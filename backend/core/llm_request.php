<?php
/**
 * 大模型通用请求（OpenAI Chat Completions 兼容协议）。
 * 支持多供应商切换：deepseek / qwen / 旧版单配置；provider=none 表示不用模型。
 */

/**
 * 规范化 base_url → chat completions 完整 URL。
 */
function mrd_llm_endpoint_url($baseUrl) {
    $base = rtrim(trim((string)$baseUrl), '/');
    if ($base === '') {
        return '';
    }
    if (preg_match('#/chat/completions$#', $base)) {
        return $base;
    }
    return $base . '/chat/completions';
}

/**
 * 合并后的供应商表（含是否可用）。
 * @return array<string,array>
 */
function mrd_llm_providers_map($cfg = null) {
    if ($cfg === null) {
        $cfg = mrd_config();
    }
    $out = array();
    $providers = isset($cfg['llm_providers']) && is_array($cfg['llm_providers'])
        ? $cfg['llm_providers'] : array();

    foreach ($providers as $id => $p) {
        if (!is_array($p)) {
            continue;
        }
        $id = (string)$id;
        $key = isset($p['api_key']) ? trim((string)$p['api_key']) : '';
        if ($key === '' && isset($p['key'])) {
            $key = trim((string)$p['key']);
        }
        $base = isset($p['base_url']) ? trim((string)$p['base_url']) : '';
        $model = isset($p['model']) ? trim((string)$p['model']) : '';
        $enabled = !isset($p['enabled']) || !empty($p['enabled']);
        $ready = $enabled && $key !== '' && $base !== '' && $model !== '';
        $out[$id] = array(
            'id' => $id,
            'name' => isset($p['name']) ? (string)$p['name'] : $id,
            'enabled' => $enabled,
            'ready' => $ready,
            'api_key' => $key,
            'base_url' => $base,
            'model' => $model,
            'api_host' => isset($p['api_host']) ? (string)$p['api_host'] : '',
        );
    }

    // 兼容旧单配置
    if (empty($out) && !empty($cfg['llm_key']) && !empty($cfg['llm_endpoint'])) {
        $out['legacy'] = array(
            'id' => 'legacy',
            'name' => '默认模型',
            'enabled' => true,
            'ready' => true,
            'api_key' => trim((string)$cfg['llm_key']),
            'base_url' => '',
            'model' => isset($cfg['llm_model']) ? trim((string)$cfg['llm_model']) : '',
            'endpoint' => trim((string)$cfg['llm_endpoint']),
            'api_host' => '',
        );
    }

    return $out;
}

/** 前台公开列表（不含密钥） */
function mrd_llm_providers_public($cfg = null) {
    $map = mrd_llm_providers_map($cfg);
    $list = array(
        array(
            'id' => 'none',
            'name' => '不使用模型',
            'ready' => true,
            'desc' => '仅关键词 / 语录匹配，不消耗 API',
        ),
    );
    foreach ($map as $p) {
        if (empty($p['enabled'])) {
            continue;
        }
        $list[] = array(
            'id' => $p['id'],
            'name' => $p['name'],
            'ready' => !empty($p['ready']),
            'model' => $p['model'],
            'desc' => !empty($p['ready']) ? ('模型 ' . $p['model']) : '未配置密钥或地址',
        );
    }
    return $list;
}

/**
 * 解析用户选择的供应商。
 * @return array|null null 表示 none / 不可用
 */
function mrd_llm_resolve_provider($cfg, $providerId) {
    $providerId = trim((string)$providerId);
    if ($providerId === '' || $providerId === 'none' || $providerId === 'off') {
        return null;
    }
    $map = mrd_llm_providers_map($cfg);
    if (!isset($map[$providerId]) || empty($map[$providerId]['ready'])) {
        return null;
    }
    $p = $map[$providerId];
    $endpoint = isset($p['endpoint']) && $p['endpoint'] !== ''
        ? $p['endpoint']
        : mrd_llm_endpoint_url($p['base_url']);
    return array(
        'id' => $p['id'],
        'name' => $p['name'],
        'endpoint' => $endpoint,
        'api_key' => $p['api_key'],
        'model' => $p['model'],
    );
}

/**
 * @param string      $systemPrompt
 * @param string      $message
 * @param array       $history
 * @param array       $cfg
 * @param string|null $providerId
 * @return array{ok:bool, reply?:string, error?:string, provider?:string, model?:string, detail?:array}
 */
