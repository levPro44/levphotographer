<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
startSession();
$csrf = $_SESSION['csrf']; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf($csrf,$_POST['csrf'] ?? null)) { http_response_code(403); exit('Недействительный токен сессии'); }
    if (isset($_POST['logout'])) { $_SESSION=[]; session_destroy(); header('Location: ./'); exit; }
    $hash = trim((string)@file_get_contents(config()['data'].'/password.hash'));
    $rateFile = config()['data'].'/login-rate.json';
    $ok = operation(function() use ($rateFile,$hash) {
        $rates = readJson($rateFile); $now = time();
        foreach ($rates as $k=>$v) if ($v['until']<$now) unset($rates[$k]);
        $key = hash('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $r = $rates[$key] ?? ['count'=>0,'until'=>$now+900];
        if ($r['count']>=10) return false;
        $ok = $hash && is_string($_POST['password'] ?? null) && strlen($_POST['password'])<=1024 && password_verify($_POST['password'],$hash);
        if ($ok) unset($rates[$key]); else { $r['count']++; $rates[$key]=$r; }
        saveJson($rateFile,$rates); return (bool)$ok;
    });
    if ($ok) { session_regenerate_id(true); $_SESSION['auth']=true; $_SESSION['csrf']=bin2hex(random_bytes(32)); header('Location: ./'); exit; }
    $error='Вход не выполнен. Проверьте пароль; после 10 попыток подождите 15 минут.';
}
if (empty($_SESSION['auth'])): ?>
<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход · LevPro</title><link rel="stylesheet" href="assets/app.css">
<main class="login"><h1>LevPro Photo Manager</h1><p><?=h($error)?></p><form method="post">
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><label>Пароль<input type="password" name="password" autocomplete="current-password" required></label><button>Войти</button></form></main></html>
<?php exit; endif;
session_write_close();
try { $cat = catalog(); if (!isset($cat['groups'])) throw new RuntimeException(); }
catch (Throwable) { http_response_code(503); exit('Подготовьте каталог командой bin/reindex.php через PHP CLI.'); }
if (isset($_GET['photo'])) {
    try {
        $id = (string)$_GET['photo'];
        if (!isset($cat['files'][$id])) throw new RuntimeException();
        $p = safePath(config()['images'],$cat['files'][$id]);
        $mime = @getimagesize($p)['mime'] ?? '';
        if (!in_array($mime,['image/jpeg','image/webp','image/avif'],true)) throw new RuntimeException();
        header('Content-Type: '.$mime); header('Content-Length: '.filesize($p)); readfile($p); exit;
    } catch (Throwable) { http_response_code(404); exit; }
}
$q = mb_substr((string)($_GET['q'] ?? ''),0,200); $filter = (string)($_GET['filter'] ?? 'all');
$sort = (string)($_GET['sort'] ?? 'size');
$groups = [];
// No recursion: verify only paths in the prepared catalog. Counts reflect existing files.
foreach ($cat['groups'] as $g) {
    if ($q !== '' && mb_stripos($g['name'],$q) === false) continue;
    $g = actualGroup($g); $c = $g['counts'];
    $match = match ($filter) {
        'jpeg_only'=>$c['jpeg']>0 && !$c['webp'] && !$c['ai'] && !$c['pdf'],
        'webp_only'=>$c['webp']>0 && !$c['jpeg'] && !$c['ai'] && !$c['pdf'],
        'jpeg_webp'=>$c['jpeg']>0 && $c['webp']>0,
        default=>true
    };
    if ($match) $groups[]=$g;
}
usort($groups,fn($a,$b)=>($sort==='name' ? strnatcasecmp($a['name'],$b['name']) : $b['jpeg_bytes']<=>$a['jpeg_bytes']) ?: strcmp($a['id'],$b['id']));
$pages=max(1,(int)ceil(count($groups)/50)); $page=max(1,min($pages,(int)($_GET['page'] ?? 1)));
$current=array_slice($groups,($page-1)*50,50);
function pageUrl(int $page): string { return '?'.http_build_query(array_merge($_GET,['page'=>$page])); }
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LevPro Photo Manager <?=h(LP_VERSION)?></title><link rel="stylesheet" href="assets/app.css"><script src="assets/selection.js" defer></script><script src="assets/app.js" defer></script></head>
<body><header><div><h1>LevPro Photo Manager</h1><span><?=h(LP_VERSION)?></span></div><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><button name="logout">Выйти</button></form></header>
<main><div class="notice">Бета для проверки на стенде. JPEG сохраняются. Изменение ссылок и очистка сайта не выполняются автоматически.</div>
<section class="stats"><div><strong><?=count($groups)?></strong> групп по фильтру</div><div><strong><?=$page?> / <?=$pages?></strong> страниц</div><div><strong><?=number_format(($cat['bytes'] ?? 0)/1073741824,2,',',' ')?></strong> ГиБ при индексации</div><div>Каталог обновлён<br><?=h($cat['updated'])?></div></section>
<form class="panel filters" method="get"><label>Поиск<input name="q" value="<?=h($q)?>" placeholder="Имя файла"></label><label>Показать<select name="filter">
<?php foreach (['all'=>'Все фотографии','jpeg_only'=>'Только JPEG','webp_only'=>'Только WebP','jpeg_webp'=>'JPEG + WebP'] as $k=>$v): ?><option value="<?=$k?>" <?=$k===$filter?'selected':''?>><?=$v?></option><?php endforeach; ?>
</select></label><label>Сортировка<select name="sort"><option value="size">Размер JPEG</option><option value="name" <?=$sort==='name'?'selected':''?>>Имя фотографии</option></select></label><button>Применить</button></form>
<section class="panel"><h2>Конвертация</h2><div class="filters"><label>Формат<select id="format">
<?php foreach (['webp'=>'WebP','jpeg'=>'JPEG','avif'=>'AVIF'] as $k=>$v): ?><option value="<?=$k?>" <?=!codec($k)?'disabled':''?>><?=$v?><?=!codec($k)?' — кодек недоступен':''?></option><?php endforeach; ?>
</select></label><label>Максимальная ширина<select id="width"><?php foreach ([1200,1600,1920,2560] as $w): ?><option <?=$w===1600?'selected':''?>><?=$w?></option><?php endforeach; ?></select></label><label>Качество<input type="number" id="quality" min="50" max="100" value="88"></label><label class="inline"><input type="checkbox" id="exclude" checked>Пропускать группы с AI/PDF</label></div><p>Обрабатываются JPEG-варианты выбранных групп последовательно, по одному файлу. Оригиналы сохраняются. Перезаписи нет.</p></section>
<section class="panel filters"><label>Массовый выбор<select id="select-mode"><option value="all">Все видимые</option><option value="jpeg_only">Только JPEG</option><option value="webp_only">Только WebP</option><option value="jpeg_webp">JPEG + WebP</option></select></label><button id="select-all" type="button">Выбрать на странице</button><button id="clear" type="button">Снять выделение</button><span id="eligible"></span></section>
<div class="grid" id="gallery" data-csrf="<?=h($csrf)?>">
<?php foreach ($current as $g):
 $c=$g['counts']; $previews=array_merge($g['files']['jpeg'],$g['files']['webp'],$g['files']['avif']);
 usort($previews,fn($a,$b)=>filesize(safePath(config()['images'],$a))<=>filesize(safePath(config()['images'],$b)));
 $url=$previews ? '?photo='.hash('sha256',$previews[0]) : null;
