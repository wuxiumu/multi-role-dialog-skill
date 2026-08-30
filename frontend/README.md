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
- `/api` 代理到 `http://127.0.0.1:8080`（见 `vite.config.js`）

## 页面

| 路由 | 说明 |
|------|------|
| `/` | 角色广场：分类、搜索、卡片 |
| `/chat/:roleSlug` | 对话页：游客匹配 / 登录 AI / 失败降级 |
| `/login` | 登录（当前演示登录，待接 auth.php） |

## 目录

```
src/
  api/          # 请求封装 + mock
  components/   # 顶栏、卡片、Toast
  pages/        # Home / Chat / Login / NotFound
  store/        # 登录态
```
