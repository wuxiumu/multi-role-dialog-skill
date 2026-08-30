<?php
/**
 * LLM 回答文件缓存（角色内容资产）：按「问题」建目录，多模型为多版本。
 *
 * 目录：backend/roles/{role}/cache/{question_hash}/
 *   question.md          — 问题索引（便于项目管理浏览）
 *   {provider}.md        — 某一模型版本的回答
 *
 * 唯一键：角色目录下的 问题 + provider（与会话无关）。
 */

/** 某角色的 cache 根目录 */
function mrd_llm_cache_role_root($slug) {
    $slug = preg_replace('/[^a-z0-9\-]/', '', (string)$slug);
    return MRD_ROLES . '/' . $slug . '/cache';
}

function mrd_llm_cache_ensure_role($slug) {
    $root = mrd_llm_cache_role_root($slug);
    if (!is_dir($root)) {
        @mkdir($root, 0775, true);
    }
    return $root;
}

/**
 * 规范化问题文本（用于哈希 / 归并同类问法）。
 * 去掉引号、统一问号，避免「怎么理解“格局”？」与「怎么理解格局？」分成两个缓存。
 */
function mrd_llm_cache_normalize_question($message) {
    $s = trim((string)$message);
    if ($s === '') {
        return '';
    }
    // 空白
    $s = preg_replace('/\s+/u', ' ', $s);
    // 各类引号 / 书名号式包裹（保留正文）
    $s = preg_replace('/["\'"“”‘’「」『』‹›«»《》]/u', '', $s);
    // 统一问号、感叹号为全角（与中文提问习惯一致）
    $s = str_replace(array('?', '!', '！'), array('？', '？', '？'), $s);
    // 去掉首尾标点（含问号），让「怎么办」与「怎么办？」归为同一问题
    $s = preg_replace('/^[，,。．、：:；;？?！!]+|[，,。．、：:；;？?！!]+$/u', '', $s);
    $s = trim((string)$s);
    return $s;
}

function mrd_llm_cache_question_hash($message) {
    return substr(hash('sha256', mrd_llm_cache_normalize_question($message)), 0, 24);
}

/**
 * 解析问题对应的缓存目录 hash：
 * 优先新规范哈希；若目录不存在，再按规范化问题文案扫描已有 question.md（兼容旧缓存）。
 */
function mrd_llm_cache_resolve_hash($slug, $message) {
    $norm = mrd_llm_cache_normalize_question($message);
    $qh = substr(hash('sha256', $norm), 0, 24);
    $dir = mrd_llm_cache_dir($slug, $qh);
    if (is_dir($dir)) {
        return $qh;
    }

    $root = mrd_llm_cache_role_root($slug);
    if (!is_dir($root)) {
        return $qh;
    }
    foreach (glob($root . '/*/question.md') ?: array() as $path) {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            continue;
        }
        $parsed = mrd_parse_frontmatter($raw);
        $meta = isset($parsed['meta']) && is_array($parsed['meta']) ? $parsed['meta'] : array();
        $q = '';
        if (!empty($meta['question'])) {
            $q = mrd_llm_cache_normalize_question((string)$meta['question']);
        } elseif (!empty($parsed['body'])) {
            $first = trim(strtok((string)$parsed['body'], "\n"));
            $q = mrd_llm_cache_normalize_question($first);
        }
        if ($q !== '' && $q === $norm) {
            return basename(dirname($path));
        }
    }
    return $qh;
}

function mrd_llm_cache_provider_safe($providerId) {
    $id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$providerId);
    return $id === '' ? 'unknown' : $id;
}

/** 一问一目录 */
function mrd_llm_cache_dir($slug, $questionHash) {
    $qh = preg_replace('/[^a-f0-9]/', '', (string)$questionHash);
    return mrd_llm_cache_role_root($slug) . '/' . $qh;
}

function mrd_llm_cache_path($slug, $questionHash, $providerId) {
    return mrd_llm_cache_dir($slug, $questionHash)
        . '/' . mrd_llm_cache_provider_safe($providerId) . '.md';
}

