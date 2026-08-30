<?php
/**
 * GET /api/health.php — 健康检查
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

mrd_ok(array(
    'app' => 'multi-role-dialog',
    'php' => PHP_VERSION,
    'roles' => count(mrd_list_roles()),
));
