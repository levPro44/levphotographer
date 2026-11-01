<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
startSession();
header('Content-Type: application/json; charset=utf-8');
try {
    if (empty($_SESSION['auth'])) { http_response_code(401); throw new RuntimeException('Нужно войти'); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validCsrf($_SESSION['csrf'] ?? null,$_POST['csrf'] ?? null)) {
        http_response_code(403); throw new RuntimeException('Недействительный токен сессии');
    }
    $owner = hash('sha256',session_id()); session_write_close();
    $action = $_POST['action'] ?? '';
    $result = operation(function() use ($action,$owner) {
        $data = config()['data'];
        if ($action === 'create') {
            $s = settings($_POST);
            if (!codec($s['format'])) throw new RuntimeException('Кодек не поддерживается');
            $ids = $_POST['selected'] ?? [];
            if (!is_array($ids) || !count($ids) || count($ids)>50) throw new RuntimeException('Выберите от 1 до 50 фотографий');
            foreach ($ids as $id) if (!is_string($id) || !preg_match('/^[a-f0-9]{64}$/D',$id)) throw new RuntimeException('Неверный идентификатор');
            $cat = catalog(); $items = [];
            foreach (array_unique($ids) as $id) {
                if (!is_string($id) || !isset($cat['groups'][$id])) throw new RuntimeException('Неизвестная фотография');
                $g = actualGroup($cat['groups'][$id]);
                if (isset($_POST['exclude_documents']) && ($g['counts']['ai'] || $g['counts']['pdf'])) {
                    $items[] = ['group'=>$id,'source'=>null,'status'=>'skipped','message'=>'В группе есть AI или PDF']; continue;
                }
                if (!$g['files']['jpeg']) { $items[] = ['group'=>$id,'source'=>null,'status'=>'skipped','message'=>'JPEG отсутствует']; continue; }
                foreach ($g['files']['jpeg'] as $source) {
                    if (str_starts_with($source,'levpro-v31/')) continue;
                    $items[] = ['group'=>$id,'source'=>$source,'status'=>'pending'];
                }
            }
            $id = bin2hex(random_bytes(16));
            $job = ['id'=>$id,'owner'=>$owner,'settings'=>$s,'items'=>$items,'created'=>gmdate('c')];
            saveJson($data.'/job-'.$id.'.json',$job);
            eventLog('job_created',['job'=>$id,'items'=>count($items)]);
            return ['job'=>$id,'items'=>$items];
        }
        if ($action === 'step' || $action === 'status') {
            $id = $_POST['job'] ?? '';
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D',$id)) throw new RuntimeException('Неверное задание');
            $file = $data.'/job-'.$id.'.json'; $job = readJson($file);
            if (($job['owner'] ?? null) !== $owner) throw new RuntimeException('Задание недоступно');
            if ($action === 'step') {
                foreach ($job['items'] as &$item) {
                    if (!in_array($item['status'],['pending','processing'],true)) continue;
                    $item['status'] = 'processing'; saveJson($file,$job);
                    try {
                        $r = convertOne($item['source'],$item['group'],$job['settings']);
                        $item['status'] = $r['status']; $item['result'] = $r;
                    } catch (Throwable $e) {
                        $item['status'] = 'failed'; $item['message'] = $e->getMessage();
                        eventLog('conversion_failed',['job'=>$id,'source_id'=>hash('sha256',$item['source'])]);
                    }
                    saveJson($file,$job); break; // One JPEG per request, never 50 groups synchronously.
                }
                unset($item);
            }
            unset($job['owner']); return $job;
        }
        throw new RuntimeException('Операция не поддерживается');
    });
    echo json_encode(['ok'=>true,'data'=>$result],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
