# 文档索引

| 编号 | 文件 | 说明 |
|------|------|------|
| 00 | [00-readme.md](./00-readme.md) | 本索引 |
| 01 | [01-api-contract.md](./01-api-contract.md) | 后端 API 契约（前后端联调依据） |
| 02 | [02-dev-progress.md](./02-dev-progress.md) | 前后端进化与进度表、角色成熟度矩阵 |
| 03 | [03-architecture-flows.md](./03-architecture-flows.md) | 前后端流程图、角色资产差异与品类路径 |
| 04 | [04-geo-optimization.md](./04-geo-optimization.md) | GEO：成为 AI 可引用的可信信源 |

产品总方案见仓库根目录 [`README.md`](../README.md)。本地：`npm run dev`（或 `./dev.sh start`）。

跨域：`frontend/.env` 的 `VITE_API_BASE`（有值走远程，空则 `/api`）；后端 `cors_origin` / `asset_base`。

机器摘要：站点 `/llms.txt`（源文件 [`frontend/public/llms.txt`](../frontend/public/llms.txt)）。
