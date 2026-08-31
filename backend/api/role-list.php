<?php
/**
 * GET /api/role-list.php
 * 扫描 roles/，返回角色卡片列表。
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    mrd_fail('仅支持 GET', 405, 405);
}

$list = mrd_list_roles();
mrd_ok(array(
    'list' => $list,
    'total' => count($list),
));