function mrd_llm_cache_question_index_path($slug, $questionHash) {
    return mrd_llm_cache_dir($slug, $questionHash) . '/question.md';
}

/**
 * 写入/更新问题目录索引，方便在仓库里按问题浏览。
 */
function mrd_llm_cache_touch_question_index($slug, $message, $questionHash, $extraMeta = array()) {
    $q = mrd_llm_cache_normalize_question($message);
    $dir = mrd_llm_cache_dir($slug, $questionHash);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }

    // 扫描已有版本
    $versions = array();
    foreach (glob($dir . '/*.md') ?: array() as $path) {
        $base = basename($path);
        if ($base === 'question.md') {
            continue;
        }
        $versions[] = basename($path, '.md');
    }
    sort($versions);

    $meta = array(
        'role' => (string)$slug,
        'question' => $q,
        'question_hash' => $questionHash,
        'versions' => $versions,
        'version_count' => count($versions),
        'updated_at' => gmdate('c'),
    );
    foreach ($extraMeta as $k => $v) {
        if ($v === null || $v === '' || isset($meta[$k])) {
            continue;
        }
        if (is_bool($v) || is_scalar($v)) {
            $meta[$k] = $v;
        }
    }

    $body = $q . "\n\n"
        . "> 本目录为「问题」缓存资产，与会话无关。\n"
        . "> 每个 `{provider}.md` 是该问题在对应模型下的一个版本。\n";
    if ($versions) {
        $body .= "\n当前版本：" . implode('、', $versions) . "\n";
    }

    return mrd_safe_write(mrd_llm_cache_question_index_path($slug, $questionHash), mrd_build_frontmatter($meta) . $body);
}

/**
 * 读取单条缓存（某一模型版本）。
 * @return array|null { meta, reply, path, question_hash, provider }
 */
function mrd_llm_cache_read($slug, $message, $providerId) {
    $qh = mrd_llm_cache_resolve_hash($slug, $message);
    $path = mrd_llm_cache_path($slug, $qh, $providerId);
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $parsed = mrd_parse_frontmatter($raw);
    $meta = isset($parsed['meta']) && is_array($parsed['meta']) ? $parsed['meta'] : array();
    $reply = trim((string)$parsed['body']);
    if ($reply === '') {
        return null;
    }
    return array(
        'meta' => $meta,
        'reply' => $reply,
        'path' => $path,
        'question_hash' => $qh,
        'provider' => mrd_llm_cache_provider_safe($providerId),
    );
}

/**
 * 写入某一模型版本；并维护 question.md 索引。
 * @param array $meta 扁平标量/数组字段（写入 frontmatter）
 * @return bool
 */
function mrd_llm_cache_write($slug, $message, $providerId, $reply, $meta = array()) {
    $reply = trim((string)$reply);
    if ($reply === '') {
        return false;
    }
    if (!mrd_role_exists($slug)) {
        return false;
    }
    mrd_llm_cache_ensure_role($slug);
    $qh = mrd_llm_cache_resolve_hash($slug, $message);
    $dir = mrd_llm_cache_dir($slug, $qh);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }

    $base = array(
        'role' => (string)$slug,
        'question' => mrd_llm_cache_normalize_question($message),
        'question_hash' => $qh,
        'provider' => mrd_llm_cache_provider_safe($providerId),
        'cached_at' => gmdate('c'),
        'source' => 'llm',
    );
    foreach ($meta as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        if (is_bool($v) || is_scalar($v)) {
            $base[$k] = $v;
        } elseif (is_array($v)) {
            $flat = true;
            $parts = array();
            foreach ($v as $sk => $sv) {
                if (!is_scalar($sv) && !is_bool($sv)) {
                    $flat = false;
                    break;
                }
                $parts[] = $sk . '=' . (is_bool($sv) ? ($sv ? 'true' : 'false') : (string)$sv);
            }
            if ($flat && $parts) {
                $base[$k] = implode('; ', $parts);
            } else {
                $enc = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($enc !== false) {
                    $base[$k] = $enc;
                }
            }
        }
    }

    $content = mrd_build_frontmatter($base) . $reply . "\n";
    $path = mrd_llm_cache_path($slug, $qh, $providerId);
    if (!mrd_safe_write($path, $content)) {
        return false;
    }
    mrd_llm_cache_touch_question_index($slug, $message, $qh);
    return true;
}

