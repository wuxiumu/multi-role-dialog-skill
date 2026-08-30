<?php
/**
 * 关键词模糊匹配引擎：对角色 qa.md 条目打分，返回最佳答案。
 */

/**
 * @param string $message 用户消息
 * @param array  $qaItems mrd_load_qa() 结果
 * @param array  $role    角色信息（用于兜底文案）
 * @return array{reply:string, matched:bool, score:int, keys:array}
 */
function mrd_match_reply($message, $qaItems, $role = null) {
    $message = trim((string)$message);
    $best = null;
    $bestScore = 0;

    foreach ($qaItems as $item) {
        $score = 0;
        $hitKeys = array();
        foreach ($item['keys'] as $key) {
            if ($key === '') {
                continue;
            }
            if (function_exists('mb_stripos')) {
                $pos = mb_stripos($message, $key, 0, 'UTF-8');
            } else {
                $pos = stripos($message, $key);
            }
            if ($pos !== false) {
                // 关键词越长分越高；命中位置靠前略加分
                $len = function_exists('mb_strlen') ? mb_strlen($key, 'UTF-8') : strlen($key);
                $score += 2 + $len;
                if ($pos === 0) {
                    $score += 1;
                }
                $hitKeys[] = $key;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $item;
            $best['_hit'] = $hitKeys;
        }
    }

    if ($best !== null && $bestScore > 0) {
        return array(
            'reply' => $best['answer'],
            'matched' => true,
            'score' => $bestScore,
            'keys' => isset($best['_hit']) ? $best['_hit'] : array(),
        );
    }

    $name = ($role && isset($role['name'])) ? $role['name'] : '助手';
    $snippet = $message;
    if (function_exists('mb_substr')) {
        $snippet = mb_substr($message, 0, 40, 'UTF-8');
    } else {
        $snippet = substr($message, 0, 40);
    }
    $fallback = "（离线兜底）我是{$name}。你说的「{$snippet}」我记下了——"
        . '可以换个说法，或试试问候、谢谢等常见问题。';

    return array(
        'reply' => $fallback,
        'matched' => false,
        'score' => 0,
        'keys' => array(),
    );
}
