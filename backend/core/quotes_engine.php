<?php
/**
 * Quotes mock 引擎：从角色 quotes.json 按主题命中语录并拼装回复。
 * 自 yingxue/api/mock_engine.php 迁入，按角色隔离；兼容 PHP 7.4。
 * 会话内去重：同一 session 近期用过的语录 id 不再重复。
 */

/** 特殊主题：不参与主句打分，只用于拼装 */
function mrd_quotes_meta_topics() {
    return array('opener' => true, 'catchphrase' => true, 'fallback' => true);
}

function mrd_quotes_seen_dir() {
    $dir = MRD_DATA . '/session_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function mrd_quotes_session_key($slug, $session) {
    $slug = preg_replace('/[^a-z0-9\-]/', '', (string)$slug);
    $session = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$session);
    return substr(md5($slug . '|' . ($session === '' ? 'anon' : $session)), 0, 16);
}

function mrd_quotes_load_seen($slug, $session) {
    $f = mrd_quotes_seen_dir() . '/' . mrd_quotes_session_key($slug, $session) . '.json';
    if (!is_file($f)) {
        return array();
    }
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? $j : array();
}

function mrd_quotes_save_seen($slug, $session, $seen) {
    $f = mrd_quotes_seen_dir() . '/' . mrd_quotes_session_key($slug, $session) . '.json';
    @file_put_contents($f, json_encode(array_slice($seen, 0, 120, true)), LOCK_EX);
}

function mrd_quotes_line_id($l) {
    if (isset($l['id']) && $l['id'] !== '') {
        return $l['id'];
    }
    return '#' . substr(md5(isset($l['text']) ? $l['text'] : ''), 0, 10);
}

function mrd_quotes_topic_map($data) {
    $m = array();
    if (!empty($data['topics']) && is_array($data['topics'])) {
        foreach ($data['topics'] as $t) {
            if (isset($t['id'])) {
                $m[$t['id']] = $t;
            }
        }
    }
    return $m;
}

/**
 * 从一组语录里挑一条本会话没用过的；全用过则清零重新轮换。
 */
function mrd_quotes_pick_line($lines, &$seen) {
    if (empty($lines) || !is_array($lines)) {
        return array('text' => '', 'id' => '');
    }
    $remaining = array();
    foreach ($lines as $l) {
        $id = mrd_quotes_line_id($l);
        if (!isset($seen[$id])) {
            $remaining[] = $l;
        }
    }
    if (count($remaining) === 0) {
        foreach ($lines as $l) {
            unset($seen[mrd_quotes_line_id($l)]);
        }
        $remaining = $lines;
    }
    $pick = $remaining[array_rand($remaining)];
    $pid = mrd_quotes_line_id($pick);
    $seen[$pid] = isset($seen[$pid]) ? $seen[$pid] + 1 : 1;
    return $pick;
}

function mrd_quotes_score_line($line, $msg) {
    $score = 0;
    if (!empty($line['keywords']) && is_array($line['keywords'])) {
        foreach ($line['keywords'] as $kw) {
            if ($kw !== '' && strpos($msg, $kw) !== false) {
                $score += 2;
            }
        }
    }
    if (isset($line['text']) && $line['text'] !== '' && strpos($msg, $line['text']) !== false) {
        $score += 3;
    }
    return $score;
}

function mrd_quotes_is_invest($msg) {
    $kw = array(
        '比特币', 'btc', '币', '行情', '走势', '涨跌', '买', '卖', '抄底', '逃顶',
        '加仓', '减仓', '仓位', '点位', '止盈', '止损', '套牢', '被套', '割肉',
        '投资', '理财', '币种', 'k线', '牛熊', '牛市', '熊市',
    );
    foreach ($kw as $k) {
        if ($k !== '' && strpos($msg, $k) !== false) {
            return true;
        }
    }
    return false;
}

function mrd_quotes_is_identity($msg) {
    return (bool)preg_match(
        '/你(是不是|是|是本人|是真人|就是)|孙宇晨|是真人吗|真的吗|官方|本尊|你谁|你是谁|who are you/iu',
        $msg
    );
}

