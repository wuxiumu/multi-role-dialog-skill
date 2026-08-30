<?php
/**
 * 后台首页：角色列表
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once __DIR__ . '/_layout.php';
mrd_admin_require();

$roles = mrd_list_roles();
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

admin_layout_start('角色列表', 'index.php');
?>
<h1>角色列表 <span class="muted">（<?php echo count($roles); ?>）</span></h1>
<?php if ($msg !== ''): ?><div class="msg ok"><?php echo admin_h($msg); ?></div><?php endif; ?>
<div class="card">
  <p class="muted" style="margin-top:0">
    简单配置用 MD：<code>config.md</code> / <code>prompt.md</code> / <code>qa.md</code>；
    复杂配置用 JSON：<code>persona.json</code>（人格卡）/ <code>quotes.json</code>（语录库）。
  </p>
  <table>
    <thead>
      <tr><th>角色</th><th>分类</th><th>Slug</th><th>配置</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php if (empty($roles)): ?>
      <tr><td colspan="5" class="muted">暂无角色，去「新建角色」添加。</td></tr>
    <?php else: foreach ($roles as $r): ?>
      <tr>
        <td>
          <strong><?php echo admin_h($r['name']); ?></strong>
          <?php if (!empty($r['featured'])): ?><span class="tag">精选</span><?php endif; ?>
          <div class="muted"><?php echo admin_h($r['tagline']); ?></div>
        </td>
        <td><?php echo admin_h($r['category']); ?></td>
        <td><code><?php echo admin_h($r['slug']); ?></code></td>
        <td>
          <span class="tag">MD</span>
          <?php if (!empty($r['has_persona'])): ?><span class="tag">persona</span><?php endif; ?>
          <?php if (!empty($r['has_quotes'])): ?><span class="tag">quotes</span><?php endif; ?>
          <?php
            foreach ($r['tags'] as $t) {
                echo '<span class="tag">' . admin_h($t) . '</span>';
            }
          ?>
        </td>
        <td>
          <a class="btn" href="role-edit.php?slug=<?php echo urlencode($r['slug']); ?>">MD</a>
          <a class="btn" href="role-persona.php?slug=<?php echo urlencode($r['slug']); ?>">人格</a>
          <a class="btn" href="quotes.php?slug=<?php echo urlencode($r['slug']); ?>">语录</a>
          <a class="btn btn-danger" href="role-del.php?slug=<?php echo urlencode($r['slug']); ?>" onclick="return confirm('确认删除角色 <?php echo admin_h($r['slug']); ?>？');">删除</a>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<p><a class="btn btn-brand" href="role-add.php">+ 新建角色</a>
   <a class="btn" href="../api/role-list.php" target="_blank">预览 API</a></p>
<?php
admin_layout_end();
