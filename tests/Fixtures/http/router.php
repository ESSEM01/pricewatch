<?php

/*
 * Router for `php -S`: a real web server, so transfers go through curl exactly
 * as in production (decompression, aborted transfers), which Http::fake skips.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/robots.txt') {
    echo '';
} elseif ($path === '/small-gzip') {
    header('Content-Encoding: gzip');
    echo gzencode('<html>small</html>');
} elseif ($path === '/gzip-bomb') {
    // 50 MB of "x", compressed on the fly: about 50 KB on the wire.
    header('Content-Encoding: gzip');

    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    $deflate = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 9]);
    $megabyte = str_repeat('x', 1024 * 1024);

    for ($i = 0; $i < 50 && ! connection_aborted(); $i++) {
        echo deflate_add($deflate, $megabyte, ZLIB_SYNC_FLUSH);
        flush();
    }

    echo deflate_add($deflate, '', ZLIB_FINISH);
} elseif ($path === '/error-large') {
    http_response_code(500);
    header('Content-Length: '.(2 * 1024 * 1024));
    echo str_repeat('x', 2 * 1024 * 1024);
} else {
    http_response_code(404);
}
