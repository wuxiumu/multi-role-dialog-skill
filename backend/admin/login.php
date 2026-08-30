<?php
/**
 * 后台登录 / 退出
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once __DIR__ . '/_layout.php';

if (isset($_GET['logout'])) {
    mrd_admin_logout();
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = isset($_POST['user']) ? trim((string)$_POST['user']) : '';
    $pass = isset($_POST['password']) ? (string)$_POST['password'] : '';
    if (mrd_admin_login($user, $pass)) {
        header('Location: index.php');
        exit;
    }
    $error = '账号或密码错误';
}

if (mrd_admin_logged_in()) {
    header('Location: index.php');
    exit;
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>后台登录 · 角色场</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f1419;color:#e8eef6;font-family:-apple-system,"PingFang SC","Noto Sans SC",sans-serif}
.box{width:min(360px,92vw);background:#1a222c;border:1px solid #2a3544;border-radius:12px;padding:1.5rem}
h1{margin:0 0 .35rem;font-size:1.25rem}p{color:#8b9bb0;font-size:.9rem;margin:0 0 1rem}
label{display:block;margin:.7rem 0 .3rem;color:#8b9bb0;font-size:.85rem}
input{width:100%;padding:.55rem .65rem;border-radius:8px;border:1px solid #2a3544;background:#121820;color:#e8eef6;font:inherit;box-sizing:border-box}
button{margin-top:1rem;width:100%;padding:.6rem;border:0;border-radius:8px;background:#ff8a3d;color:#1a1008;font-weight:600;cursor:pointer;font:inherit}
.err{background:rgba(240,113,120,.12);color:#f07178;padding:.5rem .7rem;border-radius:8px;margin-bottom:.8rem;font-size:.9rem}
</style>
</head>
<body>
<form class="box" method="post">
  <h1>后台登录</h1>
  <p>默认账号 admin / admin123（请尽快在全局配置中修改）</p>
  <?php if ($error !== ''): ?><div class="err"><?php echo admin_h($error); ?></div><?php endif; ?>
  <label>账号</label>
  <input name="user" autocomplete="username" required>
  <label>密码</label>
  <input type="password" name="password" autocomplete="current-password" required>
  <button type="submit">进入后台</button>
</form>
</body>
</html>
