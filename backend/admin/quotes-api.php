<?php
/**
 * 角色语录库 quotes.json —— JSON API（Session 鉴权）
 * 兼容 yingxue/api/admin.php 的请求/响应形态，便于沿用原前端脚本。
 *
 * GET  ?slug=&op=quotes|topics&page=&per_page=&topic=&q=
 * POST ?slug=&op=quotes|quotes/update|quotes/delete  body JSON
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_admin_require();

header('Content-Type: application/json; charset=utf-8');

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
if ($slug === '' && isset($_POST['slug'])) {
    $slug = trim((string)$_POST['slug']);
}
if (!mrd_role_exists($slug)) {
    echo json_encode(array('ok' => false, 'error' => '角色不存在'), JSON_UNESCAPED_UNICODE);
    exit;
}

$op = isset($_GET['op']) ? trim((string)$_GET['op']) : 'quotes';
$method = $_SERVER['REQUEST_METHOD'];

function quotes_api_ok($payload) {
    echo json_encode(array_merge(array('ok' => true), $payload), JSON_UNESCAPED_UNICODE);
    exit;
}
function quotes_api_err($msg) {
    echo json_encode(array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($op === 'init' && $method === 'POST') {
    $res = mrd_ensure_role_json($slug, 'quotes.json');
    if ($res !== true) {
        quotes_api_err($res);
    }
    quotes_api_ok(array('created' => true));
}

if ($op === 'topics' && $method === 'GET') {
    $data = mrd_load_role_json($slug, 'quotes.json');
    if ($data === null) {
        quotes_api_err('quotes.json 不存在');
    }
    quotes_api_ok(array('topics' => mrd_quotes_topics_summary($data)));
}

if ($op === 'quotes' && $method === 'GET') {
    $res = mrd_quotes_list(
        $slug,
        isset($_GET['page']) ? $_GET['page'] : 1,
        isset($_GET['per_page']) ? $_GET['per_page'] : 10,
        isset($_GET['topic']) ? $_GET['topic'] : '',
        isset($_GET['q']) ? $_GET['q'] : ''
    );
    if (is_string($res)) {
        quotes_api_err($res);
    }
    quotes_api_ok($res);
}

$body = mrd_body();

if ($op === 'quotes' && $method === 'POST') {
    $item = mrd_quotes_add(
        $slug,
        isset($body['topic_id']) ? $body['topic_id'] : '',
        isset($body['text']) ? $body['text'] : '',
        isset($body['keywords']) ? $body['keywords'] : array(),
        isset($body['source']) ? $body['source'] : ''
    );
    if (is_string($item)) {
        quotes_api_err($item);
    }
    quotes_api_ok(array('item' => $item));
}

if ($op === 'quotes/update' && $method === 'POST') {
    $item = mrd_quotes_update(
        $slug,
        isset($body['topic_id']) ? $body['topic_id'] : '',
        isset($body['id']) ? $body['id'] : '',
        $body
    );
    if (is_string($item)) {
        quotes_api_err($item);
    }
    quotes_api_ok(array('item' => $item));
}

if ($op === 'quotes/delete' && $method === 'POST') {
    $topicId = isset($body['topic_id']) ? $body['topic_id'] : (isset($body['topic']) ? $body['topic'] : '');
    $qid = isset($body['id']) ? $body['id'] : '';
    $del = mrd_quotes_delete($slug, $topicId, $qid);
    // 成功返回被删 id；失败返回错误文案
    if ($del !== $qid) {
        quotes_api_err(is_string($del) ? $del : '删除失败');
    }
    quotes_api_ok(array('deleted' => $del));
}

http_response_code(404);
quotes_api_err('未知管理接口：' . $op);
