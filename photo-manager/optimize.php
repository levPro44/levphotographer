<?php
require __DIR__.'/lib.php';
startSession();
http_response_code(409);
exit('Автоматическая замена ссылок отключена. Сначала подготовьте отдельный отчёт bin/audit.php. JPEG сохраняются.');
