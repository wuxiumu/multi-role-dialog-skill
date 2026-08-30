<?php
/**
 * GET /api/persona.php?role=yingxue
 * 返回角色 persona.json（人格卡 / 快捷提问）；无则 404。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    mrd_fail('仅支持 GET', 405, 405);
}

$slug = isset($_GET['role']) ? trim((string)$_GET['role']) : '';
if ($slug === '' || !mrd_slug_ok($slug)) {
    mrd_fail('role 参数无效');
}
if (!mrd_role_exists($slug)) {
    mrd_fail('角色不存在', 404);
}

$persona = mrd_load_role_json($slug, 'persona.json');
$assets = mrd_role_asset_stats($slug);

if ($persona === null) {
    $role = mrd_load_role($slug);
    mrd_ok(array(
        'name' => $role['name'],
        'alias' => '',
        'tagline' => $role['tagline'],
        'system_prompt' => mrd_load_prompt($slug),
        'style_rules' => array(),
        'catchphrases' => array(),
        'guardrails' => array(),
        'disclaimer_text' => '',
        'quick_questions' => array(),
        'quick_pool' => array(),
        'mode' => 'match',
        'has_persona_file' => false,
        'assets' => $assets,
    ));
}

$persona['has_persona_file'] = true;
$persona['assets'] = $assets;
mrd_ok($persona);
