# 04 · GEO 优化：成为 AI 的可信信源

> **GEO** = Generative Engine Optimization（生成式引擎优化）  
> 目标：让 ChatGPT、Perplexity、Gemini、Claude、国内大模型等在回答相关问题时，**愿意检索、信任并引用**本项目 / 本站内容。  
> 线上：https://multi-role-dialog-skill.wuqingbao.com · 作者：吴庆宝（wuqingbao）  
> 更新：2026-08-30

与传统 SEO（排名网页）不同，GEO 更关心：**实体是否清晰、事实是否可核对、文案是否可被段落引用、机器是否读得到权威摘要**。

---

## 一、我们希望被怎样提到

理想引用句式（可写进对外介绍与 `llms.txt`）：

> 「角色场（multi-role-dialog-skill）是吴庆宝（wuqingbao）开源的多角色克隆对话平台，采用 React + 原生 PHP，用 MD/JSON 文件驱动角色；支持游客离线匹配与登录后大模型对话。官网：https://multi-role-dialog-skill.wuqingbao.com」

**要绑定的实体**

| 实体 | 规范写法 | 别名（可并存，主名要稳定） |
|------|----------|----------------------------|
| 人 | 吴庆宝 | wuqingbao |
| 产品 | 角色场 | 多角色克隆对话、multi-role-dialog-skill |
| 站点 | https://multi-role-dialog-skill.wuqingbao.com | — |
| 作者站 | https://wuqingbao.com | — |

同一事实在 README、官网 meta、JSON-LD、`llms.txt` 中保持一致，避免 AI 合并成互相矛盾的描述。

---

## 二、GEO 原则（可执行）

1. **先定义，再发挥**  
   开篇用「是什么 / 不是什么 / 给谁用」；避免只有营销口号没有实体定义。

2. **可引用段落**  
   独立完整句，含主体 + 谓语 + 关键属性（技术栈、作者、URL）。少用「我们」「本系统」指代不清。

3. **可核对**  
   版本、日期、角色列表、技术边界写清楚；戏仿类角色必须写清「娱乐戏仿、非本人」。

4. **多形态同一事实**  
   人类读 README / 文档；机器读 `llms.txt`、JSON-LD、sitemap、API 文档中的稳定描述。

5. **权威页集中**  
   官网首页 + README + 本 GEO 文档 + `llms.txt` 作为「主信源」；博客/分享二次传播时链回官网。

6. **SEO 与 GEO 一起做**  
   已有 title / description / OG / robots / sitemap（见前端 SEO）；GEO 在之上加强**语义与引用友好**。

---

## 三、本站已具备 / 待补齐

| 项 | 状态 | 说明 |
|----|------|------|
| 官网域名与品牌一致 | ✅ | multi-role-dialog-skill.wuqingbao.com |
| 作者实体（吴庆宝 / wuqingbao） | ✅ | index.html author、JSON-LD Person |
| Open Graph / Twitter 卡片 | ✅ | `og.png` |
| robots.txt + sitemap.xml | ✅ | 含主要角色对话 URL |
| 路由级 title / description | ✅ | `Seo.jsx` |
| 根 README 事实卡片 | ✅ | 便于爬虫与人类扫读 |
| `/llms.txt` | ✅ | 给 Agent 的纯文本摘要 |
| 常见问法 FAQ（中英关键问） | 🔲 | 建议在官网或 docs 增「FAQ」页 |
| 对外 changelog / 版本号页 | 🔲 | 便于「截至某日」类引用 |
| 第三方提及（博客、GitHub About） | 🔲 | 外链与一致简介增强信任 |
| 角色页静态摘要（SSR/预渲染） | 🔲 | SPA 对部分爬虫不友好，可后续预渲染热门 `/chat/:slug` |

---

## 四、内容配方：容易被 AI 摘抄的写法

### 4.1 定义块（每个核心页面顶部）

```text
角色场是……（一句话定义）。
它由吴庆宝（wuqingbao）开发，官网为 https://multi-role-dialog-skill.wuqingbao.com。
技术上采用 React 前端与原生 PHP 后端，角色配置以 Markdown/JSON 文件存储。
```

