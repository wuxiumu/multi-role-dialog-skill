<?php
/**
 * 角色语录管理页（从 yingxue/public/admin.html 迁入，按 slug 隔离）
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once __DIR__ . '/_layout.php';
mrd_admin_require();

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
if (!mrd_role_exists($slug)) {
    header('Location: index.php?msg=' . urlencode('角色不存在'));
    exit;
}

$role = mrd_load_role($slug);
$has = mrd_role_has_json($slug, 'quotes.json');
$msg = '';
$error = '';

if (!$has && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'init') {
    $res = mrd_ensure_role_json($slug, 'quotes.json');
    if ($res === true) {
        header('Location: quotes.php?slug=' . urlencode($slug));
        exit;
    }
    $error = $res;
}

admin_layout_start('语录 · ' . $role['name'], 'index.php');
?>
<h1>语录库：<?php echo admin_h($role['name']); ?> <code class="muted"><?php echo admin_h($slug); ?></code></h1>
<?php if ($error !== ''): ?><div class="msg err"><?php echo admin_h($error); ?></div><?php endif; ?>
<?php echo admin_role_tabs($slug, 'quotes'); ?>

<?php if (!$has): ?>
<div class="card">
  <p class="muted" style="margin-top:0">该角色尚无 <code>quotes.json</code>。语录库适合主题分池、关键词命中的复杂 mock 引擎；简单 FAQ 继续用 <code>qa.md</code>。</p>
  <form method="post">
    <input type="hidden" name="action" value="init">
    <button class="btn btn-brand" type="submit">创建空语录库</button>
    <a class="btn" href="role-edit.php?slug=<?php echo urlencode($slug); ?>">返回 MD 编辑</a>
  </form>
</div>
<?php else: ?>
<p class="muted">文件：<code>roles/<?php echo admin_h($slug); ?>/quotes.json</code> · 写入前自动备份 <code>.bak</code></p>
<div class="card" style="padding:1rem">
  <div class="toolbar" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
    <select id="topicSel" style="min-width:140px"></select>
    <input type="text" id="search" placeholder="搜内容 / id / 关键词…" style="flex:1;min-width:160px">
    <select id="perPage">
      <option value="10">10 条/页</option>
      <option value="20">20 条/页</option>
      <option value="50">50 条/页</option>
    </select>
    <button class="btn btn-brand" type="button" id="addBtn">＋ 新增</button>
  </div>
  <div class="muted" id="stats" style="margin-bottom:10px"></div>
  <div id="list"></div>
  <div id="pager" style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:14px;flex-wrap:wrap"></div>
</div>

<!-- 编辑弹窗 -->
<div id="editMask" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);place-items:center;z-index:50;padding:16px">
  <div class="card" style="width:min(520px,100%);max-height:88vh;overflow-y:auto;margin:0">
    <h2 id="editTitle" style="font-size:1rem;margin:0 0 .75rem;color:var(--brand)">新增语录</h2>
    <label>主题</label><select id="fTopic"></select>
    <label>语录内容（≤500 字）</label>
    <textarea id="fText" style="min-height:88px" placeholder="朋友，…"></textarea>
    <label>命中关键词（逗号分隔，可空）</label>
    <input type="text" id="fKw" placeholder="如：比特币，行情，被套">
    <label>来源标注（可空）</label>
    <input type="text" id="fSource" placeholder="风格戏仿 / 网络梗 / …">
    <p style="margin-top:1rem;display:flex;gap:8px;justify-content:flex-end">
      <button class="btn" type="button" id="editCancel">取消</button>
      <button class="btn btn-brand" type="button" id="editSave">保存</button>
    </p>
  </div>
</div>

<div id="confirmMask" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);place-items:center;z-index:50;padding:16px">
  <div class="card" style="width:min(420px,100%);margin:0">
    <h2 id="confirmTitle" style="font-size:1rem;margin:0 0 .5rem">确认</h2>
    <p id="confirmMsg" class="muted"></p>
    <p style="margin-top:1rem;display:flex;gap:8px;justify-content:flex-end">
      <button class="btn" type="button" id="confirmNo">取消</button>
      <button class="btn btn-danger" type="button" id="confirmYes">删除</button>
    </p>
  </div>
</div>

<div id="toast" style="position:fixed;top:16px;left:50%;transform:translateX(-50%);background:#20242e;border:1px solid #2a3544;padding:9px 16px;border-radius:999px;opacity:0;transition:.25s;z-index:60;pointer-events:none;font-size:13px"></div>

<script>
(function () {
  var slug = <?php echo json_encode($slug, JSON_UNESCAPED_UNICODE); ?>;
  var apiBase = 'quotes-api.php?slug=' + encodeURIComponent(slug) + '&op=';
  var $ = function (id) { return document.getElementById(id); };
  var state = { page: 1, perPage: 10, topic: '', q: '', pages: 1, total: 0, topics: [], editing: null };

  function toast(msg, type) {
    var t = $('toast');
    t.textContent = msg;
    t.style.opacity = '1';
    t.style.borderColor = type === 'ok' ? '#3ad29f' : (type === 'error' ? '#f07178' : '#2a3544');
    t.style.color = type === 'ok' ? '#b7f4dc' : (type === 'error' ? '#ffb4b4' : '#e8eef6');
    setTimeout(function () { t.style.opacity = '0'; }, 1800);
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function api(op, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers || {});
    return fetch(apiBase + op, opts).then(function (r) { return r.json(); });
  }
  function showMask(id, on) {
    var el = $(id);
    el.style.display = on ? 'grid' : 'none';
  }

  function load() {
    var qs = 'quotes&page=' + state.page + '&per_page=' + state.perPage +
      '&topic=' + encodeURIComponent(state.topic) + '&q=' + encodeURIComponent(state.q);
    api(qs).then(function (j) {
      if (!j.ok) { toast(j.error || '加载失败', 'error'); return; }
      state.topics = j.topics; state.pages = j.pages; state.total = j.total;
      fillTopicSelects();
      renderList(j.items);
      renderPager();
      $('stats').textContent = '共 ' + j.total + ' 条 · 第 ' + j.page + '/' + j.pages + ' 页';
    }).catch(function () { toast('请求失败', 'error'); });
  }

  function fillTopicSelects() {
    var html = '<option value="">全部主题</option>';
    state.topics.forEach(function (t) {
      html += '<option value="' + esc(t.id) + '">' + esc(t.name) + '（' + t.count + '）</option>';
    });
    var sel = $('topicSel');
    var cur = state.topic;
    sel.innerHTML = html;
    sel.value = cur;
    if (sel.value !== cur) { sel.value = ''; state.topic = ''; }
    $('fTopic').innerHTML = state.topics.map(function (t) {
      return '<option value="' + esc(t.id) + '">' + esc(t.name) + '</option>';
    }).join('');
  }

  function renderList(items) {
    var box = $('list');
    if (!items.length) { box.innerHTML = '<div class="muted" style="text-align:center;padding:24px">没有匹配的语录</div>'; return; }
    box.innerHTML = items.map(function (it) {
      var kws = (it.keywords || []).map(function (k) { return '<span class="tag">' + esc(k) + '</span>'; }).join('');
      return '<div style="display:flex;gap:10px;padding:.65rem 0;border-bottom:1px solid #2a3544">' +
        '<div style="flex:1;min-width:0">' +
          '<div style="line-height:1.55">' + esc(it.text) + '</div>' +
          '<div style="margin-top:6px">' +
            '<span class="tag">' + esc(it.id) + '</span>' +
            '<span class="tag">' + esc(it.topic_name) + '</span>' + kws +
            (it.source ? '<span class="tag">' + esc(it.source) + '</span>' : '') +
          '</div>' +
        '</div>' +
        '<div style="display:flex;flex-direction:column;gap:6px">' +
          '<button class="btn" type="button" data-act="edit" data-t="' + esc(it.topic_id) + '" data-id="' + esc(it.id) + '">编辑</button>' +
          '<button class="btn btn-danger" type="button" data-act="del" data-t="' + esc(it.topic_id) + '" data-id="' + esc(it.id) + '">删除</button>' +
        '</div></div>';
    }).join('');
  }

  $('list').addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-act]');
    if (!btn) return;
    var t = btn.getAttribute('data-t'), id = btn.getAttribute('data-id');
    if (btn.getAttribute('data-act') === 'edit') { openEdit(t, id); }
    else { askDelete(t, id); }
  });

  function renderPager() {
    var p = $('pager');
    p.innerHTML = '<button class="btn" type="button" id="pgPrev"' + (state.page <= 1 ? ' disabled' : '') + '>‹ 上一页</button>' +
      '<span class="muted">' + state.page + ' / ' + state.pages + '</span>' +
      '<button class="btn" type="button" id="pgNext"' + (state.page >= state.pages ? ' disabled' : '') + '>下一页 ›</button>';
    $('pgPrev').onclick = function () { if (state.page > 1) { state.page--; load(); } };
    $('pgNext').onclick = function () { if (state.page < state.pages) { state.page++; load(); } };
  }

  var searchTimer = null;
  $('search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    var v = this.value.trim();
    searchTimer = setTimeout(function () { state.q = v; state.page = 1; load(); }, 300);
  });
  $('topicSel').onchange = function () { state.topic = this.value; state.page = 1; load(); };
  $('perPage').onchange = function () { state.perPage = parseInt(this.value, 10); state.page = 1; load(); };

  function openEdit(topicId, id) {
    state.editing = id ? { topic: topicId, id: id } : null;
    $('editTitle').textContent = id ? '编辑语录' : '新增语录';
    if (id) {
      api('quotes&per_page=100&q=' + encodeURIComponent(id)).then(function (j) {
        var it = (j.items || []).filter(function (x) { return x.id === id; })[0];
        if (!it) { toast('未找到该语录', 'error'); return; }
        $('fTopic').value = it.topic_id;
        $('fText').value = it.text;
        $('fKw').value = (it.keywords || []).join('，');
        $('fSource').value = it.source || '';
        showMask('editMask', true);
      });
    } else {
      if (topicId) { $('fTopic').value = topicId; }
      $('fText').value = ''; $('fKw').value = ''; $('fSource').value = '';
      showMask('editMask', true);
    }
  }
  $('addBtn').onclick = function () { openEdit('', null); };
  $('editCancel').onclick = function () { showMask('editMask', false); };
  $('editSave').onclick = function () {
    var payload = {
      topic_id: $('fTopic').value,
      text: $('fText').value.trim(),
      keywords: $('fKw').value,
      source: $('fSource').value.trim()
    };
    if (!payload.text) { toast('内容不能为空', 'error'); return; }
    var req;
    if (state.editing) {
      payload.id = state.editing.id;
      payload.topic_id = state.editing.topic;
      payload.new_topic_id = $('fTopic').value;
      req = api('quotes/update', { method: 'POST', body: JSON.stringify(payload) });
    } else {
      req = api('quotes', { method: 'POST', body: JSON.stringify(payload) });
    }
    req.then(function (j) {
      if (!j.ok) { toast(j.error || '保存失败', 'error'); return; }
      showMask('editMask', false);
      toast(state.editing ? '已更新 ' + j.item.id : '已新增 ' + j.item.id, 'ok');
      load();
    });
  };

  var pendingDel = null;
  function askDelete(topicId, id) {
    pendingDel = { topic_id: topicId, id: id };
    $('confirmTitle').textContent = '删除语录';
    $('confirmMsg').textContent = '确认删除 ' + id + '？写入前会备份 quotes.json.bak。';
    showMask('confirmMask', true);
  }
  $('confirmNo').onclick = function () { showMask('confirmMask', false); pendingDel = null; };
  $('confirmYes').onclick = function () {
    if (!pendingDel) return;
    api('quotes/delete', { method: 'POST', body: JSON.stringify(pendingDel) }).then(function (j) {
      showMask('confirmMask', false);
      if (!j.ok) { toast(j.error || '删除失败', 'error'); return; }
      toast('已删除 ' + j.deleted, 'ok');
      pendingDel = null;
      load();
    });
  };

  load();
})();
</script>
<?php endif; ?>
<?php
admin_layout_end();
