<?php
declare(strict_types=1);
require dirname(__DIR__).'/photo-manager/lib.php';
if (PHP_SAPI !== 'cli') exit(1);
// Read-only dry run. It never edits website files or certifies deletion eligibility.
operation(function() {
    $c=config(); $manifest=readJson($c['data'].'/conversions-v31.json');
    $map=[]; $ambiguous=[];
    foreach ($manifest as $r) {
        try {
            $source=safePath($c['images'],$r['source']); $target=safePath($c['images'],$r['target']);
            if (hash_file('sha256',$source)!==$r['source_hash'] || hash_file('sha256',$target)!==$r['output_hash'] || !outputValid($target,$r['settings'])) continue;
        } catch (Throwable) { continue; }
        $old='/images/'.$r['source']; $new='/images/'.$r['target'];
        if (isset($map[$old]) && $map[$old]!==$new) $ambiguous[$old]=true;
        $map[$old]=$new;
    }
    foreach ($ambiguous as $old=>$_) unset($map[$old]);
    $report=['time'=>gmdate('c'),'mode'=>'dry-run','mappings'=>$map,'ambiguous'=>array_keys($ambiguous),
        'static'=>[],'dynamic'=>[],'unreadable'=>[],'blockers'=>['CMS/базы данных не проверены','Динамические и внешние ссылки требуют проверки','Автоматическая замена и удаление отключены']];
    $web=realpath($c['web']); if (!$web) throw new RuntimeException('Корень сайта недоступен');
    $filter=new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($web,FilesystemIterator::SKIP_DOTS),function($f)use($web){
        if ($f->isLink()) return false;
        if ($f->isDir() && in_array($f->getPathname(),[$web.'/images',$web.'/photo-manager'],true)) return false;
        return true;
    });
    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if (!$file->isFile()) continue;
        $ext=strtolower($file->getExtension()); if (!in_array($ext,['html','htm','php','css','js'],true)) continue;
        $rel=substr($file->getPathname(),strlen($web)+1);
        if ($file->getSize()>8*1048576) { $report['unreadable'][]=$rel; continue; }
        $content=@file_get_contents($file->getPathname());
        if ($content===false) { $report['unreadable'][]=$rel; continue; }
        $hits=[];
        foreach ($map as $old=>$new) {
            // Case-sensitive complete root-relative URL only. srcset comma is a valid delimiter.
            $pattern='~(?<=["\'\s(=])'.preg_quote($old,'~').'(?=["\'\s?#<>,)])~';
            $n=preg_match_all($pattern,$content);
            if ($n) $hits[]=['source'=>$old,'target'=>$new,'occurrences'=>$n];
        }
        if ($hits) $report[in_array($ext,['html','htm'],true)?'static':'dynamic'][]=['file'=>$rel,'hash'=>hash('sha256',$content),'references'=>$hits];
    }
    $id=bin2hex(random_bytes(16));saveJson($c['data'].'/reference-audit-'.$id.'.json',$report);
    eventLog('reference_dry_run',['report'=>$id,'static_files'=>count($report['static']),'dynamic_files'=>count($report['dynamic'])]);
    echo json_encode(['report'=>$id,'static_files'=>count($report['static']),'dynamic_files'=>count($report['dynamic']),'deletion_eligible'=>false],JSON_UNESCAPED_UNICODE)."\n";
});
