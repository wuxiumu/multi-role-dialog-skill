<?php
/**
 * MD 在线写入：新建角色目录、更新三大 MD、写全局配置。
 */

function mrd_safe_write($path, $content) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) {
            return false;
        }
    }
    if (is_file($path)) {
        @copy($path, $path . '.bak');
    }
    $tmp = $path . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function mrd_default_config_md($meta) {
    $defaults = array(
        'name' => '新角色',
        'slug' => 'new-role',
        'category' => '未分类',
        'avatar' => '新',
        'avatarColor' => 'linear-gradient(135deg,#94a3b8,#64748b)',
        'tagline' => '',
        'description' => '',
        'tags' => array(),
        'featured' => false,
        'order' => 100,
    );
    $meta = array_merge($defaults, $meta);
    $body = isset($meta['description']) ? (string)$meta['description'] : '';
    unset($meta['description']); // description 放 frontmatter 也可，这里两边都写一份便于编辑
    $fm = mrd_build_frontmatter($meta);
    return $fm . $body . "\n";
}

function mrd_default_prompt_md($name) {
    return "你是「{$name}」。请始终以该角色的口吻、价值观与表达习惯回复用户。\n"
        . "规则：\n"
        . "1. 不冒充真人官方账号；如被追问身份，诚实说明这是角色扮演助手。\n"
        . "2. 不提供违法、伤害性或医疗/投资等专业建议；必要时给出免责提醒。\n"
        . "3. 回答简洁有温度，避免空洞套话。\n";
}

function mrd_default_qa_md() {
    return "## Q: 你好|嗨|在吗|hello|hi\n"
        . "A: 你好，我在。想聊什么直接说就行。\n\n"
        . "## Q: 谢谢|感谢|多谢\n"
        . "A: 不客气，有需要随时再来。\n\n"
        . "## Q: 你是谁|你是什么\n"
        . "A: 我是基于配置文件驱动的角色对话助手，可以陪你闲聊、答疑。\n";
}

/**
 * 新建角色：创建目录 + 三套 MD。
 * @param array $meta 至少含 slug、name
 * @return true|string 成功返回 true，失败返回错误信息
 */
function mrd_create_role($meta) {
    $slug = isset($meta['slug']) ? trim((string)$meta['slug']) : '';
    if (!mrd_slug_ok($slug)) {
        return 'slug 不合法（小写字母/数字/短横线）';
    }
    $dir = mrd_role_dir($slug);
    if (is_dir($dir)) {
        return '角色已存在：' . $slug;
    }
    if (!@mkdir($dir, 0775, true)) {
        return '无法创建角色目录';
    }
    $name = isset($meta['name']) ? (string)$meta['name'] : $slug;
    $meta['slug'] = $slug;
    $meta['name'] = $name;

    $ok1 = mrd_safe_write($dir . '/config.md', mrd_default_config_md($meta));
    $ok2 = mrd_safe_write($dir . '/prompt.md', mrd_default_prompt_md($name));
    $ok3 = mrd_safe_write($dir . '/qa.md', mrd_default_qa_md());
    if (!$ok1 || !$ok2 || !$ok3) {
        return '写入 MD 文件失败（检查权限）';
    }
    // 可选：创建时附带人格卡 JSON（语录库按需在后台启用）
    if (!empty($meta['with_persona'])) {
        $pres = mrd_save_role_json($slug, 'persona.json', mrd_default_persona($meta));
        if ($pres !== true) {
            return $pres;
        }
    }
    if (!empty($meta['with_quotes'])) {
        $qres = mrd_save_role_json($slug, 'quotes.json', mrd_default_quotes());
        if ($qres !== true) {
            return $qres;
        }
    }
    return true;
}

function mrd_write_role_file($slug, $filename, $content) {
    if (!mrd_role_exists($slug)) {
        return '角色不存在';
    }
    $allowed = array('config.md', 'prompt.md', 'qa.md');
    if (!in_array($filename, $allowed, true)) {
        return '不允许编辑该文件';
    }
    $path = mrd_role_dir($slug) . '/' . $filename;
    if (!mrd_safe_write($path, $content)) {
        return '写入失败';
    }
    return true;
}

/**
 * 删除角色目录（MD + JSON 及备份）。
 */
function mrd_delete_role($slug) {
    if (!mrd_role_exists($slug)) {
        return '角色不存在';
    }
    $dir = mrd_role_dir($slug);
    $files = array(
        'config.md', 'prompt.md', 'qa.md',
        'persona.json', 'quotes.json',
        'config.md.bak', 'prompt.md.bak', 'qa.md.bak',
        'persona.json.bak', 'quotes.json.bak',
    );
    foreach ($files as $f) {
        $p = $dir . '/' . $f;
        if (is_file($p)) {
            @unlink($p);
        }
    }
    // 清理残留临时文件
    foreach (glob($dir . '/*') ?: array() as $p) {
        if (is_file($p)) {
            @unlink($p);
        }
    }
    if (!@rmdir($dir)) {
        return '目录删除失败（可能非空）';
    }
    return true;
}

/**
 * 将可写配置项写回 global.php（保留 local 覆盖能力）。
 * 只更新白名单键，避免破坏 PHP 语法。
 */
function mrd_save_global_settings($updates) {
    $allowed = array(
        'site_name', 'site_tagline', 'disclaimer',
        'llm_endpoint', 'llm_model', 'llm_key', 'llm_timeout',
        'llm_max_history', 'llm_enabled',
        'demo_open', 'admin_user', 'admin_password',
        'cors_origin', 'asset_base',
    );
    $cfg = mrd_config_fresh();
    foreach ($allowed as $k) {
        if (array_key_exists($k, $updates)) {
            $cfg[$k] = $updates[$k];
        }
    }

    // 写独立的 writable 覆盖文件，避免重写整个 global.php
    $path = MRD_ROOT . '/config/settings.json';
    $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return 'JSON 编码失败';
    }
    if (!mrd_safe_write($path, $json)) {
        return '写入 settings.json 失败';
    }
    return true;
}

function mrd_load_settings_overlay() {
    $path = MRD_ROOT . '/config/settings.json';
    if (!is_file($path)) {
        return array();
    }
    $j = json_decode((string)@file_get_contents($path), true);
    return is_array($j) ? $j : array();
}
