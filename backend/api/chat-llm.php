<?php
/**
 * POST /api/chat-llm.php
 * Header: Authorization: Bearer <token>
 * Body: { role, message, history?, session_id?, provider?, bypass_cache? }
 * provider=none|deepseek|qwen|... ；none 或未配置则直接离线。
 * 命中缓存：同一「角色+问题+模型」直接返回，不重复打上游。
 * 响应含 variants：同问题下各模型缓存，供会话内切换答案。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mrd_fail('仅支持 POST', 405, 405);
}

mrd_require_user();

$body = mrd_body();
$slug = isset($body['role']) ? trim((string)$body['role']) : '';
$message = isset($body['message']) ? trim((string)$body['message']) : '';
$history = isset($body['history']) && is_array($body['history']) ? $body['history'] : array();
$session = isset($body['session_id']) ? (string)$body['session_id'] : 'anon';
$providerId = isset($body['provider']) ? trim((string)$body['provider']) : '';
$bypassCache = !empty($body['bypass_cache']);

if ($slug === '' || !mrd_slug_ok($slug)) {
    mrd_fail('role 参数无效');
}
if ($message === '') {
    mrd_fail('message 不能为空');
}
if (!mrd_role_exists($slug)) {
    mrd_fail('角色不存在', 404);
}

$cfg = mrd_config();
if ($providerId === '') {
    $providerId = isset($cfg['llm_default_provider']) ? (string)$cfg['llm_default_provider'] : 'none';
}

$prompt = mrd_load_prompt($slug);
$persona = mrd_load_role_json($slug, 'persona.json');
$promptFrom = 'prompt.md';
if (is_array($persona) && !empty($persona['system_prompt'])) {
    $prompt = (string)$persona['system_prompt'];
    $promptFrom = 'persona.json.system_prompt';
}

$disclaimer = isset($cfg['disclaimer']) ? "\n\n【声明】" . $cfg['disclaimer'] : '';
$disclaimerFrom = 'global';
if (is_array($persona) && !empty($persona['disclaimer_text'])) {
    $disclaimer = "\n\n【声明】" . $persona['disclaimer_text'];
    $disclaimerFrom = 'persona.json.disclaimer_text';
}
$system = $prompt . $disclaimer;

$questionHash = mrd_llm_cache_question_hash($message);

// 明确不用模型 → 离线；若该问题已有模型缓存，仍优先展示最新缓存（可切回默认语录）
if ($providerId === 'none' || $providerId === 'off') {
    $result = mrd_offline_reply($slug, $message, $session);
    $trace = isset($result['trace']) ? $result['trace'] : mrd_build_reply_trace($slug, $result, $result['source'] === 'quotes' ? 'quotes' : 'qa_md');
    $trace['llm'] = array(
        'ok' => false,
        'skipped' => true,
        'provider' => 'none',
        'error' => '用户选择不使用模型',
        'prompt_from' => $promptFrom,
        'disclaimer_from' => $disclaimerFrom,
        'fallback_to' => $result['source'],
    );
    $payload = mrd_llm_cache_attach_variants($slug, $message, array(
        'reply' => $result['reply'],
        'source' => $result['source'],
        'degraded' => false,
        'provider' => 'none',
        'matched' => !empty($result['matched']),
        'matched_topic' => isset($result['matched_topic']) ? $result['matched_topic'] : '',
        'quote_id' => isset($result['quote_id']) ? $result['quote_id'] : '',
        'risk' => !empty($result['risk']),
        'role' => $slug,
        'endpoint' => 'chat-llm.php',
        'from_cache' => false,
        'debug' => $trace,
    ), $result);
    mrd_ok($payload);
}

// 缓存命中：同一问题下任一模有缓存则直接返回最新一条（不打上游）
if (!$bypassCache) {
    $listedEarly = mrd_llm_cache_list_variants($slug, $message);
    $latestHit = mrd_llm_cache_latest_model_variant($listedEarly['variants']);
    if ($latestHit !== null) {
        $pubProvider = isset($latestHit['provider']) ? (string)$latestHit['provider'] : $providerId;
        $fake = array(
            'matched' => true,
            'matched_topic' => '',
            'quote_id' => '',
            'score' => 0,
            'keys' => array(),
            'risk' => false,
            'source' => 'llm',
        );
        $trace = mrd_build_reply_trace($slug, $fake, 'llm');
        $trace['llm'] = array(
            'ok' => true,
            'from_cache' => true,
            'prompt_from' => $promptFrom,
            'disclaimer_from' => $disclaimerFrom,
            'provider' => $pubProvider,
            'provider_name' => isset($latestHit['provider_name']) ? (string)$latestHit['provider_name'] : $pubProvider,
            'model' => isset($latestHit['model']) ? (string)$latestHit['model'] : '',
            'cached_at' => isset($latestHit['cached_at']) ? (string)$latestHit['cached_at'] : '',
            'llm_id' => isset($latestHit['llm_id']) ? (string)$latestHit['llm_id'] : '',
            'finish_reason' => isset($latestHit['finish_reason']) ? (string)$latestHit['finish_reason'] : '',
            'prompt_tokens' => isset($latestHit['prompt_tokens']) ? $latestHit['prompt_tokens'] : '',
            'completion_tokens' => isset($latestHit['completion_tokens']) ? $latestHit['completion_tokens'] : '',
            'total_tokens' => isset($latestHit['total_tokens']) ? $latestHit['total_tokens'] : '',
        );
        mrd_llm_cache_ok_with_variants($slug, $message, array(
            'reply' => $latestHit['reply'],
            'source' => 'llm',
            'degraded' => false,
            'from_cache' => true,
            'provider' => $pubProvider,
            'provider_name' => isset($latestHit['provider_name']) ? (string)$latestHit['provider_name'] : $pubProvider,
            'model' => isset($latestHit['model']) ? (string)$latestHit['model'] : '',
            'role' => $slug,
            'endpoint' => 'chat-llm.php',
            'debug' => $trace,
        ), $session);
    }
}

$llm = mrd_llm_chat($system, $message, $history, $cfg, $providerId);
if (!empty($llm['ok']) && !empty($llm['reply'])) {
    $detail = isset($llm['detail']) && is_array($llm['detail']) ? $llm['detail'] : array();
    $cacheMeta = array_merge($detail, array(
        'provider' => isset($llm['provider']) ? $llm['provider'] : $providerId,
        'provider_name' => isset($llm['provider_name']) ? $llm['provider_name'] : '',
        'model' => isset($llm['model']) ? $llm['model'] : '',
        'prompt_from' => $promptFrom,
        'disclaimer_from' => $disclaimerFrom,
        'history_turns' => is_array($history) ? count($history) : 0,
    ));
    mrd_llm_cache_write(
        $slug,
        $message,
        isset($llm['provider']) ? $llm['provider'] : $providerId,
        $llm['reply'],
        $cacheMeta
    );

    $fake = array(
        'matched' => true,
        'matched_topic' => '',
        'quote_id' => '',
        'score' => 0,
        'keys' => array(),
        'risk' => false,
        'source' => 'llm',
    );
    $trace = mrd_build_reply_trace($slug, $fake, 'llm');
    $trace['llm'] = array(
        'ok' => true,
        'from_cache' => false,
        'prompt_from' => $promptFrom,
        'disclaimer_from' => $disclaimerFrom,
        'provider' => isset($llm['provider']) ? $llm['provider'] : $providerId,
        'provider_name' => isset($llm['provider_name']) ? $llm['provider_name'] : '',
        'model' => isset($llm['model']) ? $llm['model'] : '',
        'llm_id' => isset($detail['llm_id']) ? $detail['llm_id'] : '',
        'finish_reason' => isset($detail['finish_reason']) ? $detail['finish_reason'] : '',
        'prompt_tokens' => isset($detail['prompt_tokens']) ? $detail['prompt_tokens'] : '',
        'completion_tokens' => isset($detail['completion_tokens']) ? $detail['completion_tokens'] : '',
        'total_tokens' => isset($detail['total_tokens']) ? $detail['total_tokens'] : '',
        'latency_ms' => isset($detail['latency_ms']) ? $detail['latency_ms'] : '',
    );
    mrd_llm_cache_ok_with_variants($slug, $message, array(
        'reply' => $llm['reply'],
        'source' => 'llm',
        'degraded' => false,
        'from_cache' => false,
        'provider' => isset($llm['provider']) ? $llm['provider'] : $providerId,
        'provider_name' => isset($llm['provider_name']) ? $llm['provider_name'] : '',
        'model' => isset($llm['model']) ? $llm['model'] : '',
        'role' => $slug,
        'endpoint' => 'chat-llm.php',
        'debug' => $trace,
    ), $session);
}

$result = mrd_offline_reply($slug, $message, $session);
$trace = isset($result['trace']) ? $result['trace'] : mrd_build_reply_trace($slug, $result, $result['source'] === 'quotes' ? 'quotes' : 'qa_md');
$trace['llm'] = array(
    'ok' => false,
    'error' => isset($llm['error']) ? $llm['error'] : 'unknown',
    'provider' => isset($llm['provider']) ? $llm['provider'] : $providerId,
    'prompt_from' => $promptFrom,
    'disclaimer_from' => $disclaimerFrom,
    'fallback_to' => $result['source'],
);

// 降级时仍附带：默认答案 + 已有模型缓存，便于对比
$listed = mrd_llm_cache_list_variants($slug, $message);
$failedProvider = isset($llm['provider']) ? (string)$llm['provider'] : $providerId;
$variants = mrd_llm_cache_prepend_default_variant($slug, $message, $listed['variants']);
// 若本次降级答案与 peek 默认不同，用本次会话结果覆盖 default 条目
$offlineReply = isset($result['reply']) ? trim((string)$result['reply']) : '';
if ($offlineReply !== '') {
    $src = isset($result['source']) ? (string)$result['source'] : 'match';
    $label = ($src === 'quotes' || $src === 'mock') ? '默认语录' : '默认匹配';
    $replaced = false;
    foreach ($variants as $i => $v) {
        if (isset($v['provider']) && ($v['provider'] === 'default' || $v['provider'] === 'offline')) {
            $variants[$i]['reply'] = $offlineReply;
            $variants[$i]['provider'] = 'default';
            $variants[$i]['provider_name'] = $label;
            $variants[$i]['source'] = $src;
            $variants[$i]['from_cache'] = false;
            $variants[$i]['is_default'] = true;
            $replaced = true;
            break;
        }
    }
    if (!$replaced) {
        array_unshift($variants, array(
            'provider' => 'default',
            'provider_name' => $label,
            'model' => '',
            'reply' => $offlineReply,
            'source' => $src,
            'from_cache' => false,
            'is_default' => true,
        ));
    }
}
mrd_ok(array(
    'reply' => $result['reply'],
    'source' => $result['source'],
    'degraded' => true,
    'from_cache' => false,
    'provider' => 'default',
    'provider_name' => '默认匹配',
    'requested_provider' => $failedProvider,
    'matched' => !empty($result['matched']),
    'matched_topic' => isset($result['matched_topic']) ? $result['matched_topic'] : '',
    'quote_id' => isset($result['quote_id']) ? $result['quote_id'] : '',
    'risk' => !empty($result['risk']),
    'llm_error' => isset($llm['error']) ? $llm['error'] : 'unknown',
    'role' => $slug,
    'endpoint' => 'chat-llm.php',
    'question_hash' => $listed['question_hash'],
    'variants' => $variants,
    'variant_index' => 0,
    'debug' => $trace,
));
