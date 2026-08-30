# 角色场 · 前端

React + Vite 独立前端，对接原生 PHP API。

## 开发

```bash
./dev.sh start
# 或
cd frontend && npm install && npm run dev
```

本地默认请求 `/api`（Vite 代理到 `127.0.0.1:8080`）。

## 环境变量

只认一个文件：`.env`（可从 `.env.example` 复制）。

```bash
cp .env.example .env
```

| `VITE_API_BASE` | 行为 |
|-----------------|------|
| 未配 / 留空 | 本地 `/api` |
| `https://api.example.com/api` | 走远程；头像等相对路径自动拼到该源站 |

部署前写入远程地址再 `npm run build`。`.env` 已 gitignore，勿提交密钥类配置。

后端跨域时在 `config.local.php` 设置 `cors_origin`、`asset_base`。

## 页面

| 路由 | 说明 |
|------|------|
| `/` | 角色广场 |
| `/chat/:roleSlug` | 对话 |
| `/login` | 登录 |
| `/llm-key-test` | 模型 Key 测试 |