function mrd_llm_chat($systemPrompt, $message, $history, $cfg, $providerId = null) {
    if (empty($cfg['llm_enabled'])) {
        return array('ok' => false, 'error' => 'LLM 已关闭');
    }

    if ($providerId === null || $providerId === '') {
        $providerId = isset($cfg['llm_default_provider']) ? $cfg['llm_default_provider'] : 'none';
    }

    $provider = mrd_llm_resolve_provider($cfg, $providerId);
    if ($provider === null) {
        return array('ok' => false, 'error' => '未选用模型或模型不可用', 'provider' => (string)$providerId);
    }

    $endpoint = $provider['endpoint'];
    $key = $provider['api_key'];
    $model = $provider['model'];
    if ($endpoint === '' || $key === '' || $model === '') {
        return array('ok' => false, 'error' => '大模型未配置完整', 'provider' => $provider['id']);
    }

    $maxHist = isset($cfg['llm_max_history']) ? (int)$cfg['llm_max_history'] : 10;
    $temperature = 0.7;
    $messages = array();
    if ($systemPrompt !== '') {
        $messages[] = array('role' => 'system', 'content' => $systemPrompt);
    }
    if (is_array($history)) {
        foreach (array_slice($history, -$maxHist) as $h) {
            if (!isset($h['role'], $h['content'])) {
                continue;
            }
            $role = $h['role'] === 'assistant' ? 'assistant' : 'user';
            $messages[] = array('role' => $role, 'content' => (string)$h['content']);
        }
    }
    $messages[] = array('role' => 'user', 'content' => (string)$message);

    $payload = json_encode(array(
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
    ), JSON_UNESCAPED_UNICODE);

    $timeout = isset($cfg['llm_timeout']) ? (int)$cfg['llm_timeout'] : 25;

    if (!function_exists('curl_init')) {
        return array('ok' => false, 'error' => 'PHP curl 扩展不可用');
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
    ));
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    if ($resp === false) {
        return array('ok' => false, 'error' => '上游请求失败：' . $err, 'provider' => $provider['id']);
    }
    $j = json_decode($resp, true);
    if (!is_array($j)) {
        return array(
            'ok' => false,
            'error' => '上游返回非 JSON（HTTP ' . $code . '）',
            'provider' => $provider['id'],
        );
    }

    $reply = '';
    $finishReason = '';
    if (isset($j['choices'][0]['message']['content'])) {
        $content = $j['choices'][0]['message']['content'];
        if (is_array($content)) {
            // 部分 VL 接口 content 为数组
            $parts = array();
            foreach ($content as $c) {
                if (is_string($c)) {
                    $parts[] = $c;
                } elseif (is_array($c) && isset($c['text'])) {
                    $parts[] = (string)$c['text'];
                }
            }
            $reply = implode('', $parts);
        } else {
            $reply = (string)$content;
        }
        if (isset($j['choices'][0]['finish_reason'])) {
            $finishReason = (string)$j['choices'][0]['finish_reason'];
        }
    } elseif (isset($j['output_text'])) {
        $reply = (string)$j['output_text'];
    } elseif (isset($j['error']['message'])) {
        return array(
            'ok' => false,
            'error' => (string)$j['error']['message'],
            'provider' => $provider['id'],
        );
    }

    if ($reply === '') {
        return array('ok' => false, 'error' => '上游无有效回复', 'provider' => $provider['id']);
    }

    $respModel = isset($j['model']) ? (string)$j['model'] : $model;
    $usage = isset($j['usage']) && is_array($j['usage']) ? $j['usage'] : array();
    $detail = array(
        'llm_id' => isset($j['id']) ? (string)$j['id'] : '',
        'object' => isset($j['object']) ? (string)$j['object'] : '',
        'created' => isset($j['created']) ? $j['created'] : '',
        'model' => $respModel,
        'finish_reason' => $finishReason,
        'prompt_tokens' => isset($usage['prompt_tokens']) ? $usage['prompt_tokens'] : '',
        'completion_tokens' => isset($usage['completion_tokens']) ? $usage['completion_tokens'] : '',
        'total_tokens' => isset($usage['total_tokens']) ? $usage['total_tokens'] : '',
        'system_fingerprint' => isset($j['system_fingerprint']) ? (string)$j['system_fingerprint'] : '',
        'http_code' => $code,
        'latency_ms' => (int)round($totalTime * 1000),
        'temperature' => $temperature,
        'endpoint_host' => (string)parse_url($endpoint, PHP_URL_HOST),
    );

    return array(
        'ok' => true,
        'reply' => $reply,
        'provider' => $provider['id'],
        'model' => $respModel,
        'provider_name' => $provider['name'],
        'detail' => $detail,
    );
}

/**
 * 用显式 endpoint / api_key / model 发一次探测请求（不读系统供应商表）。
 * @return array{ok:bool, reply?:string, error?:string, model?:string, detail?:array}
 */
