# 06 · 如何制作一个角色（以赢学为样板）

> 对齐：[`yingxue`](../backend/roles/yingxue/)、[`02-dev-progress.md`](./02-dev-progress.md)、[`03-architecture-flows.md`](./03-architecture-flows.md)  
> 一句话：**同一套前后端逻辑，差异全在 `backend/roles/{slug}/` 目录资产。**

---

## 一、先选品类，再决定做多厚

| 品类 | 推荐资产组合 | 离线主引擎 | 样板 |
|------|--------------|------------|------|
| **名人戏仿** | config + prompt + qa + **persona** + **quotes** | `quotes.json` | 赢学 `yingxue`、韩学 `hanxue` |
| 客服 / FAQ | config + prompt + **加厚 qa** + persona | `qa.md` | 客服小七 |
| 星座 / 趣味 | 同上 | `qa.md` | 星语姐姐 |
| 榜样 / 老师 | 同上 | `qa.md` | 林老师、开悟学长 |

**硬规则**：只要目录里有 `quotes.json`，离线就会优先走语录引擎，`qa.md` 只作后备。  
因此：**FAQ 型角色不要放空的 quotes.json**。

---

## 二、目录清单

```
backend/roles/{slug}/
├── config.md          # 必填：广场卡片
├── prompt.md          # 必填：LLM 人设（无 persona 时用）
├── qa.md              # 必填：关键词 FAQ
├── persona.json       # 推荐：system_prompt、快捷提问、免责
├── quotes.json        # 戏仿型推荐：主题语录库
├── asset/             # 可选：logo.png 等头像
└── cache/             # 可选：LLM 黄金问答（聊过后自动生成）
```

`slug`：小写字母、数字、短横线，例如 `hanxue`。改 slug = **重命名目录**，不要只改 frontmatter。

---

## 三、逐步制作（建议顺序）

### 1. 建目录 + `config.md`

```yaml
---
name: 角色展示名
slug: your-slug
category: 名人          # 名人 / 客服 / 星座 / 榜样 …
avatar: 字              # 无图时的单字头像
avatarColor: linear-gradient(135deg,#颜色1,#颜色2)
# avatarImage: logo.png   # 本地图放 asset/
# avatarCdn: https://...  # 或 CDN 全路径
tagline: 一句话卖点
description: 稍长介绍（戏仿务必写清娱乐属性）
tags: 标签1, 标签2
featured: true|false
order: 15               # 越小越靠前；赢学为 10
---
```

### 2. 写 `prompt.md`（短人设）

给大模型的底稿：身份、口吻、3～5 条硬规则。  
被问「是本人吗」必须澄清是戏仿；敏感域（投资、医疗、法律等）要免责。

### 3. 写 `qa.md`（经典问答骨架）

格式固定：

```markdown
## Q: 关键词1|关键词2|同义问法
A: 这一条的固定回答。
```

要点：

- `|` 分隔多个命中词；覆盖口语问法（你好 / 你是谁 / 谢谢…）
- 身份题单独一条，语气与角色一致，但必须声明戏仿
- 建议 **≥15～30 组**；戏仿角色可先薄后补，靠 quotes 撑离线体验

### 4. 写 `persona.json`（人设卡）

关键字段：

| 字段 | 作用 |
|------|------|
| `system_prompt` | LLM 优先用这份（覆盖/强于 prompt.md） |
| `style_rules` / `catchphrases` | 口吻约束与口头禅 |
| `guardrails` / `disclaimer_text` | 合规与 UI 免责 |
| `quick_questions` | 对话页推荐题（优先展示） |
| `quick_pool` | 「更多」题库 |
| `mode` | 戏仿常用 `"mock"` |

参考：`backend/roles/yingxue/persona.json`。

### 5. 写 `quotes.json`（戏仿离线主路径）

结构：

```json
{
  "version": 1,
  "updated": "YYYY-MM-DD",
  "disclaimer": "戏仿声明…",
  "topics": [
    { "id": "life", "name": "人生", "lines": [
      { "id": "life-001", "text": "语录正文", "keywords": ["迷茫","人生"], "source": "风格戏仿" }
    ]},
    { "id": "opener", "name": "开场前缀", "lines": [ { "id": "op-001", "text": "我觉得——" } ] },
    { "id": "catchphrase", "name": "口头禅尾巴", "lines": [ { "id": "tail-001", "text": "就这样。" } ] },
    { "id": "fallback", "name": "未命中兜底", "lines": [ { "id": "fb-001", "text": "…", "keywords": [] } ] }
  ]
}
```

主题约定：

| topic id | 是否参与打分 | 说明 |
|----------|--------------|------|
| 内容主题（自定） | 是 | 按 `keywords` 命中拼主句 |
| `opener` | 否 | 随机开场前缀 |
| `catchphrase` | 否 | 随机尾巴 |
| `fallback` | 否 | 未命中时用 |

密度参考赢学：每内容主题约 10～20 条；`opener` / `catchphrase` / `fallback` 各备若干条。

### 6. 可选：头像与缓存

- 头像：`asset/logo.png` + `config.md` 里 `avatarImage: logo.png`
- `cache/`：登录选模型聊过后自动落盘；精选问答可入库当「黄金答案」

### 7. 验收

1. `npm run dev` → 广场出现该角色  
2. **游客**发身份题、主题题、乱问 → 分别命中身份声明 / 语录或 qa / 兜底  
3. **登录 + 选模型** → 口吻像人设，免责仍在  
4. 推荐题来自 persona，不是空壳  

---

## 四、两种开工方式

| 方式 | 做法 |
|------|------|
| 文件系统 | 复制 `yingxue` 或 `hanxue`，改 slug/文案/语录 |
| 后台 | 新建角色（可勾选 persona / quotes 骨架）→ 编辑 MD / 人设 / 语录 |

后台入口：角色列表 → 编辑 config/prompt/qa → persona.json → quotes.json。

---

## 五、合规清单（名人戏仿必做）

1. 文案与 UI 标明：**娱乐戏仿，非本人 / 非官方**  
2. 被追问身份时，回复里再次澄清  
3. 不编造隐私、指控、诉讼等具体事实  
4. 投资 / 医疗 / 法律等：不给可执行建议，回到角色口吻并提示风险  
5. 语录 `source` 建议标「风格戏仿 / 公开表述风格整理」，勿伪装官方原话库  

---

## 六、成熟度对照（对照赢学）

| 级别 | 条件 | 体验 |
|------|------|------|
| 骨架 | 仅 config + prompt + 薄 qa | 能聊，常兜底 |
| 可用 | qa ≥15 组 + persona | 游客稳定命中，有推荐题 |
| 样板 | + 厚 quotes（或 FAQ 型厚 qa）+ 免样例 | 接近赢学：离线有戏、在线有人设 |

下一步可直接对照本仓库的 **韩学 `hanxue`**（韩寒风格戏仿）与 **赢学 `yingxue`** 两套资产抄作业。