/**
 * 列出同一问题下各模型版本（会话内切换答案）。
 * @return array{question_hash:string, variants:array}
 */
function mrd_llm_cache_list_variants($slug, $message) {
    $qh = mrd_llm_cache_resolve_hash($slug, $message);
    $dir = mrd_llm_cache_dir($slug, $qh);
    $variants = array();
    if (!is_dir($dir)) {
        return array('question_hash' => $qh, 'variants' => $variants);
    }
    $files = glob($dir . '/*.md') ?: array();
    sort($files);
    foreach ($files as $path) {
        if (basename($path) === 'question.md') {
            continue;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            continue;
        }
        $parsed = mrd_parse_frontmatter($raw);
        $meta = isset($parsed['meta']) && is_array($parsed['meta']) ? $parsed['meta'] : array();
        $reply = trim((string)$parsed['body']);
        if ($reply === '') {
            continue;
        }
        $provider = isset($meta['provider'])
            ? (string)$meta['provider']
            : basename($path, '.md');
        $variants[] = array(
            'provider' => $provider,
            'provider_name' => isset($meta['provider_name']) ? (string)$meta['provider_name'] : $provider,
            'model' => isset($meta['model']) ? (string)$meta['model'] : '',
            'reply' => $reply,
            'cached_at' => isset($meta['cached_at']) ? (string)$meta['cached_at'] : '',
            'llm_id' => isset($meta['llm_id']) ? (string)$meta['llm_id'] : '',
            'finish_reason' => isset($meta['finish_reason']) ? (string)$meta['finish_reason'] : '',
            'prompt_tokens' => isset($meta['prompt_tokens']) ? $meta['prompt_tokens'] : '',
            'completion_tokens' => isset($meta['completion_tokens']) ? $meta['completion_tokens'] : '',
            'total_tokens' => isset($meta['total_tokens']) ? $meta['total_tokens'] : '',
            'from_cache' => true,
        );
    }
    return array('question_hash' => $qh, 'variants' => $variants);
}

/**
 * 从缓存条目组装对外 meta（供 debug / 响应）。
 */
function mrd_llm_cache_public_meta($hit) {
    if (!$hit || !is_array($hit)) {
        return array();
    }
    $m = isset($hit['meta']) ? $hit['meta'] : array();
    return array(
        'provider' => isset($m['provider']) ? (string)$m['provider'] : (isset($hit['provider']) ? $hit['provider'] : ''),
        'provider_name' => isset($m['provider_name']) ? (string)$m['provider_name'] : '',
        'model' => isset($m['model']) ? (string)$m['model'] : '',
        'cached_at' => isset($m['cached_at']) ? (string)$m['cached_at'] : '',
        'llm_id' => isset($m['llm_id']) ? (string)$m['llm_id'] : '',
        'finish_reason' => isset($m['finish_reason']) ? (string)$m['finish_reason'] : '',
        'prompt_tokens' => isset($m['prompt_tokens']) ? $m['prompt_tokens'] : null,
        'completion_tokens' => isset($m['completion_tokens']) ? $m['completion_tokens'] : null,
        'total_tokens' => isset($m['total_tokens']) ? $m['total_tokens'] : null,
        'question_hash' => isset($hit['question_hash']) ? $hit['question_hash'] : '',
    );
}

/**
 * 在模型缓存变体中选出 cached_at 最新的一条（排除 default/offline）。
 * @return array|null 带 index 字段的变体，或 null
 */
