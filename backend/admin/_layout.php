<?php
/**
 * 后台公共：样式 + 导航。
 */
function admin_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function admin_layout_start($title, $active = '') {
    $nav = array(
        'index.php' => '角色列表',
        'role-add.php' => '新建角色',
        'config-set.php' => '全局配置',
    );
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . admin_h($title) . ' · 角色场后台</title>';
    echo '<style>
:root{--bg:#0f1419;--panel:#1a222c;--line:#2a3544;--text:#e8eef6;--muted:#8b9bb0;--brand:#ff8a3d;--ok:#3ad29f;--danger:#f07178}
*{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Noto Sans SC",sans-serif;background:var(--bg);color:var(--text);line-height:1.5}
a{color:var(--brand);text-decoration:none}a:hover{text-decoration:underline}
.top{display:flex;align-items:center;gap:1rem;padding:.75rem 1.25rem;border-bottom:1px solid var(--line);background:var(--panel)}
.top .brand{font-weight:700;color:#fff;margin-right:auto}
.top nav a{color:var(--muted);margin-right:1rem;padding:.25rem 0;border-bottom:2px solid transparent}
.top nav a.on{color:var(--brand);border-bottom-color:var(--brand)}
.wrap{max-width:960px;margin:0 auto;padding:1.5rem 1.25rem 3rem}
h1{font-size:1.35rem;margin:0 0 1rem}
.card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:1rem 1.15rem;margin-bottom:1rem}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.55rem .4rem;border-bottom:1px solid var(--line);font-size:.92rem}
th{color:var(--muted);font-weight:600}
label{display:block;margin:.75rem 0 .3rem;color:var(--muted);font-size:.85rem}
input,textarea,select{width:100%;padding:.55rem .65rem;border-radius:8px;border:1px solid var(--line);background:#121820;color:var(--text);font:inherit}
textarea{min-height:220px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.85rem;line-height:1.45}
.row{display:flex;gap:.75rem;flex-wrap:wrap}.row>*{flex:1;min-width:140px}
.btn{display:inline-block;padding:.45rem .9rem;border-radius:8px;border:1px solid var(--line);background:#243041;color:var(--text);cursor:pointer;font:inherit}
.btn:hover{border-color:var(--brand)}
.btn-brand{background:var(--brand);border-color:var(--brand);color:#1a1008;font-weight:600}
.btn-danger{background:transparent;border-color:var(--danger);color:var(--danger)}
.msg{padding:.65rem .85rem;border-radius:8px;margin-bottom:1rem}
.msg.ok{background:rgba(58,210,159,.12);color:var(--ok)}
.msg.err{background:rgba(240,113,120,.12);color:var(--danger)}
.muted{color:var(--muted);font-size:.88rem}
.tag{display:inline-block;padding:.1rem .4rem;border-radius:4px;background:#243041;font-size:.75rem;color:var(--muted);margin-right:.25rem}
</style></head><body>';
    echo '<div class="top"><div class="brand">角色场 · 后台</div><nav>';
    foreach ($nav as $href => $label) {
        $cls = ($active === $href) ? ' class="on"' : '';
        echo '<a href="' . $href . '"' . $cls . '>' . admin_h($label) . '</a>';
    }
    echo '</nav><a href="login.php?logout=1" class="muted">退出</a></div>';
    echo '<div class="wrap">';
}

function admin_layout_end() {
    echo '</div></body></html>';
}

/**
 * 角色编辑子导航：MD / 人格卡 / 语录
 */
function admin_role_tabs($slug, $active = 'config') {
    $tabs = array(
        'config' => array('label' => 'config.md', 'href' => 'role-edit.php?slug=' . urlencode($slug) . '&tab=config'),
        'prompt' => array('label' => 'prompt.md', 'href' => 'role-edit.php?slug=' . urlencode($slug) . '&tab=prompt'),
        'qa' => array('label' => 'qa.md', 'href' => 'role-edit.php?slug=' . urlencode($slug) . '&tab=qa'),
        'persona' => array('label' => 'persona.json', 'href' => 'role-persona.php?slug=' . urlencode($slug)),
        'quotes' => array('label' => 'quotes.json', 'href' => 'quotes.php?slug=' . urlencode($slug)),
    );
    $html = '<div class="card" style="padding:.6rem 1rem">';
    foreach ($tabs as $k => $t) {
        $cls = ($active === $k) ? ' btn-brand' : '';
        $html .= '<a class="btn' . $cls . '" href="' . $t['href'] . '" style="margin:0 .25rem .25rem 0">' . admin_h($t['label']) . '</a>';
    }
    $html .= '</div>';
    return $html;
}
