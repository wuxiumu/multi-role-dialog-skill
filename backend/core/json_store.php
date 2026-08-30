<?php
/**
 * 角色级 JSON 配置：persona.json / quotes.json
 * 简单元数据继续用 MD；人格卡与语录库等结构化数据用 JSON。
 */

function mrd_role_json_path($slug, $name) {
    return mrd_role_dir($slug) . '/' . $name;
}

function mrd_role_json_allowed($name) {
    return in_array($name, array('persona.json', 'quotes.json'), true);
}

/** @return array|null 文件不存在返回 null；损坏时抛出失败由调用方处理 */
function mrd_load_role_json($slug, $name) {
    if (!mrd_role_exists($slug) || !mrd_role_json_allowed($name)) {
        return null;
    }
    $path = mrd_role_json_path($slug, $name);
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

/**
 * 原子写入角色 JSON（自动 .bak）。
 * @return true|string
 */
function mrd_save_role_json($slug, $name, $data) {
    if (!mrd_role_exists($slug)) {
        return '角色不存在';
    }
    if (!mrd_role_json_allowed($name)) {
        return '不允许的 JSON 文件';
    }
    if (!is_array($data)) {
        return '数据必须是对象/数组';
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return 'JSON 编码失败';
    }
    $json .= "\n";
    $path = mrd_role_json_path($slug, $name);
    if (!mrd_safe_write($path, $json)) {
        return '写入失败：' . $name;
    }
    return true;
}

function mrd_role_has_json($slug, $name) {
    return mrd_role_exists($slug) && is_file(mrd_role_json_path($slug, $name));
}

/** 默认人格卡骨架（复杂配置用 JSON） */
function mrd_default_persona($meta = array()) {
    $name = isset($meta['name']) ? (string)$meta['name'] : '新角色';
    $tagline = isset($meta['tagline']) ? (string)$meta['tagline'] : '';
    return array(
        'name' => $name,
        'alias' => '',
        'tagline' => $tagline,
        'system_prompt' => "你是「{$name}」。请始终以该角色的口吻回复用户。",
        'style_rules' => array(),
        'catchphrases' => array(),
        'guardrails' => array(
            '不冒充真人官方',
            '不提供违法或伤害性内容',
        ),
        'disclaimer_text' => '本角色为娱乐/演示用的扮演助手，不代表任何真实机构。',
        'quick_questions' => array('你好', '你是谁？'),
        'quick_pool' => array(),
        'mode' => 'match',
    );
}

/** 默认空语录库（带常见主题壳，便于后台录入） */
function mrd_default_quotes() {
    return array(
        'version' => 1,
        'updated' => date('Y-m-d'),
        'disclaimer' => '',
        'topics' => array(
            array('id' => 'general', 'name' => '通用', 'lines' => array()),
            array('id' => 'catchphrase', 'name' => '口头禅尾巴', 'lines' => array()),
            array('id' => 'opener', 'name' => '开场白', 'lines' => array()),
            array('id' => 'fallback', 'name' => '兜底', 'lines' => array()),
        ),
    );
}

/**
 * 确保角色具备某 JSON；不存在则写默认。
 * @return true|string
 */
function mrd_ensure_role_json($slug, $name) {
    if (mrd_role_has_json($slug, $name)) {
        return true;
    }
    $role = mrd_load_role($slug);
    if ($name === 'persona.json') {
        return mrd_save_role_json($slug, $name, mrd_default_persona($role ?: array()));
    }
    if ($name === 'quotes.json') {
        return mrd_save_role_json($slug, $name, mrd_default_quotes());
    }
    return '未知文件';
}

/* ---------- quotes CRUD（从 yingxue/api/admin.php 迁入，按角色隔离） ---------- */

function mrd_quotes_norm_keywords($kw) {
    if (is_string($kw)) {
        $kw = preg_split('/[,，\s]+/u', trim($kw));
    }
    if (!is_array($kw)) {
        return array();
    }
    $out = array();
    foreach ($kw as $k) {
        $k = trim((string)$k);
        if ($k !== '') {
            $out[] = $k;
        }
    }
    return array_values(array_unique($out));
}

function mrd_quotes_find_topic(&$data, $topicId) {
    if (!isset($data['topics']) || !is_array($data['topics'])) {
        return -1;
    }
    foreach ($data['topics'] as $i => $t) {
        if (isset($t['id']) && $t['id'] === $topicId) {
            return $i;
        }
    }
    return -1;
}

function mrd_quotes_content_prefix($topicId) {
    $map = array(
        'invest' => 'invest-', 'trend' => 'trend-', 'marketing' => 'mk-',
        'selfmock' => 'sm-', 'life' => 'life-', 'catchphrase' => 'cp-',
        'opener' => 'op-', 'fallback' => 'fb-', 'general' => 'gen-',
    );
    return isset($map[$topicId]) ? $map[$topicId] : ($topicId . '-');
}

function mrd_quotes_next_id(&$data, $topicId) {
    $prefix = mrd_quotes_content_prefix($topicId);
    $max = 0;
    $ti = mrd_quotes_find_topic($data, $topicId);
    if ($ti >= 0 && isset($data['topics'][$ti]['lines']) && is_array($data['topics'][$ti]['lines'])) {
        foreach ($data['topics'][$ti]['lines'] as $l) {
            $id = isset($l['id']) ? $l['id'] : '';
            if (strpos($id, $prefix) === 0) {
                $n = (int)substr($id, strlen($prefix));
                if ($n > $max) {
                    $max = $n;
                }
            }
        }
    }
    return $prefix . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

function mrd_quotes_topics_summary($data) {
    $out = array();
    if (!isset($data['topics']) || !is_array($data['topics'])) {
        return $out;
    }
    foreach ($data['topics'] as $t) {
        $out[] = array(
            'id' => $t['id'],
            'name' => isset($t['name']) ? $t['name'] : $t['id'],
            'count' => isset($t['lines']) && is_array($t['lines']) ? count($t['lines']) : 0,
        );
    }
    return $out;
}

/**
 * 分页列出语录。
 * @return array|string 成功返回结构，失败返回错误字符串
 */
function mrd_quotes_list($slug, $page = 1, $perPage = 10, $topicFilter = '', $q = '') {
    $data = mrd_load_role_json($slug, 'quotes.json');
    if ($data === null || !isset($data['topics']) || !is_array($data['topics'])) {
        return 'quotes.json 不存在或格式损坏';
    }
    $page = max(1, (int)$page);
    $perPage = (int)$perPage;
    if ($perPage < 1) {
        $perPage = 10;
    }
    if ($perPage > 100) {
        $perPage = 100;
    }
    $topicFilter = trim((string)$topicFilter);
    $q = trim((string)$q);

    $flat = array();
    foreach ($data['topics'] as $t) {
        if ($topicFilter !== '' && $t['id'] !== $topicFilter) {
            continue;
        }
        $lines = isset($t['lines']) && is_array($t['lines']) ? $t['lines'] : array();
        foreach ($lines as $l) {
                if ($q !== '') {
                    $hay = (isset($l['text']) ? $l['text'] : '')
                        . ' ' . (isset($l['id']) ? $l['id'] : '')
                        . ' ' . implode(' ', isset($l['keywords']) && is_array($l['keywords']) ? $l['keywords'] : array());
                    $pos = function_exists('mb_strpos') ? mb_strpos($hay, $q) : strpos($hay, $q);
                    if ($pos === false) {
                        continue;
                    }
                }
            $flat[] = array(
                'topic_id' => $t['id'],
                'topic_name' => isset($t['name']) ? $t['name'] : $t['id'],
                'id' => isset($l['id']) ? $l['id'] : '',
                'text' => isset($l['text']) ? $l['text'] : '',
                'keywords' => isset($l['keywords']) && is_array($l['keywords']) ? $l['keywords'] : array(),
                'source' => isset($l['source']) ? $l['source'] : '',
            );
        }
    }
    $total = count($flat);
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $items = array_slice($flat, ($page - 1) * $perPage, $perPage);

    return array(
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'items' => $items,
        'topics' => mrd_quotes_topics_summary($data),
    );
}

function mrd_quotes_add($slug, $topicId, $text, $keywords = array(), $source = '') {
    $text = trim((string)$text);
    $topicId = trim((string)$topicId);
    if ($text === '') {
        return '语录内容不能为空';
    }
    if (mb_strlen($text) > 500) {
        return '语录太长（>500字）';
    }
    $data = mrd_load_role_json($slug, 'quotes.json');
    if ($data === null || !isset($data['topics'])) {
        return 'quotes.json 不存在或格式损坏';
    }
    $ti = mrd_quotes_find_topic($data, $topicId);
    if ($ti < 0) {
        return '主题不存在：' . $topicId;
    }
    $item = array(
        'id' => mrd_quotes_next_id($data, $topicId),
        'text' => $text,
        'keywords' => mrd_quotes_norm_keywords($keywords),
    );
    $source = trim((string)$source);
    if ($source !== '') {
        $item['source'] = $source;
    }
    if (!isset($data['topics'][$ti]['lines']) || !is_array($data['topics'][$ti]['lines'])) {
        $data['topics'][$ti]['lines'] = array();
    }
    $data['topics'][$ti]['lines'][] = $item;
    $data['updated'] = date('Y-m-d');
    $res = mrd_save_role_json($slug, 'quotes.json', $data);
    if ($res !== true) {
        return $res;
    }
    $item['topic_id'] = $topicId;
    $item['topic_name'] = isset($data['topics'][$ti]['name']) ? $data['topics'][$ti]['name'] : $topicId;
    return $item;
}

function mrd_quotes_update($slug, $topicId, $qid, $fields) {
    $topicId = trim((string)$topicId);
    $qid = trim((string)$qid);
    $data = mrd_load_role_json($slug, 'quotes.json');
    if ($data === null || !isset($data['topics'])) {
        return 'quotes.json 不存在或格式损坏';
    }
    $ti = mrd_quotes_find_topic($data, $topicId);
    if ($ti < 0) {
        return '主题不存在';
    }
    $found = -1;
    foreach ($data['topics'][$ti]['lines'] as $i => $l) {
        if (isset($l['id']) && $l['id'] === $qid) {
            $found = $i;
            break;
        }
    }
    if ($found < 0) {
        return '语录不存在：' . $qid;
    }
    $line = $data['topics'][$ti]['lines'][$found];
    if (array_key_exists('text', $fields)) {
        $text = trim((string)$fields['text']);
        if ($text === '') {
            return '语录内容不能为空';
        }
        if (mb_strlen($text) > 500) {
            return '语录太长（>500字）';
        }
        $line['text'] = $text;
    }
    if (array_key_exists('keywords', $fields)) {
        $line['keywords'] = mrd_quotes_norm_keywords($fields['keywords']);
    }
    if (array_key_exists('source', $fields)) {
        $source = trim((string)$fields['source']);
        if ($source === '') {
            unset($line['source']);
        } else {
            $line['source'] = $source;
        }
    }
    $newTopic = isset($fields['new_topic_id']) ? trim((string)$fields['new_topic_id']) : '';
    if ($newTopic !== '' && $newTopic !== $topicId) {
        $nti = mrd_quotes_find_topic($data, $newTopic);
        if ($nti < 0) {
            return '目标主题不存在';
        }
        array_splice($data['topics'][$ti]['lines'], $found, 1);
        $line['id'] = mrd_quotes_next_id($data, $newTopic);
        $data['topics'][$nti]['lines'][] = $line;
        $topicId = $newTopic;
    } else {
        $data['topics'][$ti]['lines'][$found] = $line;
    }
    $data['updated'] = date('Y-m-d');
    $res = mrd_save_role_json($slug, 'quotes.json', $data);
    if ($res !== true) {
        return $res;
    }
    return array(
        'topic_id' => $topicId,
        'id' => $line['id'],
        'text' => $line['text'],
        'keywords' => isset($line['keywords']) ? $line['keywords'] : array(),
        'source' => isset($line['source']) ? $line['source'] : '',
    );
}

function mrd_quotes_delete($slug, $topicId, $qid) {
    $topicId = trim((string)$topicId);
    $qid = trim((string)$qid);
    $data = mrd_load_role_json($slug, 'quotes.json');
    if ($data === null || !isset($data['topics'])) {
        return 'quotes.json 不存在或格式损坏';
    }
    $ti = mrd_quotes_find_topic($data, $topicId);
    if ($ti < 0) {
        return '主题不存在';
    }
    $found = -1;
    foreach ($data['topics'][$ti]['lines'] as $i => $l) {
        if (isset($l['id']) && $l['id'] === $qid) {
            $found = $i;
            break;
        }
    }
    if ($found < 0) {
        return '语录不存在：' . $qid;
    }
    array_splice($data['topics'][$ti]['lines'], $found, 1);
    $data['updated'] = date('Y-m-d');
    $res = mrd_save_role_json($slug, 'quotes.json', $data);
    if ($res !== true) {
        return $res;
    }
    return $qid;
}

/**
 * 将表单多行文本拆成数组（空行忽略）。
 */
function mrd_lines_to_list($text) {
    $parts = preg_split('/\r?\n/', (string)$text);
    $out = array();
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

function mrd_list_to_lines($arr) {
    if (!is_array($arr)) {
        return '';
    }
    return implode("\n", $arr);
}
