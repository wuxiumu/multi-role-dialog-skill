<?php
/**
 * POST /api/auth.php
 * Body: { name|username, password } 登录
 * Body: { action: "logout" } + Bearer 退出
 * GET  + Bearer：校验当前 Token
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $token = mrd_bearer_token();
    $user = mrd_verify_token($token);
    if ($user === null) {
        mrd_fail('未登录', 401, 401);
    }
    mrd_ok(array(
        'user' => array('name' => $user['user']),
        'expires' => $user['expires'],
    ));
}

if ($method !== 'POST') {
    mrd_fail('仅支持 GET/POST', 405, 405);
}

$body = mrd_body();
$action = isset($body['action']) ? (string)$body['action'] : 'login';

if ($action === 'logout') {
    $token = mrd_bearer_token();
    if ($token !== '') {
        mrd_revoke_token($token);
    }
    mrd_ok(null, '已退出');
}

$name = '';
if (isset($body['name'])) {
    $name = trim((string)$body['name']);
} elseif (isset($body['username'])) {
    $name = trim((string)$body['username']);
}
$password = isset($body['password']) ? (string)$body['password'] : '';

$check = mrd_check_login($name, $password);
if ($check !== true) {
    mrd_fail($check, 401);
}

$payload = mrd_issue_token($name);
mrd_ok(array(
    'token' => $payload['token'],
    'expires' => $payload['expires'],
    'user' => array('name' => $name),
));
