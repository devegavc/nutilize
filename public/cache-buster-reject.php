<?php

// #region agent log
@file_put_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'debug-afd7f1.log', json_encode(['sessionId'=>'afd7f1','hypothesisId'=>'A','location'=>'cache-buster-reject.php','message'=>'static cache-buster rejected','data'=>['script'=>1],'timestamp'=>(int) round(microtime(true)*1000),'runId'=>'post-fix'])."\n", FILE_APPEND);
// #endregion

http_response_code(400);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; script-src 'none'; style-src 'none'; img-src 'none'; connect-src 'none'; frame-src 'none'; font-src 'none'; media-src 'none'; object-src 'none'; manifest-src 'none'; worker-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");

echo 'Bad Request';
