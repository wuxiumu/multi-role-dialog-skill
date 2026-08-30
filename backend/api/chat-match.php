<?php
/**
 * POST /api/chat-match.php
 * Body: { role, message, session_id? }
 * 有 quotes.json → 语录 mock 引擎；否则 → qa.md 关键词匹配。
 * 若同问题已有模型缓存，优先展示最新缓存，variants 中仍可切回默认语录。
 * 响应含 debug/trace，供前台调试面板。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mrd_fail('仅支持 POST', 405, 405);
}

$body = mrd_body();
$slug = isset($body['role']) ? trim((string)$body['role']) : '';
$message = isset($body['message']) ? trim((string)$body['message']) : '';
$session = isset($body['session_id']) ? (string)$body['session_id'] : 'anon';

if ($slug === '' || !mrd_slug_ok($slug)) {
    mrd_fail('role 参数无效');
}
if ($message === '') {
    mrd_fail('message 不能为空');
}
if (!mrd_role_exists($slug)) {
    mrd_fail('角色不存在', 404);
}

$result = mrd_offline_reply($slug, $message, $session);
$trace = isset($result['trace']) ? $result['trace'] : mrd_build_reply_trace($slug, $result, $result['source'] === 'quotes' ? 'quotes' : 'qa_md');

$payload = mrd_llm_cache_attach_variants($slug, $message, array(
    'reply' => $result['reply'],
    'source' => $result['source'],
    'matched' => !empty($result['matched']),
    'matched_topic' => isset($result['matched_topic']) ? $result['matched_topic'] : '',
    'quote_id' => isset($result['quote_id']) ? $result['quote_id'] : '',
    'risk' => !empty($result['risk']),
    'score' => isset($result['score']) ? $result['score'] : 0,
    'keys' => isset($result['keys']) ? $result['keys'] : array(),
    'role' => $slug,
    'endpoint' => 'chat-match.php',
    'from_cache' => false,
    'debug' => $trace,
), $result);

mrd_ok($payload);