function mrd_llm_cache_latest_model_variant($variants) {
    if (!is_array($variants) || count($variants) === 0) {
        return null;
    }
    $best = null;
    $bestTs = -1;
    $bestIdx = -1;
    foreach ($variants as $i => $v) {
        if (!is_array($v)) {
            continue;
        }
        $provider = isset($v['provider']) ? (string)$v['provider'] : '';
        if ($provider === 'default' || $provider === 'offline' || !empty($v['is_default'])) {
            continue;
        }
        $reply = isset($v['reply']) ? trim((string)$v['reply']) : '';
        if ($reply === '') {
            continue;
        }
        $raw = isset($v['cached_at']) ? (string)$v['cached_at'] : '';
        $ts = $raw !== '' ? strtotime($raw) : false;
        if ($ts === false) {
            $ts = 0;
        }
        // 同时间戳时取更靠后的（文件排序后较新写入优先）
        if ($ts > $bestTs || ($ts === $bestTs && $i >= $bestIdx)) {
            $bestTs = $ts;
            $bestIdx = $i;
            $best = $v;
            $best['_index'] = $i;
        }
    }
    return $best;
}

/**
 * 在模型缓存变体前插入「默认语录/匹配」，便于对比。
 * 使用独立 session `variant_compare`，避免污染用户会话语录去重。
 *
 * @param array $variants mrd_llm_cache_list_variants 的 variants
 * @return array
 */
function mrd_llm_cache_prepend_default_variant($slug, $message, $variants) {
    if (!is_array($variants)) {
        $variants = array();
    }
    $offline = mrd_offline_reply($slug, $message, 'variant_compare');
    $defaultReply = isset($offline['reply']) ? trim((string)$offline['reply']) : '';
    if ($defaultReply === '') {
        return $variants;
    }
    $src = isset($offline['source']) ? (string)$offline['source'] : 'match';
    $label = ($src === 'quotes' || $src === 'mock') ? '默认语录' : '默认匹配';
    $defaultVar = array(
        'provider' => 'default',
        'provider_name' => $label,
        'model' => '',
        'reply' => $defaultReply,
        'source' => $src,
        'matched_topic' => isset($offline['matched_topic']) ? $offline['matched_topic'] : '',
        'quote_id' => isset($offline['quote_id']) ? $offline['quote_id'] : '',
        'from_cache' => false,
        'is_default' => true,
    );
    $deduped = array();
    foreach ($variants as $v) {
        if (isset($v['reply']) && trim((string)$v['reply']) === $defaultReply) {
            continue;
        }
        if (isset($v['provider']) && ($v['provider'] === 'default' || $v['provider'] === 'offline')) {
            continue;
        }
        $deduped[] = $v;
    }
    return array_merge(array($defaultVar), $deduped);
}

/**
 * 组装对外 variants，并在有模型缓存时优先展示最新一条。
 *
 * @param array $payload 已有业务字段（reply/source/...）
 * @param array|null $sessionOffline 本次会话离线结果；用于覆盖 default 文案，避免与 peek 不一致
 * @return array 补全 question_hash / variants / variant_index，并可能改写 reply/provider 为最新缓存
 */