function mrd_quotes_identity_reply($slug, $persona, $role) {
    $name = isset($persona['name']) ? $persona['name'] : (isset($role['name']) ? $role['name'] : $slug);
    if ($slug === 'yingxue') {
        $text = '朋友，我把话放这儿——我谁也不是，我只是一个把网络段子焊进 DNA 的戏仿机器人“赢学”，'
            . '跟孙宇晨本人没有任何关系，也不代表任何机构。'
            . '你要真想问他本尊，出门左转官方账号。至于我这儿，只聊格局，仅供娱乐。';
    } else {
        $text = "我是「{$name}」——角色扮演助手，不是真人本人，也不代表任何官方机构。仅供娱乐/演示。";
    }
    return array(
        'reply' => $text,
        'matched_topic' => 'identity',
        'quote_id' => 'guard-identity',
        'risk' => false,
        'source' => 'quotes',
        'matched' => true,
        'score' => 99,
        'keys' => array('identity'),
    );
}

/**
 * 用 quotes.json 生成回复。角色无语录库时返回 null。
 *
 * @return array|null {reply, matched_topic, quote_id, risk, source, matched, score, keys}
 */
function mrd_quotes_generate($slug, $msg, $session = 'anon') {
    if (!mrd_role_has_json($slug, 'quotes.json')) {
        return null;
    }
    $data = mrd_load_role_json($slug, 'quotes.json');
    if ($data === null || empty($data['topics'])) {
        return null;
    }

    $msg = trim((string)$msg);
    $role = mrd_load_role($slug);
    $persona = mrd_load_role_json($slug, 'persona.json');
    if (!is_array($persona)) {
        $persona = array();
    }

    if (mrd_quotes_is_identity($msg)) {
        return mrd_quotes_identity_reply($slug, $persona, $role);
    }

    $topics = mrd_quotes_topic_map($data);
    $meta = mrd_quotes_meta_topics();
    $seen = mrd_quotes_load_seen($slug, $session);

    $contentIds = array();
    foreach ($topics as $tid => $t) {
        if (!isset($meta[$tid])) {
            $contentIds[] = $tid;
        }
    }

    $bestId = null;
    $bestScore = 0;
    $bestLine = null;

    // 第一轮：未用过的语录
    foreach ($contentIds as $tid) {
        $lines = isset($topics[$tid]['lines']) ? $topics[$tid]['lines'] : array();
        foreach ($lines as $line) {
            if (isset($seen[mrd_quotes_line_id($line)])) {
                continue;
            }
            $score = mrd_quotes_score_line($line, $msg);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $tid;
                $bestLine = $line;
            }
        }
    }
    // 第二轮：允许复用
    if ($bestLine === null) {
        foreach ($contentIds as $tid) {
            $lines = isset($topics[$tid]['lines']) ? $topics[$tid]['lines'] : array();
            foreach ($lines as $line) {
                $score = mrd_quotes_score_line($line, $msg);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestId = $tid;
                    $bestLine = $line;
                }
            }
        }
    }

    $risk = false;
    if ($bestScore <= 0 && mrd_quotes_is_invest($msg) && isset($topics['invest'])) {
        $bestId = 'invest';
        $bestLine = mrd_quotes_pick_line($topics['invest']['lines'], $seen);
        $bestScore = 1;
    }

    $openerLine = isset($topics['opener'])
        ? mrd_quotes_pick_line($topics['opener']['lines'], $seen)
        : array('text' => '');
    $tailLine = isset($topics['catchphrase'])
        ? mrd_quotes_pick_line($topics['catchphrase']['lines'], $seen)
        : array('text' => '');
    $fallback = isset($topics['fallback'])
        ? mrd_quotes_pick_line($topics['fallback']['lines'], $seen)
        : array('text' => '朋友，一句话：格局。', 'id' => 'fb');

    if ($bestLine === null || $bestScore <= 0) {
        $main = isset($fallback['text']) ? $fallback['text'] : '……';
        $matched = 'fallback';
        $qid = mrd_quotes_line_id($fallback);
    } else {
        $main = $bestLine['text'];
        $matched = $bestId;
        $qid = mrd_quotes_line_id($bestLine);
        if (!isset($seen[$qid])) {
            $seen[$qid] = 1;
        } else {
            $seen[$qid] += 1;
        }
    }

    $op = isset($openerLine['text']) ? $openerLine['text'] : '';
    if ($op !== '' && function_exists('mb_strpos') && mb_strpos($main, '朋友') === 0) {
        $op = '';
    }

    $reply = $op . $main;
    if (isset($tailLine['text']) && $tailLine['text'] !== '' && mt_rand(1, 100) <= 60) {
        $reply .= ' ' . $tailLine['text'];
    }

    if ($matched === 'invest' || ($slug === 'yingxue' && mrd_quotes_is_invest($msg))) {
        $reply .= "\n\n（友情提醒：以上是段子，不构成任何投资建议。钱是你的，锅别让我背。）";
        $risk = true;
    }

    mrd_quotes_save_seen($slug, $session, $seen);

    $keys = array();
    if ($bestLine !== null && !empty($bestLine['keywords']) && is_array($bestLine['keywords'])) {
        foreach ($bestLine['keywords'] as $kw) {
            if ($kw !== '' && strpos($msg, $kw) !== false) {
                $keys[] = $kw;
            }
        }
    }

    return array(
        'reply' => $reply,
        'matched_topic' => $matched,
        'quote_id' => $qid,
        'risk' => $risk,
        'source' => 'quotes',
        'matched' => ($matched !== 'fallback'),
        'score' => $bestScore,
        'keys' => $keys,
    );
}

