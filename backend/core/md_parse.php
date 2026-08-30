<?php
/**
 * MD 解析：角色目录扫描 + config/prompt/qa 读取。
 *
 * config.md 格式（YAML-ish 前置元数据，--- 包裹）：
 * ---
 * name: 客服小七
 * slug: support-xiaoqi
 * category: 客服
 * avatar: 七
 * avatarColor: linear-gradient(135deg,#3ad29f,#2b6cff)
 * avatarImage: logo.png
 * # avatarCdn: https://cdn.example.com/roles/xxx/logo.png
 * tagline: ...
 * ---
 *
 * 头像优先级：avatarCdn > avatarImage(完整URL) > avatarImage(本地 asset/) > 文字 avatar
 */

function mrd_parse_frontmatter($text) {
    $meta = array();
    $body = $text;
    if (preg_match('/^---\s*\r?\n(.*?)\r?\n---\s*\r?\n?(.*)$/s', $text, $m)) {
        $body = $m[2];
        $lines = preg_split('/\r?\n/', $m[1]);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if ($val === 'true') {
                $val = true;
            } elseif ($val === 'false') {
                $val = false;
            } elseif ($key === 'tags') {
                $parts = preg_split('/[,，]/u', $val);
                $tags = array();
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $tags[] = $p;
                    }
                }
                $val = $tags;
            }
            $meta[$key] = $val;
        }
    }
    return array('meta' => $meta, 'body' => $body);
}

function mrd_role_dir($slug) {
    return MRD_ROLES . '/' . $slug;
}

function mrd_role_exists($slug) {
    return mrd_slug_ok($slug) && is_dir(mrd_role_dir($slug));
}

function mrd_read_role_file($slug, $name) {
    $path = mrd_role_dir($slug) . '/' . $name;
    if (!is_file($path)) {
        return '';
    }
    $raw = @file_get_contents($path);
    return $raw === false ? '' : $raw;
}

/**
 * 解析角色头像图 URL（本地 asset 或 CDN）。
 * @return array{src:?string, source:string, image:?string, cdn:?string}
 */
function mrd_resolve_avatar_src($slug, $meta) {
    $cfg = function_exists('mrd_config') ? mrd_config() : array();
    $assetBase = isset($cfg['asset_base']) ? rtrim((string)$cfg['asset_base'], '/') : '';

    $cdn = '';
    if (!empty($meta['avatarCdn'])) {
        $cdn = trim((string)$meta['avatarCdn']);
    } elseif (!empty($meta['avatar_cdn'])) {
        $cdn = trim((string)$meta['avatar_cdn']);
    }

    $image = '';
    if (!empty($meta['avatarImage'])) {
        $image = trim((string)$meta['avatarImage']);
    } elseif (!empty($meta['avatar_image'])) {
        $image = trim((string)$meta['avatar_image']);
    }

    // 1) 显式 CDN
    if ($cdn !== '' && preg_match('#^https?://#i', $cdn)) {
        return array(
            'src' => $cdn,
            'source' => 'cdn',
            'image' => $image !== '' ? $image : null,
            'cdn' => $cdn,
        );
    }

    // 2) avatarImage 本身是完整 URL → 当 CDN 用
    if ($image !== '' && preg_match('#^https?://#i', $image)) {
        return array(
            'src' => $image,
            'source' => 'cdn',
            'image' => $image,
            'cdn' => $image,
        );
    }

    // 3) 本地 asset/{file}
    if ($image !== '') {
        $file = basename(str_replace(array('\\', "\0"), '', $image));
        if ($file !== '' && $file !== '.' && $file !== '..') {
            $localPath = mrd_role_dir($slug) . '/asset/' . $file;
            if (is_file($localPath)) {
                $rel = '/roles/' . $slug . '/asset/' . rawurlencode($file);
                // rawurlencode 会编码中文；对纯 ascii 文件名保持可读
                if (preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
                    $rel = '/roles/' . $slug . '/asset/' . $file;
                }
                $src = $assetBase !== '' ? ($assetBase . $rel) : $rel;
                return array(
                    'src' => $src,
                    'source' => 'local',
                    'image' => $file,
                    'cdn' => $cdn !== '' ? $cdn : null,
                );
            }
        }
    }

    return array(
        'src' => null,
        'source' => 'text',
        'image' => $image !== '' ? $image : null,
        'cdn' => $cdn !== '' ? $cdn : null,
    );
}

/**
 * 解析单个角色为前端卡片结构。
 * @return array|null
 */
