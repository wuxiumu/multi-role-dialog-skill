<?php
/**
 * 角色人格卡 persona.json（结构化表单 + 可选同步 prompt.md）
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once __DIR__ . '/_layout.php';
mrd_admin_require();

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
if (!mrd_role_exists($slug)) {
    header('Location: index.php?msg=' . urlencode('角色不存在'));
    exit;
}

$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : 'save';
    if ($action === 'init') {
        $res = mrd_ensure_role_json($slug, 'persona.json');
        if ($res === true) {
            header('Location: role-persona.php?slug=' . urlencode($slug) . '&msg=' . urlencode('已创建 persona.json'));
            exit;
        }
        $error = $res;
    } else {
        $persona = array(
            'name' => isset($_POST['name']) ? trim((string)$_POST['name']) : '',
            'alias' => isset($_POST['alias']) ? trim((string)$_POST['alias']) : '',
            'tagline' => isset($_POST['tagline']) ? trim((string)$_POST['tagline']) : '',
            'system_prompt' => isset($_POST['system_prompt']) ? trim((string)$_POST['system_prompt']) : '',
            'style_rules' => mrd_lines_to_list(isset($_POST['style_rules']) ? $_POST['style_rules'] : ''),
            'catchphrases' => mrd_lines_to_list(isset($_POST['catchphrases']) ? $_POST['catchphrases'] : ''),
            'guardrails' => mrd_lines_to_list(isset($_POST['guardrails']) ? $_POST['guardrails'] : ''),
            'disclaimer_text' => isset($_POST['disclaimer_text']) ? trim((string)$_POST['disclaimer_text']) : '',
            'quick_questions' => mrd_lines_to_list(isset($_POST['quick_questions']) ? $_POST['quick_questions'] : ''),
            'quick_pool' => mrd_lines_to_list(isset($_POST['quick_pool']) ? $_POST['quick_pool'] : ''),
            'mode' => isset($_POST['mode']) ? trim((string)$_POST['mode']) : 'match',
        );
        $res = mrd_save_role_json($slug, 'persona.json', $persona);
        if ($res === true) {
            if (!empty($_POST['sync_prompt']) && $persona['system_prompt'] !== '') {
                $promptBody = $persona['system_prompt'];
                if (substr($promptBody, -1) !== "\n") {
                    $promptBody .= "\n";
                }
                mrd_write_role_file($slug, 'prompt.md', $promptBody);
            }
            $msg = 'persona.json 已保存';
        } else {
            $error = $res;
        }
    }
}

$role = mrd_load_role($slug);
$persona = mrd_load_role_json($slug, 'persona.json');
$has = $persona !== null;

admin_layout_start('人格卡 · ' . $role['name'], 'index.php');
?>
<h1>人格卡：<?php echo admin_h($role['name']); ?> <code class="muted"><?php echo admin_h($slug); ?></code></h1>
<?php if ($msg !== ''): ?><div class="msg ok"><?php echo admin_h($msg); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="msg err"><?php echo admin_h($error); ?></div><?php endif; ?>

<?php echo admin_role_tabs($slug, 'persona'); ?>

<?php if (!$has): ?>
<div class="card">
  <p class="muted" style="margin-top:0">该角色尚无 <code>persona.json</code>。人格卡用于快捷提问、口头禅、护栏、免责声明等复杂配置；简单人设继续用 <code>prompt.md</code>。</p>
  <form method="post">
    <input type="hidden" name="action" value="init">
    <button class="btn btn-brand" type="submit">创建默认人格卡</button>
    <a class="btn" href="role-edit.php?slug=<?php echo urlencode($slug); ?>">返回 MD 编辑</a>
  </form>
</div>
<?php else:
  $p = $persona;
?>
<form class="card" method="post">
  <input type="hidden" name="action" value="save">
  <p class="muted" style="margin-top:0">文件：<code>roles/<?php echo admin_h($slug); ?>/persona.json</code> · 列表类字段每行一条</p>
  <div class="row">
    <div>
      <label>名称</label>
      <input name="name" value="<?php echo admin_h(isset($p['name']) ? $p['name'] : ''); ?>">
    </div>
    <div>
      <label>别名</label>
      <input name="alias" value="<?php echo admin_h(isset($p['alias']) ? $p['alias'] : ''); ?>">
    </div>
    <div>
      <label>模式</label>
      <select name="mode">
        <?php
        $mode = isset($p['mode']) ? $p['mode'] : 'match';
        foreach (array('match' => '关键词/匹配', 'mock' => '语录 mock', 'live' => '大模型') as $k => $lab) {
            $sel = $mode === $k ? ' selected' : '';
            echo '<option value="' . admin_h($k) . '"' . $sel . '>' . admin_h($lab) . '</option>';
        }
        ?>
      </select>
    </div>
  </div>
  <label>一句话简介</label>
  <input name="tagline" value="<?php echo admin_h(isset($p['tagline']) ? $p['tagline'] : ''); ?>">
  <label>系统提示词 system_prompt</label>
  <textarea name="system_prompt" style="min-height:160px"><?php echo admin_h(isset($p['system_prompt']) ? $p['system_prompt'] : ''); ?></textarea>
  <label><input type="checkbox" name="sync_prompt" value="1"> 保存时同步写入 prompt.md</label>
  <div class="row">
    <div>
      <label>风格规则 style_rules</label>
      <textarea name="style_rules" style="min-height:120px"><?php echo admin_h(mrd_list_to_lines(isset($p['style_rules']) ? $p['style_rules'] : array())); ?></textarea>
    </div>
    <div>
      <label>口头禅 catchphrases</label>
      <textarea name="catchphrases" style="min-height:120px"><?php echo admin_h(mrd_list_to_lines(isset($p['catchphrases']) ? $p['catchphrases'] : array())); ?></textarea>
    </div>
  </div>
  <label>护栏 guardrails</label>
  <textarea name="guardrails" style="min-height:100px"><?php echo admin_h(mrd_list_to_lines(isset($p['guardrails']) ? $p['guardrails'] : array())); ?></textarea>
  <label>免责声明</label>
  <input name="disclaimer_text" value="<?php echo admin_h(isset($p['disclaimer_text']) ? $p['disclaimer_text'] : ''); ?>">
  <div class="row">
    <div>
      <label>快捷提问 quick_questions</label>
      <textarea name="quick_questions" style="min-height:120px"><?php echo admin_h(mrd_list_to_lines(isset($p['quick_questions']) ? $p['quick_questions'] : array())); ?></textarea>
    </div>
    <div>
      <label>题池 quick_pool（补位）</label>
      <textarea name="quick_pool" style="min-height:120px"><?php echo admin_h(mrd_list_to_lines(isset($p['quick_pool']) ? $p['quick_pool'] : array())); ?></textarea>
    </div>
  </div>
  <p style="margin-top:1rem">
    <button class="btn btn-brand" type="submit">保存人格卡</button>
    <a class="btn" href="index.php">返回列表</a>
  </p>
</form>
<?php endif; ?>
<?php
admin_layout_end();
