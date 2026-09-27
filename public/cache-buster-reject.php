<?php

// #region agent log
@file_put_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'debug-a9d1a3.log', json_encode(['sessionId'=>'a9d1a3','hypothesisId'=>'B','location'=>'cache-buster-reject.php','message'=>'static cache-buster rejected','data'=>['script'=>1],'timestamp'=>(int) round(microtime(true)*1000),'runId'=>'recheck'])."\n", FILE_APPEND);
// #endregion

http_response_code(400);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

echo 'Bad Request';
