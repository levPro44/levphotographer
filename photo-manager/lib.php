<?php
declare(strict_types=1);

const LP_VERSION = '3.1.0-beta.14';
function config(): array {
    static $c;
    if ($c !== null) return $c;
    $site = getenv('LEVPRO_SITE') ?: dirname(__DIR__, 2);
    $c = ['web' => $site.'/httpdocs', 'images' => $site.'/httpdocs/images',
        'data' => $site.'/private/levpro-photo-manager'];
    $file = getenv('LEVPRO_CONFIG');
    if ($file) $c = array_replace($c, require $file);
    foreach ($c as $k => $v) $c[$k] = rtrim($v, '/');
    return $c;
}
function readJson(string $file, array $default = []): array {
    if (!is_file($file)) return $default;
    $v = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($v)) throw new RuntimeException('Некорректный файл данных');
    return $v;
}
function saveJson(string $file, array $v): void {
    $tmp = $file.'.'.bin2hex(random_bytes(8)).'.tmp';
    try {
        $s = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $s, LOCK_EX) !== strlen($s)) throw new RuntimeException('Ошибка записи');
        chmod($tmp, 0600);
        if (!rename($tmp, $file)) throw new RuntimeException('Ошибка сохранения');
    } finally { if (is_file($tmp)) unlink($tmp); }
}
function operation(callable $fn): mixed {
    $h = fopen(config()['data'].'/operations.lock', 'c');
    if (!$h || !flock($h, LOCK_EX|LOCK_NB)) throw new RuntimeException('Другая операция уже выполняется');
    try { return $fn(); } finally { flock($h, LOCK_UN); fclose($h); }
}
function safePath(string $root, string $rel): string {
    if ($rel === '' || preg_match('~[\\\\\x00:]|^/|(?:^|/)\.{1,2}(?:/|$)~', $rel))
        throw new RuntimeException('Недопустимый путь');
    $base = realpath($root);
    if (!$base) throw new RuntimeException('Каталог недоступен');
    $p = $base;
    foreach (explode('/', $rel) as $part) {
        if ($part === '') throw new RuntimeException('Пустой компонент пути');
        $p .= DIRECTORY_SEPARATOR.$part;
        if (is_link($p)) throw new RuntimeException('Символические ссылки запрещены');
    }
    $real = realpath($p);
    if (!$real || !str_starts_with($real, $base.DIRECTORY_SEPARATOR) || !is_file($real))
        throw new RuntimeException('Файл недоступен');
    return $real;
}
function eventLog(string $event, array $details): void {
    $line = json_encode(['time'=>gmdate('c'), 'event'=>$event, 'details'=>$details], JSON_THROW_ON_ERROR)."\n";
    $file = config()['data'].'/events.jsonl';
    if (file_put_contents($file, $line, FILE_APPEND|LOCK_EX) !== strlen($line)) throw new RuntimeException('Журнал недоступен');
    chmod($file, 0600);
}
function validCsrf(mixed $expected, mixed $actual): bool {
    return is_string($expected) && strlen($expected) === 64 && is_string($actual) && hash_equals($expected, $actual);
}
function startSession(): void {
    ini_set('display_errors', '0');
    ini_set('session.use_strict_mode', '1');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    session_name('levpro_photo_manager');
    session_save_path(config()['data'].'/sessions');
    session_set_cookie_params(['path'=>'/photo-manager/', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function catalog(): array { return readJson(config()['data'].'/catalog-v31.json'); }
function actualGroup(array $g): array {
    $g['counts'] = array_fill_keys(['jpeg','webp','ai','pdf','avif'], 0);
    $g['jpeg_bytes'] = 0;
    foreach ($g['files'] as $format => &$files) {
        $files = array_values(array_filter($files, function($rel) use ($format, &$g) {
            try { $p = safePath(config()['images'], $rel); }
            catch (Throwable) { return false; }
            $g['counts'][$format]++;
            if ($format === 'jpeg') $g['jpeg_bytes'] += filesize($p);
            return true;
        }));
    }
    unset($files);
    return $g;
}
function settings(array $v): array {
    $f = $v['format'] ?? 'webp';
    $w = filter_var($v['width'] ?? 1600, FILTER_VALIDATE_INT);
    $q = filter_var($v['quality'] ?? 88, FILTER_VALIDATE_INT);
    if (!in_array($f, ['webp','jpeg','avif'], true) || !in_array($w,[1200,1600,1920,2560],true) || $q === false || $q < 50 || $q > 100)
        throw new RuntimeException('Неверные настройки конвертации');
    return ['format'=>$f, 'width'=>$w, 'quality'=>$q];
}
function codec(string $format): bool {
    // Imagick is required: all EXIF orientations, including mirrored images, are respected.
    return extension_loaded('imagick') && (bool)Imagick::queryFormats(strtoupper($format));
}
function outputValid(string $p, array $s, ?array $expected = null): bool {
    $i = @getimagesize($p);
    $type = ['webp'=>IMAGETYPE_WEBP,'jpeg'=>IMAGETYPE_JPEG,'avif'=>IMAGETYPE_AVIF][$s['format']];
    return $i && $i[2] === $type && $i[0] <= $s['width'] && filesize($p) > 0
        && (!$expected || ($i[0] === $expected[0] && $i[1] === $expected[1]));
}
function convertOne(string $relative, string $group, array $s): array {
    $root = config()['images']; $data = config()['data'];
    $source = safePath($root, $relative);
    if (!preg_match('/\.jpe?g$/i', $relative) || (@getimagesize($source)[2] ?? 0) !== IMAGETYPE_JPEG)
        throw new RuntimeException('Источник не является JPEG');
    $info = getimagesize($source);
    if ($info[0]*$info[1] > 80000000 || filesize($source) > 100*1048576) throw new RuntimeException('Источник превышает лимит обработки');
    $sourceHash = hash_file('sha256', $source);
    $key = hash('sha256', $relative."\0".$sourceHash."\0".json_encode($s));
    $ext = $s['format'] === 'jpeg' ? 'jpg' : $s['format'];
    $dir = $root.'/levpro-v31';
    if (!is_dir($dir) && !mkdir($dir,0755)) throw new RuntimeException('Не создан каталог результатов');
    if (is_link($dir) || realpath($dir) !== realpath($root).'/levpro-v31') throw new RuntimeException('Небезопасный каталог результатов');
    $target = 'levpro-v31/'.$key.'.'.$ext;
    $dest = $root.'/'.$target;
    $manifest = readJson($data.'/conversions-v31.json');
    if (isset($manifest[$key]) && is_file($dest) && !is_link($dest)
        && hash_file('sha256',$dest) === $manifest[$key]['output_hash'] && outputValid($dest,$s))
        return $manifest[$key] + ['status'=>'skipped'];
    if (file_exists($dest) || is_link($dest)) throw new RuntimeException('Результат уже существует, перезапись запрещена');
    if (!codec($s['format'])) throw new RuntimeException('Кодек Imagick недоступен');
    $tmp = $dir.'/.'.bin2hex(random_bytes(16)).'.tmp';
    $im = new Imagick();
    try {
        $im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256*1048576);
        $im->setResourceLimit(Imagick::RESOURCETYPE_MAP, 512*1048576);
        $im->setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
        $im->readImage($source); $im->setIteratorIndex(0); $im->autoOrient();
        $w = $im->getImageWidth(); $h = $im->getImageHeight();
        $nw = min($w,$s['width']); $nh = max(1,(int)round($h*$nw/$w));
        if ($nw !== $w) $im->resizeImage($nw,$nh,Imagick::FILTER_LANCZOS,1);
        $im->stripImage(); $im->setImageFormat($s['format']); $im->setImageCompressionQuality($s['quality']);
        if (!$im->writeImage($tmp) || !outputValid($tmp,$s,[$nw,$nh])) throw new RuntimeException('Результат не прошёл проверку');
        if (hash_file('sha256',$source) !== $sourceHash) throw new RuntimeException('Источник изменился');
        chmod($tmp,0644);
        // Hard-link publication is atomic and refuses overwriting an existing destination.
        if (!link($tmp,$dest)) throw new RuntimeException('Не удалось опубликовать результат');
        $record = ['source'=>$relative,'target'=>$target,'group'=>$group,'source_hash'=>$sourceHash,
            'output_hash'=>hash_file('sha256',$dest),'settings'=>$s,'width'=>$nw,'height'=>$nh,
            'source_bytes'=>filesize($source),'output_bytes'=>filesize($dest),'time'=>gmdate('c')];
        $manifest[$key] = $record;
        saveJson($data.'/conversions-v31.json',$manifest);
        $cat = catalog();
        $cat['groups'][$group]['files'][$s['format']][] = $target;
        $cat['files'][hash('sha256',$target)] = $target;
        saveJson($data.'/catalog-v31.json',$cat);
        eventLog('converted',['source_id'=>hash('sha256',$relative),'target_id'=>$key,'settings'=>$s]);
        return $record + ['status'=>'converted'];
    } finally { $im->clear(); if (is_file($tmp)) unlink($tmp); }
}
function h(string $v): string { return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