/**
 * 统一离线回复：有 quotes.json 走语录引擎，否则走 qa.md。
 */
function mrd_offline_reply($slug, $message, $session = 'anon') {
    $q = mrd_quotes_generate($slug, $message, $session);
    if ($q !== null) {
        $q['trace'] = mrd_build_reply_trace($slug, $q, 'quotes');
        return $q;
    }
    $role = mrd_load_role($slug);
    $qa = mrd_load_qa($slug);
    $result = mrd_match_reply($message, $qa, $role);
    $out = array(
        'reply' => $result['reply'],
        'matched_topic' => $result['matched'] ? 'qa' : 'fallback',
        'quote_id' => '',
        'risk' => false,
        'source' => 'match',
        'matched' => $result['matched'],
        'score' => $result['score'],
        'keys' => $result['keys'],
    );
    $out['trace'] = mrd_build_reply_trace($slug, $out, 'qa_md');
    return $out;
}

/**
 * 角色配置与语料统计（供调试面板 / API 附带）。
 */
function mrd_role_asset_stats($slug) {
    $hasQuotes = mrd_role_has_json($slug, 'quotes.json');
    $hasPersona = mrd_role_has_json($slug, 'persona.json');
    $hasConfig = is_file(mrd_role_dir($slug) . '/config.md');
    $hasPrompt = is_file(mrd_role_dir($slug) . '/prompt.md');
    $hasQa = is_file(mrd_role_dir($slug) . '/qa.md');

    $quotesTotal = 0;
    $topics = array();
    $contentLines = 0;
    $metaLines = 0;
    if ($hasQuotes) {
        $data = mrd_load_role_json($slug, 'quotes.json');
        $meta = mrd_quotes_meta_topics();
        if (is_array($data) && !empty($data['topics'])) {
            foreach ($data['topics'] as $t) {
                $n = isset($t['lines']) && is_array($t['lines']) ? count($t['lines']) : 0;
                $tid = isset($t['id']) ? $t['id'] : '';
                $topics[] = array(
                    'id' => $tid,
                    'name' => isset($t['name']) ? $t['name'] : $tid,
                    'count' => $n,
                    'kind' => isset($meta[$tid]) ? 'meta' : 'content',
                );
                $quotesTotal += $n;
                if (isset($meta[$tid])) {
                    $metaLines += $n;
                } else {
                    $contentLines += $n;
                }
            }
        }
    }

    $qaItems = mrd_load_qa($slug);
    $qaCount = count($qaItems);

    $persona = $hasPersona ? mrd_load_role_json($slug, 'persona.json') : null;
    $qq = (is_array($persona) && isset($persona['quick_questions']) && is_array($persona['quick_questions']))
        ? count($persona['quick_questions']) : 0;
    $qp = (is_array($persona) && isset($persona['quick_pool']) && is_array($persona['quick_pool']))
        ? count($persona['quick_pool']) : 0;

    // 离线引擎优先级说明
    if ($hasQuotes) {
        $enginePriority = 'quotes.json（语录 mock 引擎）';
        $engineId = 'quotes';
    } elseif ($qaCount > 0) {
        $enginePriority = 'qa.md（关键词匹配）';
        $engineId = 'qa_md';
    } else {
        $enginePriority = '无语料（将走兜底文案）';
        $engineId = 'fallback';
    }

    $promptSource = 'none';
    if ($hasPersona && is_array($persona) && !empty($persona['system_prompt'])) {
        $promptSource = 'persona.json.system_prompt';
    } elseif ($hasPrompt && trim(mrd_load_prompt($slug)) !== '') {
        $promptSource = 'prompt.md';
    }

    return array(
        'role' => $slug,
        'files' => array(
            'config.md' => $hasConfig,
            'prompt.md' => $hasPrompt,
            'qa.md' => $hasQa,
            'persona.json' => $hasPersona,
            'quotes.json' => $hasQuotes,
        ),
        'quotes' => array(
            'enabled' => $hasQuotes,
            'total_lines' => $quotesTotal,
            'content_lines' => $contentLines,
            'meta_lines' => $metaLines,
            'topics' => $topics,
            'topic_count' => count($topics),
        ),
        'qa_md' => array(
            'enabled' => $hasQa,
            'entry_count' => $qaCount,
        ),
        'persona' => array(
            'enabled' => $hasPersona,
            'quick_questions' => $qq,
            'quick_pool' => $qp,
            'recommend_total' => $qq + $qp,
            'mode' => (is_array($persona) && isset($persona['mode'])) ? $persona['mode'] : '',
            'prompt_source' => $promptSource,
        ),
        'engine' => array(
            'id' => $engineId,
            'label' => $enginePriority,
            'priority' => array('quotes.json', 'qa.md', 'frontend_local_fallback'),
        ),
    );
}

