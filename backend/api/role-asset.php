<?php
/**
 * GET /api/role-asset.php?slug=&file=
 * 输出 roles/{slug}/asset/{file}，带 CORS，供 Canvas 海报跨域绘制。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method Not Allowed';
    exit;
}

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$file = isset($_GET['file']) ? trim((string)$_GET['file']) : '';

if (!mrd_slug_ok($slug) || $file === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad Request';
    exit;
}

$file = basename(str_replace(array('\\', "\0"), '', $file));
if ($file === '' || $file === '.' || $file === '..') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad Request';
    exit;
}

if (!mrd_role_exists($slug)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit;
}

$path = mrd_role_dir($slug) . '/asset/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimes = array(
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
);
$mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
