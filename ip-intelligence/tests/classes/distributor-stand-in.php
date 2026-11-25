<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2026 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

declare(strict_types=1);

// The router of DistributorStandIn, run by PHP's built-in web server. It
// answers as the distributor does for a free data file's link, from the
// settings DistributorStandIn writes to stand-in.json in the server's
// folder, which is its document root:
//   path       the link's path
//   file       the gzip-compressed data file
//   md5        the compressed file's MD5 hash, in hex
//   published  the data file's published date, as a Unix time
//   log        where each request is recorded

$settings = json_decode(
    (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/stand-in.json'),
    true,
    2,
    JSON_THROW_ON_ERROR
);
$path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = (string) $_SERVER['REQUEST_METHOD'];
$since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
    ? (string) $_SERVER['HTTP_IF_MODIFIED_SINCE']
    : null;
$published = (int) $settings['published'];
$file = (string) $settings['file'];

if ($method !== 'GET' || $path !== $settings['path']) {
    $status = 404;
} elseif ($since !== null && strtotime($since) !== false && strtotime($since) >= $published) {
    $status = 304;
} else {
    $status = 200;
}

file_put_contents(
    (string) $settings['log'],
    json_encode([
        'method' => $method,
        'path' => $path,
        'ifModifiedSince' => $since,
        'status' => $status,
    ], JSON_THROW_ON_ERROR) . "\n",
    FILE_APPEND | LOCK_EX
);

http_response_code($status);
if ($status === 404) {
    header('Content-Type: text/plain');
    echo 'Not found';

    return;
}
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $published) . ' GMT');
if ($status === 200) {
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($file));
    header('Content-MD5: ' . $settings['md5']);
    readfile($file);
}