/**
 * 单次回复的调用追踪。
 * @param string $engineUsed quotes|qa_md|llm|local
 */
function mrd_build_reply_trace($slug, $result, $engineUsed) {
    $stats = mrd_role_asset_stats($slug);
    $logicMap = array(
        'quotes' => array(
            'id' => 'quotes_engine',
            'label' => '语录引擎（quotes.json）',
            'config' => 'json',
            'file' => 'roles/' . $slug . '/quotes.json',
        ),
        'qa_md' => array(
            'id' => 'qa_match',
            'label' => '关键词匹配（qa.md）',
            'config' => 'md',
            'file' => 'roles/' . $slug . '/qa.md',
        ),
        'llm' => array(
            'id' => 'llm_api',
            'label' => '大模型 API',
            'config' => 'api',
            'file' => $stats['persona']['prompt_source'],
        ),
        'local' => array(
            'id' => 'frontend_local',
            'label' => '前端本地兜底（默认硬编码）',
            'config' => 'default',
            'file' => 'frontend/src/api/chat.js',
        ),
    );
    $logic = isset($logicMap[$engineUsed]) ? $logicMap[$engineUsed] : $logicMap['local'];

    return array(
        'at' => date('c'),
        'engine' => $engineUsed,
        'logic' => $logic,
        'hit' => array(
            'matched' => !empty($result['matched']),
            'matched_topic' => isset($result['matched_topic']) ? $result['matched_topic'] : '',
            'quote_id' => isset($result['quote_id']) ? $result['quote_id'] : '',
            'score' => isset($result['score']) ? $result['score'] : 0,
            'keys' => isset($result['keys']) ? $result['keys'] : array(),
            'risk' => !empty($result['risk']),
            'source_field' => isset($result['source']) ? $result['source'] : $engineUsed,
        ),
        'assets' => $stats,
    );
}
