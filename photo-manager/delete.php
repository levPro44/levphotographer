<?php
require __DIR__.'/lib.php';
startSession();
http_response_code(409);
exit('Удаление JPEG в этой бете заблокировано: аудит динамических ссылок и CMS ещё не подтверждён. Файлы сохранены.');
