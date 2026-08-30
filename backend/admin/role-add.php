<?php
/**
 * 新建角色
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once __DIR__ . '/_layout.php';
mrd_admin_require();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tagsRaw = isset($_POST['tags']) ? (string)$_POST['tags'] : '';
    $tags = preg_split('/[,，]/u', $tagsRaw);
    $tagsClean = array();
    foreach ($tags as $t) {
        $t = trim($t);
        if ($t !== '') {
            $tagsClean[] = $t;
        }
    }
    $meta = array(
        'name' => isset($_POST['name']) ? trim((string)$_POST['name']) : '',
        'slug' => isset($_POST['slug']) ? trim((string)$_POST['slug']) : '',
        'category' => isset($_POST['category']) ? trim((string)$_POST['category']) : '未分类',
        'avatar' => isset($_POST['avatar']) ? trim((string)$_POST['avatar']) : '',
        'avatarColor' => isset($_POST['avatarColor']) ? trim((string)$_POST['avatarColor']) : 'linear-gradient(135deg,#94a3b8,#64748b)',
        'tagline' => isset($_POST['tagline']) ? trim((string)$_POST['tagline']) : '',
        'description' => isset($_POST['description']) ? trim((string)$_POST['description']) : '',
        'tags' => $tagsClean,
        'featured' => !empty($_POST['featured']),
        'order' => isset($_POST['order']) ? (int)$_POST['order'] : 100,
        'with_persona' => !empty($_POST['with_persona']),
        'with_quotes' => !empty($_POST['with_quotes']),
    );
    if ($meta['avatar'] === '' && $meta['name'] !== '') {
        $meta['avatar'] = function_exists('mb_substr')
            ? mb_substr($meta['name'], 0, 1, 'UTF-8')
            : substr($meta['name'], 0, 1);
    }
    $res = mrd_create_role($meta);
    if ($res === true) {
        header('Location: role-edit.php?slug=' . urlencode($meta['slug']) . '&msg=' . urlencode('角色已创建，可继续编辑 MD'));
        exit;
    }
    $error = $res;
}

admin_layout_start('新建角色', 'role-add.php');
?>
<h1>新建角色</h1>
<?php if ($error !== ''): ?><div class="msg err"><?php echo admin_h($error); ?></div><?php endif; ?>
<form class="card" method="post">
  <div class="row">
    <div>
      <label>名称 *</label>
      <input name="name" required placeholder="客服小七">
    </div>
    <div>
      <label>Slug *（URL / 文件夹名）</label>
      <input name="slug" required pattern="[a-z0-9][a-z0-9\-]{0,63}" placeholder="support-xiaoqi">
    </div>
  </div>
  <div class="row">
    <div>
      <label>分类</label>
      <input name="category" list="cats" placeholder="客服">
      <datalist id="cats">
        <option value="名人"><option value="客服"><option value="星座"><option value="榜样">
      </datalist>
    </div>
    <div>
      <label>头像字</label>
      <input name="avatar" maxlength="2" placeholder="七">
    </div>
    <div>
      <label>排序（越小越前）</label>
      <input name="order" type="number" value="100">
    </div>
  </div>
  <label>头像渐变色</label>
  <input name="avatarColor" value="linear-gradient(135deg,#3ad29f,#2b6cff)">
  <label>一句话简介</label>
  <input name="tagline" placeholder="7×24 温柔兜底">
  <label>描述</label>
  <input name="description" placeholder="角色说明">
  <label>标签（逗号分隔）</label>
  <input name="tags" placeholder="售后, 安抚, FAQ">
  <label><input type="checkbox" name="featured" value="1"> 精选展示</label>
  <label><input type="checkbox" name="with_persona" value="1" checked> 同时创建 persona.json（人格卡）</label>
  <label><input type="checkbox" name="with_quotes" value="1"> 同时创建 quotes.json（语录库，复杂 mock 用）</label>
  <p style="margin-top:1rem">
    <button class="btn btn-brand" type="submit">创建并编辑 MD</button>
    <a class="btn" href="index.php">取消</a>
  </p>
</form>
<?php
admin_layout_end();
