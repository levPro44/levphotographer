"""Isolated HTTP smoke tests. Requires PHP 8.4, Imagick and Python 3 on Linux.
Run: PHP_BIN=/path/to/php python3 tests/integration.py
No production paths, passwords, sessions or photographs are read.
"""
import http.client
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlencode

PHP = os.environ.get('PHP_BIN', 'php')
SOURCE = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='levpro-http-') as temp:
    base = Path(temp)
    web = base / 'httpdocs'
    data = base / 'private' / 'levpro-photo-manager'
    images = web / 'images'
    images.mkdir(parents=True)
    (data / 'sessions').mkdir(parents=True)
    (web / 'photo-manager').symlink_to(SOURCE / 'photo-manager', target_is_directory=True)
    env = dict(os.environ, LEVPRO_SITE=str(base))
    fixture = '''
    $root=getenv('LEVPRO_SITE');
    file_put_contents($root.'/private/levpro-photo-manager/password.hash',password_hash('fixture-only',PASSWORD_DEFAULT));
    $im=new Imagick();$im->newImage(40,20,'white','jpeg');$im->writeImage($root.'/httpdocs/images/exact.jpg');
    mkdir($root.'/httpdocs/images/unrelated');$im->writeImage($root.'/httpdocs/images/unrelated/exact.jpg');
    file_put_contents($root.'/httpdocs/images/exact.pdf','test-document-not-a-photo');
    echo 'fixtures ready';
    '''
    subprocess.run([PHP, '-r', fixture], env=env, check=True, capture_output=True)
    legacy = {'version': 2, 'photos': [
        {'name': 'exact', 'jpeg': [{'path': 'exact.jpg', 'size': 1}], 'jpeg_bytes': 1},
        {'name': 'unrelated', 'jpeg': [{'path': 'unrelated/exact.jpg', 'size': 1}], 'jpeg_bytes': 1}]}
    (data / 'photo-index.json').write_text(json.dumps(legacy))
    subprocess.run([PHP, str(SOURCE / 'bin/reindex.php')], env=env, check=True, capture_output=True)
    cat = json.loads((data / 'catalog-v31.json').read_text())
    groups = list(cat['groups'].values())
    assert len(groups) == 2
    document = next(g for g in groups if g['name'] == 'exact')
    clean = next(g for g in groups if g['name'] == 'unrelated')
    assert len(document['files']['pdf']) == 1 and len(clean['files']['pdf']) == 0
    print('PASS exact directory-aware format association')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    with tempfile.TemporaryFile() as log:
        server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(web)], env=env, stdout=log, stderr=log)
        try:
            cookie = ''
            def request(path, values=None):
                global cookie
                conn = http.client.HTTPConnection('127.0.0.1', port, timeout=30)
                headers = {'Cookie': cookie}
                if values is not None:
                    headers['Content-Type'] = 'application/x-www-form-urlencoded'
                conn.request('POST' if values is not None else 'GET', path,
                             urlencode(values, doseq=True) if values is not None else None, headers)
                res = conn.getresponse()
                new_cookie = res.getheader('Set-Cookie')
                if new_cookie:
                    cookie = new_cookie.split(';')[0]  # Explicit test client; production cookies remain Secure.
                body = res.read().decode()
                status = res.status
                conn.close()
                return status, body
            for _ in range(50):
                try:
                    status, body = request('/photo-manager/')
                    break
                except OSError:
                    time.sleep(.05)
            else:
                raise RuntimeError('Test server did not start')
            assert status == 200
            csrf = re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]
            assert request('/photo-manager/api.php', {'action': 'create'})[0] == 401
            assert request('/photo-manager/', {'csrf': csrf, 'password': 'fixture-only'})[0] == 302
            status, body = request('/photo-manager/')
            assert status == 200 and 'photo-check' in body
            csrf = re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]
            assert request('/photo-manager/api.php', {'action': 'create', 'csrf': 'bad'})[0] == 403
            print('PASS authentication, session regeneration and CSRF HTTP guards')
            status, body = request('/photo-manager/api.php', {'action': 'create', 'csrf': csrf, 'selected[]': [clean['id']]})
            assert status == 200, body
            job = json.loads(body)['data']['job']
            status, body = request('/photo-manager/api.php', {'action': 'step', 'csrf': csrf, 'job': job})
            result = json.loads(body)['data']
            assert status == 200 and result['items'][0]['status'] == 'converted', body
            status, body = request('/photo-manager/api.php', {'action': 'step', 'csrf': csrf, 'job': job})
            assert json.loads(body)['data']['items'][0]['status'] == 'converted'
            print('PASS bounded conversion and idempotent completed job')
            assert request('/photo-manager/delete.php', {'csrf': csrf})[0] == 409
            assert (images / 'exact.jpg').is_file() and (images / 'exact.pdf').is_file()
            print('PASS destructive endpoint blocked, JPEG/PDF preserved')
            # A pending step whose output exists without its journal must fail closed.
            (data / 'conversions-v31.json').write_text('{}')
            status, body = request('/photo-manager/api.php', {'action': 'create', 'csrf': csrf, 'selected[]': [clean['id']]})
            job = json.loads(body)['data']['job']
            status, body = request('/photo-manager/api.php', {'action': 'step', 'csrf': csrf, 'job': job})
            assert json.loads(body)['data']['items'][0]['status'] == 'failed'
            print('PASS partial-publication recovery refuses untracked overwrite')
        finally:
            server.terminate()
            server.wait(timeout=10)
print('All isolated HTTP checks passed')
