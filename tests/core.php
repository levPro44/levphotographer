<?php
declare(strict_types=1);
require dirname(__DIR__).'/photo-manager/lib.php';
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
function rejects(callable $f): bool { try {$f();return false;}catch(Throwable){return true;} }
$dir=sys_get_temp_dir().'/levpro-test-'.bin2hex(random_bytes(8));
mkdir($dir,0700);mkdir($dir.'/images');mkdir($dir.'/data');mkdir($dir.'/outside');
$cfg=$dir.'/config.php';file_put_contents($cfg,'<?php return '.var_export(['images'=>$dir.'/images','data'=>$dir.'/data','web'=>$dir],true).';');putenv('LEVPRO_CONFIG='.$cfg);
try {
 file_put_contents($dir.'/images/photo.jpg','fixture');
 check(safePath($dir.'/images','photo.jpg')===realpath($dir.'/images/photo.jpg'),'indexed path');
 foreach(['../outside/x','/etc/passwd','a/../../x','a\\b',"a\0b",'C:/x','./photo.jpg'] as $p) check(rejects(fn()=>safePath($dir.'/images',$p)),'reject traversal');
 file_put_contents($dir.'/outside/file.jpg','outside');symlink($dir.'/outside',$dir.'/images/link');
 check(rejects(fn()=>safePath($dir.'/images','link/file.jpg')),'reject ancestor symlink');
 check(!validCsrf('','') && !validCsrf(null,null) && !validCsrf(str_repeat('a',64),['a']),'reject empty/invalid csrf');
 check(validCsrf(str_repeat('a',64),str_repeat('a',64)),'valid csrf');
 check(settings([])===['format'=>'webp','width'=>1600,'quality'=>88],'default settings');
 check(rejects(fn()=>settings(['quality'=>49])) && rejects(fn()=>settings(['width'=>999])),'invalid settings');
 saveJson($dir.'/data/check.json',['ok'=>true]);check(readJson($dir.'/data/check.json')['ok'],'atomic json');
 file_put_contents($dir.'/data/corrupt.json','{');check(rejects(fn()=>readJson($dir.'/data/corrupt.json')),'corrupt manifest fails closed');
 if (codec('webp')) {
  $im=new Imagick();$im->newImage(40,20,'white','jpeg');$im->writeImage($dir.'/images/photo.jpg');$im->clear();
  $id=str_repeat('a',64);saveJson($dir.'/data/catalog-v31.json',['groups'=>[$id=>['files'=>['webp'=>[]]]],'files'=>[]]);
  $r=operation(fn()=>convertOne('photo.jpg',$id,settings([])));
  check($r['width']===40 && $r['height']===20,'conversion does not upscale');
  check(outputValid($dir.'/images/'.$r['target'],settings([]),[40,20]),'actual output content');
  $again=operation(fn()=>convertOne('photo.jpg',$id,settings([])));check($again['status']==='skipped','same source and settings skipped');
  $other=operation(fn()=>convertOne('photo.jpg',$id,settings(['quality'=>90])));check($other['target']!==$r['target'],'different quality creates distinct target');
  file_put_contents($dir.'/images/'.$r['target'],'damaged');check(rejects(fn()=>operation(fn()=>convertOne('photo.jpg',$id,settings([])))),'damaged existing output not overwritten');
 }
 echo "All core checks passed\n";
} finally {
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $f) { if($f->isLink()||$f->isFile()) unlink($f->getPathname()); else rmdir($f->getPathname()); } rmdir($dir);
}
