# 角色场 · 前端

React + Vite。作者：吴庆宝（wuqingbao）。线上：https://multi-role-dialog-skill.wuqingbao.com

## 开发 / 打包（推荐在仓库根目录）

```bash
npm install          # 安装前端依赖（postinstall）
npm run dev          # 前后端一起启动
npm run build        # 打包 → frontend/dist
npm run stop
```

仅前端：

```bash
cd frontend && npm install && npm run dev
```

本地默认请求 `/api`（Vite 代理到 `127.0.0.1:8080`）。

## 环境变量

只认一个文件：`.env`（可从 `.env.example` 复制）。

| `VITE_API_BASE` | 行为 |
|-----------------|------|
| 未配 / 留空 | 本地 `/api` |
| `https://api.example.com/api` | 走远程 |

## SEO

- `index.html`：title / description / Open Graph / JSON-LD（作者吴庆宝）
- `public/robots.txt`、`sitemap.xml`、`og.png`、`site.webmanifest`
- 路由级：`src/seo/Seo.jsx` 更新 title 与 canonical

## 页面

| 路由 | 说明 |
|------|------|
| `/` | 角色广场 |
| `/chat/:roleSlug` | 对话 |
| `/login` | 登录 |
| `/llm-key-test` | 模型 Key 测试 |