function mrd_llm_raw_chat($endpoint, $apiKey, $model, $systemPrompt, $message, $timeout = 20) {
    $endpoint = trim((string)$endpoint);
    $apiKey = trim((string)$apiKey);
    $model = trim((string)$model);
    if ($endpoint === '' || $apiKey === '' || $model === '') {
        return array('ok' => false, 'error' => 'endpoint / api_key / model 不能为空');
    }
    // 允许只填 base_url
    $endpoint = mrd_llm_endpoint_url($endpoint);

    if (!function_exists('curl_init')) {
        return array('ok' => false, 'error' => 'PHP curl 扩展不可用');
    }

    $messages = array();
    if ($systemPrompt !== '') {
        $messages[] = array('role' => 'system', 'content' => (string)$systemPrompt);
    }
    $messages[] = array('role' => 'user', 'content' => (string)$message);

    $payload = json_encode(array(
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0,
        'max_tokens' => 64,
    ), JSON_UNESCAPED_UNICODE);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ),
        CURLOPT_TIMEOUT => max(5, (int)$timeout),
        CURLOPT_CONNECTTIMEOUT => 8,
    ));
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    if ($resp === false) {
        return array(
            'ok' => false,
            'error' => '上游请求失败：' . $err,
            'detail' => array(
                'http_code' => $code,
                'latency_ms' => (int)round($totalTime * 1000),
                'endpoint_host' => (string)parse_url($endpoint, PHP_URL_HOST),
            ),
        );
    }

    $j = json_decode($resp, true);
    if (!is_array($j)) {
        $snippet = substr(preg_replace('/\s+/', ' ', (string)$resp), 0, 180);
        return array(
            'ok' => false,
            'error' => '上游返回非 JSON（HTTP ' . $code . '）' . ($snippet !== '' ? '：' . $snippet : ''),
            'detail' => array(
                'http_code' => $code,
                'latency_ms' => (int)round($totalTime * 1000),
                'endpoint_host' => (string)parse_url($endpoint, PHP_URL_HOST),
            ),
        );
    }

    if (isset($j['error']['message'])) {
        return array(
            'ok' => false,
            'error' => (string)$j['error']['message'],
            'detail' => array(
                'http_code' => $code,
                'latency_ms' => (int)round($totalTime * 1000),
                'endpoint_host' => (string)parse_url($endpoint, PHP_URL_HOST),
                'error_type' => isset($j['error']['type']) ? (string)$j['error']['type'] : '',
                'error_code' => isset($j['error']['code']) ? (string)$j['error']['code'] : '',
            ),
        );
    }

    $reply = '';
    if (isset($j['choices'][0]['message']['content'])) {
        $content = $j['choices'][0]['message']['content'];
        if (is_array($content)) {
            $parts = array();
            foreach ($content as $c) {
                if (is_string($c)) {
                    $parts[] = $c;
                } elseif (is_array($c) && isset($c['text'])) {
                    $parts[] = (string)$c['text'];
                }
            }
            $reply = implode('', $parts);
        } else {
            $reply = (string)$content;
        }
    } elseif (isset($j['output_text'])) {
        $reply = (string)$j['output_text'];
    }

    $usage = isset($j['usage']) && is_array($j['usage']) ? $j['usage'] : array();
    $detail = array(
        'llm_id' => isset($j['id']) ? (string)$j['id'] : '',
        'model' => isset($j['model']) ? (string)$j['model'] : $model,
        'finish_reason' => isset($j['choices'][0]['finish_reason']) ? (string)$j['choices'][0]['finish_reason'] : '',
        'prompt_tokens' => isset($usage['prompt_tokens']) ? $usage['prompt_tokens'] : '',
        'completion_tokens' => isset($usage['completion_tokens']) ? $usage['completion_tokens'] : '',
        'total_tokens' => isset($usage['total_tokens']) ? $usage['total_tokens'] : '',
        'http_code' => $code,
        'latency_ms' => (int)round($totalTime * 1000),
        'endpoint_host' => (string)parse_url($endpoint, PHP_URL_HOST),
    );

    if ($reply === '') {
        return array('ok' => false, 'error' => '上游无有效回复（HTTP ' . $code . '）', 'detail' => $detail);
    }

    return array(
        'ok' => true,
        'reply' => $reply,
        'model' => $detail['model'],
        'detail' => $detail,
    );
}

/** 掩码展示密钥（仅前后几位） */
function mrd_llm_mask_key($key) {
    $key = trim((string)$key);
    $len = strlen($key);
    if ($len <= 8) {
        return str_repeat('*', max(0, $len));
    }
    return substr($key, 0, 4) . str_repeat('*', min(24, $len - 8)) . substr($key, -4);
}
