<?php
/**
 * 前台 Token 鉴权 + 后台 Session 鉴权。
 * Token 存文件：data/tokens/{hash}.json，零数据库。
 */

function mrd_token_dir() {
    $d = MRD_DATA . '/tokens';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

function mrd_token_path($token) {
    $hash = hash('sha256', (string)$token);
    return mrd_token_dir() . '/' . $hash . '.json';
}

function mrd_issue_token($username) {
    $cfg = mrd_config();
    $ttl = isset($cfg['token_ttl']) ? (int)$cfg['token_ttl'] : 604800;
    $token = bin2hex(random_bytes(24));
    $payload = array(
        'token' => $token,
        'user' => (string)$username,
        'created' => time(),
        'expires' => time() + $ttl,
    );
    mrd_safe_write(mrd_token_path($token), json_encode($payload, JSON_UNESCAPED_UNICODE));
    return $payload;
}

function mrd_revoke_token($token) {
    $path = mrd_token_path($token);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * @return array|null 有效 payload，无效返回 null
 */
function mrd_verify_token($token) {
    if ($token === null || $token === '') {
        return null;
    }
    $path = mrd_token_path($token);
    if (!is_file($path)) {
        return null;
    }
    $j = json_decode((string)@file_get_contents($path), true);
    if (!is_array($j) || empty($j['expires']) || (int)$j['expires'] < time()) {
        @unlink($path);
        return null;
    }
    return $j;
}

function mrd_bearer_token() {
    $hdr = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $hdr = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (preg_match('/^\s*Bearer\s+(\S+)/i', $hdr, $m)) {
        return $m[1];
    }
    return '';
}

/** 要求已登录；失败则直接 JSON 退出 */
function mrd_require_user() {
    $token = mrd_bearer_token();
    $user = mrd_verify_token($token);
    if ($user === null) {
        mrd_fail('未登录或 Token 无效', 401, 401);
    }
    return $user;
}

/**
 * 校验账号密码。demo_open=true 时任意非空即可。
 * @return true|string
 */
function mrd_check_login($name, $password) {
    $name = trim((string)$name);
    $password = (string)$password;
    if ($name === '' || $password === '') {
        return '账号或密码不能为空';
    }
    $cfg = mrd_config();
    if (!empty($cfg['demo_open'])) {
        return true;
    }
    $users = isset($cfg['users']) && is_array($cfg['users']) ? $cfg['users'] : array();
    if (!isset($users[$name]) || !hash_equals((string)$users[$name], $password)) {
        return '账号或密码错误';
    }
    return true;
}

/* ---------- 后台 Session ---------- */

function mrd_admin_session_start() {
    $cfg = mrd_config();
    $name = isset($cfg['admin_session']) ? $cfg['admin_session'] : 'mrd_admin';
    if (session_status() === PHP_SESSION_NONE) {
        session_name($name);
        session_start();
    }
}

function mrd_admin_logged_in() {
    mrd_admin_session_start();
    return !empty($_SESSION['admin_ok']);
}

function mrd_admin_login($user, $password) {
    $cfg = mrd_config();
    $au = isset($cfg['admin_user']) ? (string)$cfg['admin_user'] : 'admin';
    $ap = isset($cfg['admin_password']) ? (string)$cfg['admin_password'] : '';
    if (!hash_equals($au, (string)$user) || !hash_equals($ap, (string)$password)) {
        return false;
    }
    mrd_admin_session_start();
    $_SESSION['admin_ok'] = true;
    $_SESSION['admin_user'] = $au;
    return true;
}

function mrd_admin_logout() {
    mrd_admin_session_start();
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function mrd_admin_require() {
    if (!mrd_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}
