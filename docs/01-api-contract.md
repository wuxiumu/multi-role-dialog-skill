# 01 · 后端 API 契约

对齐前端：`frontend/src/api/*`  
统一响应：`{ "code": 200, "msg": "success", "data": ... }`  
开发代理：Vite `/api` → `http://127.0.0.1:8080`

## 接口一览

| 方法 | 路径 | 鉴权 | 请求要点 | 成功 data 要点 |
|------|------|------|----------|----------------|
| GET | `/api/health.php` | — | — | `app`, `php`, `roles` |
| GET | `/api/role-list.php` | — | — | `{ list: Role[], total }` |
| GET | `/api/persona.php` | — | `?role=` | 人设、快捷提问、`disclaimer_text`、`mode` |
| GET | `/api/global-set.php` | — | — | `site_name`, `disclaimer`, `llm_enabled`, `demo_open` |
| POST | `/api/auth.php` | — | `{ name, password }` | `{ token, expires, user }` |
| GET | `/api/auth.php` | Bearer | — | 当前用户 |
| POST | `/api/auth.php` | Bearer | `{ action: "logout" }` | 注销 |
| POST | `/api/chat-match.php` | — | `{ role, message, session_id? }` | 离线语录/匹配；若有模型缓存则**优先展示最新缓存**，`variants` 可切回默认语录 |
| POST | `/api/chat-llm.php` | Bearer | `{ role, message, history?, provider?, bypass_cache?, session_id? }` | LLM / 缓存命中；`provider=none` 时不打上游但仍附带缓存变体 |
| GET/POST | `/api/chat-cache-variants.php` | — | `{ role, message }` 或 `{ role, question_hash }` | 同问题下 `variants`（含默认语录 + 各模型） |
| GET | `/api/llm-providers.php` | — | — | `{ llm_enabled, default, providers[] }`（不含密钥） |
| POST | `/api/llm-key-test.php` | Bearer | `mode: system\|manual` + 对应字段 | `{ ok, reply/error, model, api_key_masked, … }` |

### chat-llm / chat-match 共用字段

| 字段 | 说明 |
|------|------|
| `reply` | 当前展示文案（有缓存时默认为最新模型答案） |
| `source` | `llm` / `quotes` / `match` 等 |
| `from_cache` | 是否来自角色目录下的问题缓存 |
| `question_hash` | 规范化问题哈希（`roles/{slug}/cache/{hash}/`） |
| `variants[]` | 可切换答案列表 |
| `variant_index` | 默认选中下标 |
| `degraded` | LLM 失败降级为离线时为 `true` |
| `provider` / `provider_name` / `model` | 当前答案来源模型 |
| `debug` | 调试轨迹（可选展示） |

### variants[] 项

| 字段 | 说明 |
|------|------|
| `provider` | `default`（默认语录/匹配）或 `deepseek` / `qwen` 等 |
| `provider_name` | 展示名，如「默认语录」「DeepSeek」 |
| `model` | 模型名；默认答案为空 |
| `reply` | 正文 |
| `cached_at` | 模型缓存写入时间（ISO） |
| `from_cache` / `is_default` | 是否缓存 / 是否默认离线答案 |

### Role 卡片字段

`id`, `slug`, `name`, `category`, `avatar`, `avatarColor`, `tagline`, `description`, `tags[]`, `featured`, `order`

## 缓存约定

- 路径：`backend/roles/{slug}/cache/{question_hash}/`
  - `question.md`：问题索引
  - `{provider}.md`：该模型答案（含 frontmatter 元数据）
- 键：角色 + 规范化问题 + provider（与会话无关）
- 默认可入库；会话态 `session_cache` / `tokens` 不入库

## 错误码

- `code=400` 参数错误（HTTP 常仍为 200，前端统一读 body）
- `code=401` 未登录（`chat-llm` / `auth` GET / `llm-key-test` 使用 HTTP 401）
- `code=404` 角色不存在
- `code=405` 方法不允许
