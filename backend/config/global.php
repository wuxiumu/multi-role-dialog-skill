<?php
/**
 * 全局配置（可被后台 config-set 覆盖写入同文件的可写段，
 * 敏感项也可放 config.local.php，已加入 .gitignore）。
 */
$cfg = array(
    // 站点
    'site_name'       => '角色场',
    'site_tagline'    => '多角色克隆对话 · MD 驱动',
    'disclaimer'      => '本服务为角色扮演/娱乐助手，不构成投资、医疗、法律等专业建议。',

    // 大模型：多供应商（OpenAI 兼容 /chat/completions）
    // 密钥请放 config.local.php 或环境变量，勿写进仓库
    'llm_enabled'     => true,
    'llm_timeout'     => 25,
    'llm_max_history' => 10,
    'llm_default_provider' => 'none',
    'llm_providers'   => array(
        'deepseek' => array(
            'name' => 'DeepSeek',
            'enabled' => true,
            'api_key' => '',
            'base_url' => 'https://api.deepseek.com',
            'model' => 'deepseek-chat',
        ),
        'qwen' => array(
            'name' => '通义千问',
            'enabled' => true,
            'api_key' => '',
            'base_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
            'model' => 'qwen-plus',
        ),
    ),
    // 旧字段保留兼容（单模型时代）；有 llm_providers 时优先用 providers
    'llm_endpoint'    => '',
    'llm_model'       => '',
    'llm_key'         => '',

    // 前台用户登录（演示：任意非空账号密码可登录；正式可改 demo_open=false 并用 users）
    'demo_open'       => true,
    'users'           => array(
        // 'admin' => 'change-me',
    ),
    'token_ttl'       => 86400 * 7,   // Token 有效期（秒）

    // 轻量后台
    'admin_user'      => 'admin',
    'admin_password'  => 'admin123',  // 生产务必修改
    'admin_session'   => 'mrd_admin',

    // CORS
    'cors_origin'     => '*',

    // 角色静态资源前缀：空=走当前后端同源 /roles/{slug}/asset/
    // 生产可设 CDN 根，如 https://cdn.example.com （最终拼 /roles/.../asset/xxx）
    'asset_base'      => '',
);

// 覆盖顺序由 core/bootstrap.php 处理：
// global.php → settings.json（后台可写）→ config.local.php（最高优先级，不入库）
return $cfg;
