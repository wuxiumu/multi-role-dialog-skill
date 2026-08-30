<?php
/**
 * GET /api/global-set.php
 * 返回前端可用的站点公开配置（不含密钥）。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    mrd_fail('仅支持 GET', 405, 405);
}

$cfg = mrd_config();
mrd_ok(array(
    'site_name' => isset($cfg['site_name']) ? $cfg['site_name'] : '角色场',
    'site_tagline' => isset($cfg['site_tagline']) ? $cfg['site_tagline'] : '',
    'disclaimer' => isset($cfg['disclaimer']) ? $cfg['disclaimer'] : '',
    'llm_enabled' => !empty($cfg['llm_enabled']),
    'llm_default_provider' => isset($cfg['llm_default_provider']) ? $cfg['llm_default_provider'] : 'none',
    'demo_open' => !empty($cfg['demo_open']),
));
