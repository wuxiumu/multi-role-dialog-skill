<?php
/**
 * 全局大模型 / 站点配置
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once __DIR__ . '/_layout.php';
mrd_admin_require();

$msg = '';
$error = '';
$cfg = mrd_config_fresh();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = array(
        'site_name' => isset($_POST['site_name']) ? trim((string)$_POST['site_name']) : '',
        'site_tagline' => isset($_POST['site_tagline']) ? trim((string)$_POST['site_tagline']) : '',
        'disclaimer' => isset($_POST['disclaimer']) ? trim((string)$_POST['disclaimer']) : '',
        'llm_endpoint' => isset($_POST['llm_endpoint']) ? trim((string)$_POST['llm_endpoint']) : '',
        'llm_model' => isset($_POST['llm_model']) ? trim((string)$_POST['llm_model']) : '',
        'llm_timeout' => isset($_POST['llm_timeout']) ? (int)$_POST['llm_timeout'] : 25,
        'llm_enabled' => !empty($_POST['llm_enabled']),
        'demo_open' => !empty($_POST['demo_open']),
        'admin_user' => isset($_POST['admin_user']) ? trim((string)$_POST['admin_user']) : 'admin',
        'cors_origin' => isset($_POST['cors_origin']) ? trim((string)$_POST['cors_origin']) : '*',
        'asset_base' => isset($_POST['asset_base']) ? trim((string)$_POST['asset_base']) : '',
    );
    // 密钥：留空表示不改；填 **** 忽略
    $keyIn = isset($_POST['llm_key']) ? trim((string)$_POST['llm_key']) : '';
    if ($keyIn !== '' && $keyIn !== '********') {
        $updates['llm_key'] = $keyIn;
    }
    $adminPass = isset($_POST['admin_password']) ? (string)$_POST['admin_password'] : '';
    if ($adminPass !== '') {
        $updates['admin_password'] = $adminPass;
    }

    $res = mrd_save_global_settings($updates);
    if ($res === true) {
        mrd_config_reload();
        $cfg = mrd_config_fresh();
        $msg = '配置已保存到 config/settings.json';
    } else {
        $error = $res;
    }
}

$keyMasked = !empty($cfg['llm_key']) ? '********' : '';

admin_layout_start('全局配置', 'config-set.php');
?>
<h1>全局配置</h1>
<?php if ($msg !== ''): ?><div class="msg ok"><?php echo admin_h($msg); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="msg err"><?php echo admin_h($error); ?></div><?php endif; ?>
<p class="muted">敏感密钥也可写在 <code>config/config.local.php</code>（不入库，优先级最高）。</p>

<form class="card" method="post">
  <h2 style="font-size:1rem;margin:0 0 .5rem">站点</h2>
  <label>站点名称</label>
  <input name="site_name" value="<?php echo admin_h(isset($cfg['site_name']) ? $cfg['site_name'] : ''); ?>">
  <label>副标题</label>
  <input name="site_tagline" value="<?php echo admin_h(isset($cfg['site_tagline']) ? $cfg['site_tagline'] : ''); ?>">
  <label>免责声明</label>
  <input name="disclaimer" value="<?php echo admin_h(isset($cfg['disclaimer']) ? $cfg['disclaimer'] : ''); ?>">
  <label>CORS Origin</label>
  <input name="cors_origin" value="<?php echo admin_h(isset($cfg['cors_origin']) ? $cfg['cors_origin'] : '*'); ?>">
  <label>资源 CDN / Asset Base（可选）</label>
  <input name="asset_base" value="<?php echo admin_h(isset($cfg['asset_base']) ? $cfg['asset_base'] : ''); ?>" placeholder="空=同源 /roles/... ；或 https://cdn.example.com">
  <p class="muted">角色本地图放在 <code>roles/{slug}/asset/</code>；config.md 用 <code>avatarImage: logo.png</code> 或 <code>avatarCdn: https://...</code>。</p>

  <h2 style="font-size:1rem;margin:1.25rem 0 .5rem">大模型（OpenAI 兼容）</h2>
  <label><input type="checkbox" name="llm_enabled" value="1" <?php echo !empty($cfg['llm_enabled']) ? 'checked' : ''; ?>> 启用 LLM</label>
  <label>API Endpoint</label>
  <input name="llm_endpoint" value="<?php echo admin_h(isset($cfg['llm_endpoint']) ? $cfg['llm_endpoint'] : ''); ?>">
  <label>Model</label>
  <input name="llm_model" value="<?php echo admin_h(isset($cfg['llm_model']) ? $cfg['llm_model'] : ''); ?>">
  <label>API Key（留空不修改）</label>
  <input name="llm_key" value="<?php echo admin_h($keyMasked); ?>" autocomplete="off" placeholder="sk-...">
  <label>超时（秒）</label>
  <input name="llm_timeout" type="number" value="<?php echo (int)(isset($cfg['llm_timeout']) ? $cfg['llm_timeout'] : 25); ?>">

  <h2 style="font-size:1rem;margin:1.25rem 0 .5rem">登录与后台</h2>
  <label><input type="checkbox" name="demo_open" value="1" <?php echo !empty($cfg['demo_open']) ? 'checked' : ''; ?>> 前台演示登录（任意非空账号密码可登录）</label>
  <div class="row">
    <div>
      <label>后台账号</label>
      <input name="admin_user" value="<?php echo admin_h(isset($cfg['admin_user']) ? $cfg['admin_user'] : 'admin'); ?>">
    </div>
    <div>
      <label>后台密码（留空不修改）</label>
      <input type="password" name="admin_password" autocomplete="new-password" placeholder="留空则保持原密码">
    </div>
  </div>

  <p style="margin-top:1.2rem">
    <button class="btn btn-brand" type="submit">保存配置</button>
  </p>
</form>
<?php
admin_layout_end();