function mrd_llm_cache_attach_variants($slug, $message, $payload, $sessionOffline = null) {
    $listed = mrd_llm_cache_list_variants($slug, $message);
    $payload['question_hash'] = $listed['question_hash'];
    $variants = mrd_llm_cache_prepend_default_variant($slug, $message, $listed['variants']);

    // 用本次会话离线答案覆盖 default，避免「none」与「默认语录」两套随机文案
    if (is_array($sessionOffline)) {
        $offlineReply = isset($sessionOffline['reply']) ? trim((string)$sessionOffline['reply']) : '';
        if ($offlineReply !== '') {
            $src = isset($sessionOffline['source']) ? (string)$sessionOffline['source'] : 'match';
            $label = ($src === 'quotes' || $src === 'mock') ? '默认语录' : '默认匹配';
            $replaced = false;
            foreach ($variants as $i => $v) {
                if (isset($v['provider']) && ($v['provider'] === 'default' || $v['provider'] === 'offline' || !empty($v['is_default']))) {
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
    }

    $payload['variants'] = $variants;

    // 有模型缓存 → 默认展示最新缓存（用户可再切回默认语录）
    $latest = mrd_llm_cache_latest_model_variant($variants);
    if ($latest !== null && isset($latest['_index'])) {
        $idx = (int)$latest['_index'];
        $payload['variant_index'] = $idx;
        $payload['reply'] = $latest['reply'];
        $payload['provider'] = isset($latest['provider']) ? (string)$latest['provider'] : '';
        $payload['provider_name'] = isset($latest['provider_name']) ? (string)$latest['provider_name'] : $payload['provider'];
        $payload['model'] = isset($latest['model']) ? (string)$latest['model'] : '';
        $payload['from_cache'] = true;
        if (!isset($payload['source']) || $payload['source'] === '' || $payload['source'] === 'match' || $payload['source'] === 'quotes' || $payload['source'] === 'mock') {
            $payload['source'] = 'llm';
        }
        return $payload;
    }

    // 无模型缓存：展示 default
    $idx = 0;
    foreach ($variants as $i => $v) {
        if (!empty($v['is_default']) || (isset($v['provider']) && $v['provider'] === 'default')) {
            $idx = $i;
            break;
        }
    }
    $payload['variant_index'] = $idx;
    if (isset($variants[$idx])) {
        $payload['provider'] = 'default';
        $payload['provider_name'] = isset($variants[$idx]['provider_name']) ? $variants[$idx]['provider_name'] : '默认匹配';
        $payload['model'] = '';
        $payload['from_cache'] = false;
    }
    return $payload;
}

/**
 * 成功响应并附带 variants 后退出。
 * 变体含：默认匹配（语录/qa）+ 各模型缓存，便于命中缓存时对比查看。
 *
 * @param array $payload 业务字段（会补 question_hash / variants / variant_index）
 * @param string $session 保留兼容，离线对比已改用 variant_compare
 */
function mrd_llm_cache_ok_with_variants($slug, $message, $payload, $session = 'anon') {
    $fromCache = !empty($payload['from_cache']);
    $listed = mrd_llm_cache_list_variants($slug, $message);
    $payload['question_hash'] = $listed['question_hash'];
    $payload['variants'] = mrd_llm_cache_prepend_default_variant($slug, $message, $listed['variants']);

    $idx = -1;

    // 有模型缓存时优先展示最新；新鲜 LLM 回答则对齐当前 provider
    if ($fromCache) {
        $latest = mrd_llm_cache_latest_model_variant($payload['variants']);
        if ($latest !== null && isset($latest['_index'])) {
            $idx = (int)$latest['_index'];
            $payload['reply'] = $latest['reply'];
            $payload['provider'] = isset($latest['provider']) ? (string)$latest['provider'] : '';
            $payload['provider_name'] = isset($latest['provider_name']) ? (string)$latest['provider_name'] : $payload['provider'];
            $payload['model'] = isset($latest['model']) ? (string)$latest['model'] : '';
            if (isset($payload['debug']) && is_array($payload['debug']) && isset($payload['debug']['llm']) && is_array($payload['debug']['llm'])) {
                $payload['debug']['llm']['provider'] = $payload['provider'];
                $payload['debug']['llm']['provider_name'] = $payload['provider_name'];
                $payload['debug']['llm']['model'] = $payload['model'];
                if (!empty($latest['cached_at'])) {
                    $payload['debug']['llm']['cached_at'] = $latest['cached_at'];
                }
            }
        }
    }

    if ($idx < 0) {
        $active = isset($payload['provider']) ? (string)$payload['provider'] : '';
        $idx = 0;
        // 新鲜回答：对齐当前 provider；若无匹配再退回最新缓存
        foreach ($payload['variants'] as $i => $v) {
            if (isset($v['provider']) && $v['provider'] === $active) {
                $idx = $i;
                break;
            }
        }
        if ($active === '' || $active === 'none' || $active === 'default') {
            $latest = mrd_llm_cache_latest_model_variant($payload['variants']);
            if ($latest !== null && isset($latest['_index'])) {
                $idx = (int)$latest['_index'];
                $payload['reply'] = $latest['reply'];
                $payload['provider'] = isset($latest['provider']) ? (string)$latest['provider'] : '';
                $payload['provider_name'] = isset($latest['provider_name']) ? (string)$latest['provider_name'] : $payload['provider'];
                $payload['model'] = isset($latest['model']) ? (string)$latest['model'] : '';
                $payload['from_cache'] = true;
            }
        }
    }

    $payload['variant_index'] = $idx;
    mrd_ok($payload);
}
