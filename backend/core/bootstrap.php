<?php
/**
 * API / 后台公共引导：路径常量、配置、CORS、统一 JSON 响应。
 * PHP 7.4+ 兼容，零框架依赖。
 */

if (!defined('MRD_ROOT')) {
    define('MRD_ROOT', dirname(__DIR__));
}
if (!defined('MRD_ROLES')) {
    define('MRD_ROLES', MRD_ROOT . '/roles');
}
if (!defined('MRD_DATA')) {
    define('MRD_DATA', MRD_ROOT . '/data');
}

require_once MRD_ROOT . '/core/md_parse.php';
require_once MRD_ROOT . '/core/md_write.php';
require_once MRD_ROOT . '/core/json_store.php';
require_once MRD_ROOT . '/core/auth_ctrl.php';
require_once MRD_ROOT . '/core/match_engine.php';
require_once MRD_ROOT . '/core/quotes_engine.php';
require_once MRD_ROOT . '/core/llm_request.php';
require_once MRD_ROOT . '/core/llm_cache.php';

function mrd_config() {
    static $cfg = null;
    if ($cfg === null || !empty($GLOBALS['mrd_cfg_force'])) {
        $GLOBALS['mrd_cfg_force'] = false;
        $cfg = require MRD_ROOT . '/config/global.php';
        if (!is_array($cfg)) {
            $cfg = array();
        }
        // 后台可写覆盖（settings.json）
        $overlayPath = MRD_ROOT . '/config/settings.json';
        if (is_file($overlayPath)) {
            $over = json_decode((string)@file_get_contents($overlayPath), true);
            if (is_array($over)) {
                $cfg = array_merge($cfg, $over);
            }
        }
        // config.local.php 优先级最高（敏感密钥）
        $local = MRD_ROOT . '/config/config.local.php';
        if (is_file($local)) {
            $loc = require $local;
            if (is_array($loc)) {
                $cfg = array_merge($cfg, $loc);
            }
        }
    }
    return $cfg;
}

/** 重置配置缓存（后台改配置后调用） */
function mrd_config_reload() {
    $GLOBALS['mrd_cfg_force'] = true;
}

function mrd_config_fresh() {
    $GLOBALS['mrd_cfg_force'] = true;
    return mrd_config();
}

/**
 * CORS 由 nginx / 网关统一注入 Access-Control-*，PHP 不再设置，避免与网关重复头导致浏览器报
 * “Access-Control-Allow-Origin contains multiple values”。
 * 此处仅兜底 OPTIONS：若预检落到 PHP，直接 204，避免落入业务方法校验返回 405。
 */
function mrd_cors() {
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function mrd_json($code, $msg, $data = null, $http = 200) {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    $out = array('code' => (int)$code, 'msg' => (string)$msg);
    if ($data !== null) {
        $out['data'] = $data;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

function mrd_ok($data = null, $msg = 'success') {
    mrd_json(200, $msg, $data);
}

function mrd_fail($msg, $code = 400, $http = 200, $data = null) {
    mrd_json($code, $msg, $data, $http);
}

function mrd_body() {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return array();
    }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : array();
}

function mrd_slug_ok($slug) {
    return is_string($slug) && preg_match('/^[a-z0-9][a-z0-9\-]{0,63}$/', $slug);
}

function mrd_ensure_dirs() {
    foreach (array(MRD_ROLES, MRD_DATA . '/tokens', MRD_DATA . '/sessions', MRD_DATA . '/session_cache') as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
    }
}

mrd_ensure_dirs();
