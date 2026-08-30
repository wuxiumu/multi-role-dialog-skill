<?php
/**
 * GET /api/llm-providers.php
 * 返回可选模型列表（不含密钥）。登录用户用于个人中心切换。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    mrd_fail('仅支持 GET', 405, 405);
}

$cfg = mrd_config();
$providers = mrd_llm_providers_public($cfg);
$default = isset($cfg['llm_default_provider']) ? (string)$cfg['llm_default_provider'] : 'none';

mrd_ok(array(
    'llm_enabled' => !empty($cfg['llm_enabled']),
    'default' => $default,
    'providers' => $providers,
));
