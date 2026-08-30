<?php
/**
 * POST /api/llm-key-test.php
 * Header: Authorization: Bearer <token>
 *
 * Body（系统模型）:
 *   { mode: "system", provider: "deepseek"|"qwen"|..., prompt? }
 *
 * Body（手动输入）:
 *   { mode: "manual", base_url, api_key, model, prompt? }
 *
 * 用途：验证系统配置或自备 Key 能否打通 OpenAI 兼容 chat/completions。
 * 注意：不会把完整 api_key 写回响应；也不会写入角色 cache。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mrd_fail('仅支持 POST', 405, 405);
}

mrd_require_user();

$body = mrd_body();
$mode = isset($body['mode']) ? trim((string)$body['mode']) : 'system';
$prompt = isset($body['prompt']) ? trim((string)$body['prompt']) : '';
if ($prompt === '') {
    $prompt = '请只回复两个字：通过';
}

$system = '你是连通性探测助手。用户在验证 API Key，请用尽量短的中文回复。';
$cfg = mrd_config();
$timeout = isset($cfg['llm_timeout']) ? (int)$cfg['llm_timeout'] : 20;

if ($mode === 'system') {
    $providerId = isset($body['provider']) ? trim((string)$body['provider']) : '';
    if ($providerId === '' || $providerId === 'none') {
        mrd_fail('请选择系统模型 provider');
    }
    $provider = mrd_llm_resolve_provider($cfg, $providerId);
    if ($provider === null) {
        mrd_fail('系统模型不可用或未配置完整：' . $providerId);
    }

    $result = mrd_llm_raw_chat(
        $provider['endpoint'],
        $provider['api_key'],
        $provider['model'],
        $system,
        $prompt,
        $timeout
    );

    mrd_ok(array(
        'mode' => 'system',
        'ok' => !empty($result['ok']),
        'provider' => $provider['id'],
        'provider_name' => $provider['name'],
        'model' => isset($result['model']) ? $result['model'] : $provider['model'],
        'api_key_masked' => mrd_llm_mask_key($provider['api_key']),
        'endpoint_host' => isset($result['detail']['endpoint_host'])
            ? $result['detail']['endpoint_host']
            : (string)parse_url($provider['endpoint'], PHP_URL_HOST),
        'reply' => isset($result['reply']) ? $result['reply'] : '',
        'error' => isset($result['error']) ? $result['error'] : '',
        'detail' => isset($result['detail']) ? $result['detail'] : array(),
        'tested_at' => gmdate('c'),
    ));
}

if ($mode === 'manual') {
    $baseUrl = isset($body['base_url']) ? trim((string)$body['base_url']) : '';
    $apiKey = isset($body['api_key']) ? trim((string)$body['api_key']) : '';
    $model = isset($body['model']) ? trim((string)$body['model']) : '';

    if ($baseUrl === '') {
        mrd_fail('请填写 base_url（OpenAI 兼容地址）');
    }
    if ($apiKey === '') {
        mrd_fail('请填写 api_key');
    }
    if ($model === '') {
        mrd_fail('请填写 model');
    }
    if (strlen($apiKey) > 512) {
        mrd_fail('api_key 过长');
    }
    if (!preg_match('#^https?://#i', $baseUrl)) {
        mrd_fail('base_url 需以 http:// 或 https:// 开头');
    }

    $result = mrd_llm_raw_chat($baseUrl, $apiKey, $model, $system, $prompt, $timeout);

    mrd_ok(array(
        'mode' => 'manual',
        'ok' => !empty($result['ok']),
        'provider' => 'manual',
        'provider_name' => '手动输入',
        'model' => isset($result['model']) ? $result['model'] : $model,
        'api_key_masked' => mrd_llm_mask_key($apiKey),
        'endpoint_host' => isset($result['detail']['endpoint_host'])
            ? $result['detail']['endpoint_host']
            : (string)parse_url(mrd_llm_endpoint_url($baseUrl), PHP_URL_HOST),
        'reply' => isset($result['reply']) ? $result['reply'] : '',
        'error' => isset($result['error']) ? $result['error'] : '',
        'detail' => isset($result['detail']) ? $result['detail'] : array(),
        'tested_at' => gmdate('c'),
    ));
}

mrd_fail('mode 仅支持 system 或 manual');
