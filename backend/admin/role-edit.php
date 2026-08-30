<?php
/**
 * 在线编辑角色 MD（config / prompt / qa）
 * 人格卡 / 语录见 role-persona.php、quotes.php
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once __DIR__ . '/_layout.php';
mrd_admin_require();

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
if (!mrd_role_exists($slug)) {
    header('Location: index.php?msg=' . urlencode('角色不存在'));
    exit;
}

$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'config';
$allowed = array('config' => 'config.md', 'prompt' => 'prompt.md', 'qa' => 'qa.md');
if (!isset($allowed[$tab])) {
    $tab = 'config';
}

$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = isset($_POST['tab']) ? (string)$_POST['tab'] : $tab;
    if (!isset($allowed[$tab])) {
        $tab = 'config';
    }
    $content = isset($_POST['content']) ? (string)$_POST['content'] : '';
    $content = str_replace("\r\n", "\n", $content);
    if (substr($content, -1) !== "\n") {
        $content .= "\n";
    }
    $res = mrd_write_role_file($slug, $allowed[$tab], $content);
    if ($res === true) {
        $msg = $allowed[$tab] . ' 已保存';
    } else {
        $error = $res;
    }
}

$role = mrd_load_role($slug);
$content = mrd_read_role_file($slug, $allowed[$tab]);

admin_layout_start('编辑 · ' . $role['name'], 'index.php');
?>
<h1>编辑：<?php echo admin_h($role['name']); ?> <code class="muted"><?php echo admin_h($slug); ?></code></h1>
<?php if ($msg !== ''): ?><div class="msg ok"><?php echo admin_h($msg); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="msg err"><?php echo admin_h($error); ?></div><?php endif; ?>

<?php echo admin_role_tabs($slug, $tab); ?>

<form class="card" method="post">
  <input type="hidden" name="tab" value="<?php echo admin_h($tab); ?>">
  <?php if ($tab === 'qa'): ?>
    <p class="muted">格式示例：<code>## Q: 关键词1|关键词2</code> 换行后 <code>A: 回答</code>。复杂语录分池请用 <a href="quotes.php?slug=<?php echo urlencode($slug); ?>">quotes.json</a>。</p>
  <?php elseif ($tab === 'config'): ?>
    <p class="muted">保留顶部 <code>---</code> 元数据块；改 slug 请用文件系统重命名目录，勿仅改 frontmatter。头像图：<code>avatarImage: logo.png</code>（本地 <code>asset/</code>）或 <code>avatarCdn: https://...</code>。人格/快捷提问见 <a href="role-persona.php?slug=<?php echo urlencode($slug); ?>">persona.json</a>。</p>
  <?php else: ?>
    <p class="muted">系统 Prompt：登录用户走大模型时注入为 system 消息。也可在人格卡里维护并勾选同步。</p>
  <?php endif; ?>
  <textarea name="content" spellcheck="false"><?php echo admin_h($content); ?></textarea>
  <p style="margin-top:.8rem">
    <button class="btn btn-brand" type="submit">保存 <?php echo admin_h($allowed[$tab]); ?></button>
    <a class="btn" href="index.php">返回列表</a>
  </p>
</form>
<?php
admin_layout_end();
