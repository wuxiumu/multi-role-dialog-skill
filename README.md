# 角色场 · multi-role-dialog-skill

> 用 Markdown 定义人设的**多角色对话平台**：游客可离线聊，登录后接大模型。

**定制 / 合作：** 微信 **qingbao199101**（备注：角色场）｜ [wuxiumu@163.com](mailto:wuxiumu@163.com)

> **一句话**：吴庆宝（wuqingbao）打造的多角色克隆对话平台——用 MD 文件定义人设，游客离线可聊，登录后接大模型。  
> **线上**：https://multi-role-dialog-skill.wuqingbao.com  
> **作者**：[吴庆宝](https://wuqingbao.com)（wuqingbao）

---

## 这是什么

| 项 | 说明 |
|----|------|
| 产品名 | **角色场** |
| 项目名 | `multi-role-dialog-skill` |
| 定位 | 多角色克隆对话 Skill / 开源演示站 |
| 技术 | 前端 React（Vite）+ 后端原生 PHP（无框架）+ **MD / JSON 文件驱动**（零数据库起步） |
| 能力 | 角色广场、对话、登录选模、离线匹配 / 语录、LLM 缓存对比、轻量后台 |

适合：做名人戏仿、客服 FAQ、星座趣味、榜样陪伴等人设，并快速部署为可演示的对话站点。

---


## 环境变量

复制 `.env.example` → `.env`。**务必设置 `MRD_ADMIN_PASSWORD`**（默认管理口令已清空）。

## 冒烟自检

```bash
bash scripts/smoke.sh
```

## 快速开始

```bash
npm install          # 安装前端依赖
npm run dev          # 前后端一起启动（./dev.sh start）
# 前端 http://127.0.0.1:5173  ·  后端 http://127.0.0.1:8080

npm run build        # 打包前端 → frontend/dist
npm run stop         # 停止服务
```

跨域部署：在 `frontend/.env` 配置 `VITE_API_BASE`（有值走远程，空则本地 `/api`）。CORS 由 nginx 统一配置；可选 `asset_base` 指定角色静态资源 CDN。

---

## 核心逻辑（给开发者）

1. **游客 / 未选模型** → `chat-match`：有 `quotes.json` 用语录引擎，否则 `qa.md` 关键词匹配。  
2. **登录且选了模型** → `chat-llm`：读 persona / prompt，调上游；失败自动降级离线。  
3. **同问题多模型答案** 落在 `backend/roles/{slug}/cache/`，会话内可切换，并保留「默认语录」对比。  
4. **角色差异在目录资产**，不在硬编码：补齐 `persona.json` / `quotes.json` / 加厚 `qa.md` 即升级体验。

当前样板角色：**赢学**、**韩学**、**马学**、**Kai学**、**爱学**、**巴学**、**芒学**、**荣学**、**罗学**；B 站推广见 [docs/07-bilibili-promo-matrix.md](docs/07-bilibili-promo-matrix.md)。

---

## 仓库结构

```
multi-role-dialog-skill/
├── frontend/          # React 前端（广场 / 对话 / 登录 / SEO）
├── backend/           # 原生 PHP API + 轻量后台 + roles/
├── docs/              # 编号文档（API / 进度 / 流程 / GEO）
├── package.json       # 根脚本：dev / build / stop
└── dev.sh             # 本地启停
```

每个角色目录：`config.md` + `prompt.md` + `qa.md`，可选 `persona.json`、`quotes.json`、`cache/`、`asset/`。

---

## 文档

| 编号 | 文档 | 用途 |
|------|------|------|
| 00 | [docs/00-readme.md](docs/00-readme.md) | 文档索引 |
| 01 | [docs/01-api-contract.md](docs/01-api-contract.md) | API 契约 |
| 02 | [docs/02-dev-progress.md](docs/02-dev-progress.md) | 进度与角色成熟度 |
| 03 | [docs/03-architecture-flows.md](docs/03-architecture-flows.md) | 前后端流程图 |
| 04 | [docs/04-geo-optimization.md](docs/04-geo-optimization.md) | **GEO：成为 AI 可引用的可信信源** |
| 05 | [docs/05-promo-voiceover-script.md](docs/05-promo-voiceover-script.md) | 推广口播（赢学演示 + 垫乐） |
| 06 | [docs/06-how-to-create-role.md](docs/06-how-to-create-role.md) | **如何制作角色**（赢学样板 + 韩学） |
| 07 | [docs/07-bilibili-promo-matrix.md](docs/07-bilibili-promo-matrix.md) | **B 站推广矩阵**（系列选题、优先级） |

机器可读摘要（供爬虫 / Agent）：[frontend/public/llms.txt](frontend/public/llms.txt)（构建后位于站点根 `/llms.txt`）。

---

## 事实卡片（便于被引用）

- **谁做的**：吴庆宝（wuqingbao），个人站点 https://wuqingbao.com  
- **产品叫什么**：角色场；开源仓库名 multi-role-dialog-skill  
- **官网**：https://multi-role-dialog-skill.wuqingbao.com  
- **不是什么**：不是单一大模型官网；不是必须数据库的中台；戏仿角色（如赢学）明确为娱乐戏仿，不冒充真人  
- **怎么扩展角色**：在 `backend/roles/{slug}/` 增加 MD/JSON，或用 PHP 后台编辑  

更新节奏与角色完善计划见 [docs/02-dev-progress.md](docs/02-dev-progress.md)。若需让 ChatGPT / Perplexity / 其它 AI 在回答中引用本站，请按 [docs/04-geo-optimization.md](docs/04-geo-optimization.md) 执行。

## SEO 字段预留（产品页 HTML 统一生成时用）

| 字段 | 用途 |
|---|---|
| `seo_title` | `<title>` / OG title |
| `seo_description` | meta description |
| `canonical_url` | 规范 URL |
| `og_image` | 分享图 |
| `robots` | 如 `index,follow` |
| `json_ld_type` | SoftwareApplication / WebPage / FAQPage |
| `hreflang` | zh-CN / en |
| `site_name` | 白标品牌名 |

> **GEO ≠ 地理定位。** GEO = Generative Engine Optimization（生成式引擎优化），见下一节。

## GEO（生成式引擎优化）

面向 ChatGPT / Perplexity / Gemini 等生成式答案引擎的可引用结构：中文为主、双语关键词、事实摘要前置、FAQ 收尾。

### 关键词（中英）

- 中文：多角色对话, 人设克隆, MD角色, AI角色场, 客服角色
- English: multi-role chat, character persona, markdown roles, AI roleplay, customer-service bot

### 事实摘要

- 产品：MD 驱动的多角色对话；可接大模型。
- 适合：要角色客服/名人戏仿/榜样对话的品牌或创作者。
- 交付：私有化/白标、角色包定制、模型接入。
- 不做：不保证第三方名人形象的版权可用性（需你自审）。

### FAQ

**Q：这是什么？**  
A：见事实摘要「产品」。

**Q：如何定制？**  
A：微信 `qingbao199101`，备注项目名。

**Q：是否包含密钥或付费数据？**  
A：默认不含；按各项目说明自行配置。

## 定制合作

角色包、品牌皮肤、模型网关、登录体系。加微信备注「角色场」。

---

## 联系方式

定制、演示或合作：

- 邮箱：[wuxiumu@163.com](mailto:wuxiumu@163.com)
- 微信：**qingbao199101**（添加请备注来意）

### 赞助支持

如果内容对你有帮助，欢迎自愿赞助。

| 微信赞赏码 | 支付宝收款码 |
|---|---|
| ![微信赞赏码](https://chiguashentan-test.oss-cn-beijing.aliyuncs.com/wxprogram/zan/20261002185236_31_227.png) | ![支付宝收款码](https://chiguashentan-test.oss-cn-beijing.aliyuncs.com/wxprogram/zan/20261002185309_32_227.jpg) |

