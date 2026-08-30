<?php
/**
 * GET/POST /api/chat-cache-variants.php
 * 查询同一「角色 + 问题」下各模型缓存答案，供会话内切换。
 * Body/Query: { role, message } 或 { role, question_hash }
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    mrd_fail('仅支持 GET/POST', 405, 405);
}

$body = $method === 'POST' ? mrd_body() : $_GET;
$slug = isset($body['role']) ? trim((string)$body['role']) : '';
$message = isset($body['message']) ? trim((string)$body['message']) : '';
$qh = isset($body['question_hash']) ? preg_replace('/[^a-f0-9]/', '', (string)$body['question_hash']) : '';

if ($slug === '' || !mrd_slug_ok($slug)) {
    mrd_fail('role 参数无效');
}
if (!mrd_role_exists($slug)) {
    mrd_fail('角色不存在', 404);
}

if ($message !== '') {
    $listed = mrd_llm_cache_list_variants($slug, $message);
    $variants = mrd_llm_cache_prepend_default_variant($slug, $message, $listed['variants']);
    mrd_ok(array(
        'role' => $slug,
        'question' => mrd_llm_cache_normalize_question($message),
        'question_hash' => $listed['question_hash'],
        'variants' => $variants,
        'total' => count($variants),
    ));
}

if ($qh === '') {
    mrd_fail('需要 message 或 question_hash');
}

// 仅有 hash：直接扫目录
$dir = mrd_llm_cache_dir($slug, $qh);
$variants = array();
if (is_dir($dir)) {
    // 用空 message 无法复用 list；手动扫
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
        $provider = isset($meta['provider']) ? (string)$meta['provider'] : basename($path, '.md');
        $variants[] = array(
            'provider' => $provider,
            'provider_name' => isset($meta['provider_name']) ? (string)$meta['provider_name'] : $provider,
            'model' => isset($meta['model']) ? (string)$meta['model'] : '',
            'reply' => $reply,
            'cached_at' => isset($meta['cached_at']) ? (string)$meta['cached_at'] : '',
            'from_cache' => true,
        );
    }
}

mrd_ok(array(
    'role' => $slug,
    'question_hash' => $qh,
    'variants' => $variants,
    'total' => count($variants),
));