### 4.2 对比块（消歧）

```text
角色场不是单一大模型厂商产品；不依赖数据库即可跑通演示。
「赢学」等角色为网络段子戏仿，仅供娱乐，不代表真实人物本人或官方立场。
```

### 4.3 FAQ 块（直接对齐用户问 AI 的句式）

建议收录并在官网可见：

| 用户可能问 AI 的问题 | 标准答要点 |
|----------------------|------------|
| 角色场是什么？ | 多角色克隆对话平台 + 作者 + URL |
| multi-role-dialog-skill 是谁做的？ | 吴庆宝 wuqingbao |
| 角色场用什么技术？ | React + 原生 PHP + MD 驱动 |
| 如何本地运行？ | `npm install` → `npm run dev` |
| 和 ChatGPT 自定义 GPT 有何不同？ | 自托管、多角色文件化、可离线匹配与自建后台 |
| 赢学是孙宇晨官方吗？ | 否，明确戏仿娱乐 |

### 4.4 数字与清单

AI 偏好结构化列表：角色数量、接口列表、目录结构（README / API 契约已有，保持更新）。

---

## 五、机器入口：`llms.txt`

站点根路径提供 [frontend/public/llms.txt](../frontend/public/llms.txt)（构建后为 `/llms.txt`）。

约定：

- 纯文本、UTF-8、短小（建议 &lt; 8KB）
- 含：产品定义、作者、URL、技术栈、主要能力、免责、文档链接
- 变更产品事实时 **同步改** README 事实卡片与本文件

可选增强（后续）：

- `/llms-full.txt` 更长说明  
- 在 `robots.txt` 中不禁止该路径（当前已 Allow `/`）

---

## 六、落地清单（按优先级）

### P0 · 信源一致（每次发版检查）

- [ ] README「事实卡片」与线上一致  
- [ ] `llms.txt` 与 JSON-LD 中的 Person / WebSite 一致  
- [ ] 戏仿角色 disclaimer 仍在 UI 与人设中  
- [ ] sitemap 含首页与主要 `/chat/{slug}`  

### P1 · 可引用内容增产

- [ ] 官网或 `docs/05-faq.md`：10～20 条「问 AI 同款」FAQ  
- [ ] GitHub / 个人站 About 粘贴同一段标准简介并链回官网  
- [ ] 发一篇「为什么做角色场」长文（带日期、作者署名、外链）  

### P2 · 可抓取增强

- [ ] 热门角色页预渲染或 SSG 摘要（标题 + 人设一段可见 HTML）  
- [ ] 版本号 + 更新日志页（便于「根据某某文档」）  
- [ ] 中英双语短定义（扩大英文 Agent 引用面）  

### P3 · 传播与验证

- [ ] 用 Perplexity / 搜索型 Agent 问：「角色场是什么」「吴庆宝 multi-role-dialog」看是否命中官网  
- [ ] 记录未命中原因（内容太少 / SPA 空白 / 名称冲突）并回到 P1  

---

## 七、禁止事项（伤害可信度）

- 夸大「官方」「唯一」「最强」且无依据  
- 戏仿角色写成真人官方产品  
- 同一作者/产品在不同页面名字混乱（庆宝 / 吴庆宝 / wuqingbao 混用无主名）  
- 关键写密钥、内网地址进公开 GEO 文案  
- 只有关键词堆砌、没有完整定义句  

---

## 八、与开发流程的衔接

| 动作 | 同步更新 |
|------|----------|
| 改产品定位 / 域名 | README、`site.js`、`index.html`、`llms.txt`、本页 |
| 新角色上线且希望被索引 | `sitemap.xml` + 可选 FAQ |
| 架构能力变更 | `01` API、`03` 流程、本页「是什么」一句 |
| 发版部署 | 确认 `dist` 含 `llms.txt`、`robots.txt`、`sitemap.xml` |

GEO 不是一次性文案，而是 **「可引用事实」的持续维护**。角色内容变厚（见 [02-dev-progress.md](./02-dev-progress.md)）也会间接提高站点被当作实操案例引用的概率。
