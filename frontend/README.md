# 角色场 · 前端

React + Vite 独立前端，对接原生 PHP API。

## 开发

推荐在仓库根目录：

```bash
./dev.sh start
```

或仅前端：

```bash
npm install
npm run dev
```

- 本地：http://127.0.0.1:5173
- 默认 `VITE_API_BASE=/api`，由 Vite 代理到 `http://127.0.0.1:8080`（见 `vite.config.js`）

## 环境变量（跨域部署）

```bash
cp .env.example .env.production
# 编辑 .env.production 中的域名后：
npm run build
```

| 变量 | 说明 | 示例 |
|------|------|------|
| `VITE_API_BASE` | 后端 API 根（无末尾 `/`） | `https://api.example.com/api` |
| `VITE_ASSET_BASE` | 角色头像等静态资源源站；可留空由 API 域名推导 | `https://api.example.com` |

模板文件：

- [`.env.example`](./.env.example) — 说明与默认值
- [`.env.development`](./.env.development) — 本地代理
- [`.env.production.example`](./.env.production.example) — 生产示例

后端请同步配置 CORS / 资源前缀（`config.local.php`）：

```php
'cors_origin' => 'https://www.example.com',  // 或 '*'
'asset_base'  => 'https://api.example.com',
```

并保证 Web 服务器能访问 `https://api.example.com/roles/{slug}/asset/...`（或 CDN）。

## 页面

| 路由 | 说明 |
|------|------|
| `/` | 角色广场：分类、搜索、卡片 |
| `/chat/:roleSlug` | 对话页：游客匹配 / 登录 AI / 失败降级 |
| `/login` | 登录 |
| `/llm-key-test` | 模型 Key 连通性测试 |

## 目录

```
src/
  api/          # 请求封装 + mock
  components/   # 顶栏、卡片、Toast
  pages/        # Home / Chat / Login / NotFound
  store/        # 登录态
```