?>
<article class="card" data-id="<?=$g['id']?>" data-jpeg="<?=$c['jpeg']?>" data-webp="<?=$c['webp']?>" data-ai="<?=$c['ai']?>" data-pdf="<?=$c['pdf']?>" data-bytes="<?=$g['jpeg_bytes']?>">
<?php if ($url): ?><a href="<?=h($url)?>" target="_blank" rel="noopener"><img src="<?=h($url)?>" loading="lazy" decoding="async" alt="<?=h($g['name'])?>" width="240" height="170"></a><?php else: ?><div class="no-preview">Предпросмотр отсутствует</div><?php endif; ?>
<div class="info"><label class="inline"><input type="checkbox" class="photo-check" value="<?=$g['id']?>"><?=h($g['name'])?></label><div class="badges">
<?php foreach (['jpeg'=>'JPEG','webp'=>'WebP','ai'=>'AI','pdf'=>'PDF'] as $f=>$label): ?><span class="badge <?=$c[$f]?'has':'missing'?>"><?=$label?>: <?=$c[$f]?></span><?php endforeach; ?>
</div><p>JPEG: <?=number_format($g['jpeg_bytes']/1048576,2,',',' ')?> МиБ</p><?php if ($url): ?><a href="<?=h($url)?>" target="_blank" rel="noopener">Открыть изображение</a><?php endif; ?></div></article>
<?php endforeach; ?></div>
<nav><?php if ($page>1): ?><a href="<?=h(pageUrl($page-1))?>">← Назад</a><?php endif; ?><span><?=$page?> / <?=$pages?></span><?php if ($page<$pages): ?><a href="<?=h(pageUrl($page+1))?>">Далее →</a><?php endif; ?></nav>
<section class="panel"><h2>Ход обработки</h2><progress id="progress" value="0" max="1"></progress><p id="job-status" role="status" aria-live="polite">Задание не запущено</p><button id="resume" type="button" hidden>Продолжить задание</button><button id="pause" type="button" hidden>Приостановить после текущего файла</button><pre id="report"></pre></section>
<section class="panel"><h2>Ссылки и очистка</h2><p>Предварительный аудит: <code>bin/audit.php</code>. Статические ссылки проверяются отдельно от PHP, CSS, JS и CMS. До завершения проверки удаление JPEG заблокировано. Перемещение в карантин не считается освобождением места.</p></section>
</main><footer><span id="summary">Выбрано: 0</span><button id="convert" type="button" disabled>Конвертировать выбранное</button></footer></body></html>
