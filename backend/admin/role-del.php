<?php
/**
 * 删除角色
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
mrd_admin_require();

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$res = mrd_delete_role($slug);
if ($res === true) {
    header('Location: index.php?msg=' . urlencode('已删除：' . $slug));
} else {
    header('Location: index.php?msg=' . urlencode('删除失败：' . $res));
}
exit;