function mrd_load_role($slug) {
    if (!mrd_role_exists($slug)) {
        return null;
    }
    $raw = mrd_read_role_file($slug, 'config.md');
    if ($raw === '') {
        return null;
    }
    $parsed = mrd_parse_frontmatter($raw);
    $m = $parsed['meta'];
    $name = isset($m['name']) ? (string)$m['name'] : $slug;
    $category = isset($m['category']) ? (string)$m['category'] : '未分类';
    $avatar = isset($m['avatar']) ? (string)$m['avatar'] : mb_substr($name, 0, 1, 'UTF-8');
    $avatarColor = isset($m['avatarColor'])
        ? (string)$m['avatarColor']
        : 'linear-gradient(135deg,#94a3b8,#64748b)';
    $tagline = isset($m['tagline']) ? (string)$m['tagline'] : '';
    $description = isset($m['description']) ? (string)$m['description'] : trim($parsed['body']);
    $tags = isset($m['tags']) && is_array($m['tags']) ? $m['tags'] : array();
    $featured = !empty($m['featured']);
    $order = isset($m['order']) ? (int)$m['order'] : 100;

    $avatarResolved = mrd_resolve_avatar_src($slug, $m);

    return array(
        'id' => $slug,
        'slug' => $slug,
        'name' => $name,
        'category' => $category,
        'avatar' => $avatar,
        'avatarColor' => $avatarColor,
        'avatarImage' => $avatarResolved['image'],
        'avatarCdn' => $avatarResolved['cdn'],
        'avatarSrc' => $avatarResolved['src'],
        'avatarSource' => $avatarResolved['source'],
        'tagline' => $tagline,
        'description' => $description,
        'tags' => $tags,
        'featured' => $featured,
        'order' => $order,
        'has_persona' => is_file(mrd_role_dir($slug) . '/persona.json'),
        'has_quotes' => is_file(mrd_role_dir($slug) . '/quotes.json'),
    );
}

/** 扫描 roles/ 下所有角色 */
function mrd_list_roles() {
    $list = array();
    if (!is_dir(MRD_ROLES)) {
        return $list;
    }
    $dirs = scandir(MRD_ROLES);
    if ($dirs === false) {
        return $list;
    }
    foreach ($dirs as $d) {
        if ($d === '.' || $d === '..') {
            continue;
        }
        if (!is_dir(MRD_ROLES . '/' . $d)) {
            continue;
        }
        if (!mrd_slug_ok($d)) {
            continue;
        }
        $role = mrd_load_role($d);
        if ($role !== null) {
            $list[] = $role;
        }
    }
    usort($list, function ($a, $b) {
        if ($a['featured'] !== $b['featured']) {
            return $a['featured'] ? -1 : 1;
        }
        if ($a['order'] !== $b['order']) {
            return $a['order'] - $b['order'];
        }
        return strcmp($a['name'], $b['name']);
    });
    return $list;
}

function mrd_load_prompt($slug) {
    return trim(mrd_read_role_file($slug, 'prompt.md'));
}

/**
 * 解析 qa.md → array( array('keys'=>[], 'answer'=>string), ... )
 */
function mrd_parse_qa($text) {
    $items = array();
    if ($text === '') {
        return $items;
    }
    // 按 ## Q: 分段
    $parts = preg_split('/^##\s*Q\s*:\s*/mi', $text);
    if ($parts === false) {
        return $items;
    }
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        // 第一行是关键词，随后找 A:
        if (!preg_match('/^(.*?)\r?\n+A\s*:\s*(.*)$/is', $part, $m)) {
            continue;
        }
        $keysRaw = trim($m[1]);
        $answer = trim($m[2]);
        // 去掉答案中可能混入的下一个标题残留（已分段则不需要）
        $keys = preg_split('/[|｜,，]/u', $keysRaw);
        $keysClean = array();
        foreach ($keys as $k) {
            $k = trim($k);
            if ($k !== '') {
                $keysClean[] = $k;
            }
        }
        if (empty($keysClean) || $answer === '') {
            continue;
        }
        $items[] = array('keys' => $keysClean, 'answer' => $answer);
    }
    return $items;
}

function mrd_load_qa($slug) {
    return mrd_parse_qa(mrd_read_role_file($slug, 'qa.md'));
}

/** 将 meta 数组序列化为 frontmatter 文本 */
function mrd_build_frontmatter($meta) {
    $lines = array('---');
    foreach ($meta as $k => $v) {
        if (is_bool($v)) {
            $lines[] = $k . ': ' . ($v ? 'true' : 'false');
        } elseif (is_array($v)) {
            $lines[] = $k . ': ' . implode(', ', $v);
        } else {
            $lines[] = $k . ': ' . str_replace(array("\r", "\n"), ' ', (string)$v);
        }
    }
    $lines[] = '---';
    $lines[] = '';
    return implode("\n", $lines);
}
