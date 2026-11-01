<?php
declare(strict_types=1);
require dirname(__DIR__).'/photo-manager/lib.php';
if (PHP_SAPI !== 'cli') exit(1);
operation(function() {
    $c = config(); $old = readJson($c['data'].'/photo-index.json');
    if (!is_array($old['photos'] ?? null)) throw new RuntimeException('Сначала нужен исходный photo-index.json');
    $cat = ['version'=>LP_VERSION,'updated'=>gmdate('c'),'groups'=>[],'files'=>[],'bytes'=>0];
    $owners = []; $stems = [];
    $add = function(string $id,string $f,string $rel) use (&$cat) {
        try { $p = safePath(config()['images'],$rel); } catch (Throwable) { return; }
        if (!in_array($rel,$cat['groups'][$id]['files'][$f] ?? [],true)) $cat['groups'][$id]['files'][$f][] = $rel;
        $cat['files'][hash('sha256',$rel)] = $rel;
    };
    foreach ($old['photos'] as $g) {
        // Keep verified legacy grouping. Never infer a group from a short DSC identifier.
        $paths = array_column($g['jpeg'] ?? [],'path'); sort($paths);
        $id = hash('sha256',json_encode($paths)."\0".(string)$g['name']);
        $cat['groups'][$id] = ['id'=>$id,'name'=>(string)$g['name'],'files'=>array_fill_keys(['jpeg','webp','ai','pdf','avif'],[])];
        foreach ($paths as $rel) {
            $add($id,'jpeg',$rel); $owners[$rel] = $id;
            $stem = preg_replace('/\.jpe?g$/i','',$rel);
            $stems[$stem][$id] = true;
            // Legacy converter: complete original stem plus hash of complete relative path.
            $generated = 'levpro-webp-1600/'.pathinfo($rel,PATHINFO_FILENAME).'-'.substr(hash('sha256',$rel),0,12).'.webp';
            $add($id,'webp',$generated);
        }
    }
    $root = realpath($c['images']);
    if (!$root || is_link($c['images'])) throw new RuntimeException('Каталог изображений недоступен');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isLink() || !$file->isFile()) continue;
        $rel = substr($file->getPathname(),strlen($root)+1);
        try { safePath($root,$rel); } catch (Throwable) { continue; }
        $cat['bytes'] += $file->getSize();
        $ext = strtolower($file->getExtension());
        $format = in_array($ext,['jpg','jpeg'],true) ? 'jpeg' : $ext;
        if (!in_array($format,['jpeg','webp','ai','pdf','avif'],true) || str_starts_with($rel,'levpro-v31/')) continue;
        if (isset($owners[$rel])) continue;
        $stem = preg_replace('/\.[^.]+$/','',$rel);
        $ids = array_keys($stems[$stem] ?? []);
        if (count($ids) === 1) $id = $ids[0];
        else {
            $id = hash('sha256','exact:'.$stem);
            $cat['groups'][$id] ??= ['id'=>$id,'name'=>$stem,'files'=>array_fill_keys(['jpeg','webp','ai','pdf','avif'],[])];
        }
        // A legacy generated output already associated by a verified full-path hash stays there.
        if (isset($cat['files'][hash('sha256',$rel)])) continue;
        $add($id,$format,$rel);
    }
    foreach (readJson($c['data'].'/conversions-v31.json') as $r) {
        if (isset($cat['groups'][$r['group']])) $add($r['group'],$r['settings']['format'],$r['target']);
    }
    foreach ($cat['groups'] as $id=>$g) if (!array_sum(array_map('count',$g['files']))) unset($cat['groups'][$id]);
    saveJson($c['data'].'/catalog-v31.json',$cat);
    eventLog('reindexed',['groups'=>count($cat['groups']),'files'=>count($cat['files'])]);
    echo json_encode(['groups'=>count($cat['groups']),'files'=>count($cat['files']),'bytes'=>$cat['bytes']])."\n";
});
