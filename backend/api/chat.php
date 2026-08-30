<?php
/**
 * POST /api/chat.php
 * 兼容旧赢学前端契约：{ message, mode?, history?, session_id?, role? }
 * mock → quotes 引擎（默认 role=yingxue）；live → 走 LLM（需配置）。
 * 默认 SSE 流式；body.stream=false 或 ?stream=0 返回 JSON {ok, reply, ...}。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => '仅支持 POST'), JSON_UNESCAPED_UNICODE);
    exit;
}

$body = mrd_body();
$message = isset($body['message']) ? trim((string)$body['message']) : '';
$session = isset($body['session_id']) ? (string)$body['session_id'] : 'anon';
$slug = isset($body['role']) ? trim((string)$body['role']) : 'yingxue';
$mode = isset($body['mode']) ? (string)$body['mode'] : 'mock';
if ($mode !== 'live') {
    $mode = 'mock';
}

$wantStream = true;
if (isset($body['stream'])) {
    $wantStream = !empty($body['stream']);
} elseif (isset($_GET['stream']) && ($_GET['stream'] === '0' || $_GET['stream'] === 'false')) {
    $wantStream = false;
}

if ($message === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => '消息不能为空'), JSON_UNESCAPED_UNICODE);
    exit;
}
if (!mrd_slug_ok($slug) || !mrd_role_exists($slug)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => '角色不存在'), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($mode === 'live') {
    $cfg = mrd_config();
    if (empty($cfg['llm_enabled']) || empty($cfg['llm_endpoint']) || empty($cfg['llm_key'])) {
        $mode = 'mock';
    } else {
        $prompt = mrd_load_prompt($slug);
        $persona = mrd_load_role_json($slug, 'persona.json');
        if (is_array($persona) && !empty($persona['system_prompt'])) {
            $prompt = (string)$persona['system_prompt'];
        }
        $history = isset($body['history']) && is_array($body['history']) ? $body['history'] : array();
        $llm = mrd_llm_chat($prompt, $message, $history, $cfg);
        if (!empty($llm['ok']) && !empty($llm['reply'])) {
            $res = array(
                'reply' => $llm['reply'],
                'matched_topic' => '',
                'quote_id' => '',
                'risk' => false,
            );
            if ($wantStream) {
                mrd_stream_sse($res, 'live');
            } else {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array(
                    'ok' => true, 'mode' => 'live',
                    'reply' => $res['reply'],
                    'matched_topic' => '', 'quote_id' => '', 'risk' => false,
                ), JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
        $mode = 'mock'; // LLM 失败降级
    }
}

$result = mrd_offline_reply($slug, $message, $session);
$res = array(
    'reply' => $result['reply'],
    'matched_topic' => isset($result['matched_topic']) ? $result['matched_topic'] : '',
    'quote_id' => isset($result['quote_id']) ? $result['quote_id'] : '',
    'risk' => !empty($result['risk']),
);

if ($wantStream) {
    mrd_stream_sse($res, $mode === 'live' ? 'mock' : $mode);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'ok' => true,
        'mode' => 'mock',
        'reply' => $res['reply'],
        'matched_topic' => $res['matched_topic'],
        'quote_id' => $res['quote_id'],
        'risk' => $res['risk'],
        'source' => isset($result['source']) ? $result['source'] : 'quotes',
    ), JSON_UNESCAPED_UNICODE);
}
exit;

function mrd_stream_sse($res, $mode) {
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', 'off');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $meta = array(
        'ok' => true,
        'mode' => $mode,
        'matched_topic' => $res['matched_topic'],
        'quote_id' => $res['quote_id'],
        'risk' => !empty($res['risk']),
    );
    echo 'event: meta' . "\n" . 'data: ' . json_encode($meta, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();

    $text = $res['reply'];
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) {
        $chars = str_split($text);
    }
    foreach ($chars as $c) {
        echo 'data: ' . json_encode(array('delta' => $c), JSON_UNESCAPED_UNICODE) . "\n\n";
        @flush();
        usleep(18000);
    }
    echo 'data: [DONE]' . "\n\n";
    @flush();
}
