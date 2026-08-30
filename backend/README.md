# 多角色对话后端（原生 PHP）

零框架、零 Composer。提供 REST API + 极简 MD 管理后台。

## 启动

```bash
# 推荐：仓库根目录
./dev.sh backend
# → http://127.0.0.1:8080

# 或手动
cd backend && php -S 127.0.0.1:8080 -t .
```

## API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/health.php` | 健康检查 |
| GET | `/api/role-list.php` | 角色列表 |
| POST | `/api/chat-match.php` | 游客关键词匹配 `{role,message}` |
| POST | `/api/chat-llm.php` | AI 对话（需 Bearer Token） |
| POST | `/api/auth.php` | 登录 `{name,password}` → token |
| GET | `/api/global-set.php` | 公开站点配置 |

统一响应：`{ "code": 200, "msg": "success", "data": {} }`

## 后台

- 地址：http://127.0.0.1:8080/admin/
- 默认账号：`admin` / `admin123`（请尽快修改）

## 角色 MD

每个角色一个目录 `roles/{slug}/`：

- `config.md` — frontmatter 元数据
- `prompt.md` — 大模型 system prompt
- `qa.md` — `## Q: 词1|词2` + `A: 回答`

## 配置

- `config/global.php` — 默认配置
- `config/settings.json` — 后台可写覆盖
- `config/config.local.php` — 本地密钥（勿提交），示例：

```php
<?php
return array(
    'llm_key' => 'sk-xxx',
    'llm_endpoint' => 'https://api.openai.com/v1/chat/completions',
    'llm_model' => 'gpt-4o-mini',
    'admin_password' => 'your-strong-password',
);
```
