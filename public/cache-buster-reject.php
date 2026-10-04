<?php

http_response_code(400);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; script-src 'none'; style-src 'none'; img-src 'none'; connect-src 'none'; frame-src 'none'; font-src 'none'; media-src 'none'; object-src 'none'; manifest-src 'none'; worker-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");

echo 'Bad Request';
