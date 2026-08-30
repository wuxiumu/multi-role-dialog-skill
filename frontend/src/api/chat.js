import { MOCK_ROLES } from './mockRoles.js'
import { request } from './client.js'

const LOCAL_REPLIES = {
  yingxue: [
    { keys: ['格局', '趋势', '未来'], text: '朋友，短期涨跌不重要，重要的是格局。把眼界拉到三年后，再回头看今天的纠结，大多是噪声。' },
    { keys: ['币', '比特', '投资', '套', '割'], text: '朋友，投资首先看趋势。我年轻，我不背锅——你自己把风险想清楚，再谈仓位。' },
    { keys: ['黑', '骂', '争议'], text: '被黑说明我在做事。流量即正义，争议也是曝光的一种。' },
  ],
  default: [
    { keys: ['你好', '嗨', '在吗'], text: '你好，我在。想聊什么直接说就行。' },
    { keys: ['谢谢', '感谢'], text: '不客气，有需要随时再来。' },
    { keys: ['退货', '退款', '售后'], text: '非常抱歉带来不便。请提供订单号，我帮你优先处理退换货。' },
    { keys: ['运势', '星座', '今日'], text: '今天适合把一件小事做完再开新坑。情绪起伏正常，别急着下结论。' },
  ],
}

function sessionIdFor(roleSlug) {
  const key = `mrd_session_${roleSlug}`
  try {
    let id = localStorage.getItem(key)
    if (!id) {
      id = `${roleSlug}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`
      localStorage.setItem(key, id)
    }
    return id
  } catch {
    return `${roleSlug}_anon`
  }
}

function localMatch(roleSlug, message) {
  const pools = [...(LOCAL_REPLIES[roleSlug] || []), ...LOCAL_REPLIES.default]
  const hit = pools.find((item) => item.keys.some((k) => message.includes(k)))
  if (hit) return hit.text
  const role = MOCK_ROLES.find((r) => r.slug === roleSlug)
  return role
    ? `（离线兜底）我是${role.name}。你说的「${message.slice(0, 40)}」我记下了——后端问答库就绪后会更准。`
    : '暂时没法回答，请稍后再试。'
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

function packResult(data, extras = {}) {
  return {
    reply: data.reply,
    source: data.source,
    degraded: !!data.degraded,
    matched: data.matched,
    matched_topic: data.matched_topic,
    quote_id: data.quote_id,
    risk: !!data.risk,
    score: data.score,
    keys: data.keys || [],
    endpoint: data.endpoint,
    provider: data.provider,
    provider_name: data.provider_name || '',
    model: data.model,
    llm_error: data.llm_error || '',
    from_cache: !!data.from_cache,
    question_hash: data.question_hash || '',
    variants: Array.isArray(data.variants) ? data.variants : [],
    variant_index: typeof data.variant_index === 'number' ? data.variant_index : 0,
    requested_provider: data.requested_provider || '',
    debug: data.debug || null,
    ...extras,
  }
}

/** 统一变体字段，兼容 reply / content */
function normalizeVariant(v) {
  if (!v || typeof v !== 'object') return null
  const reply = String(v.reply ?? v.content ?? '').trim()
  if (!reply) return null
  return {
    ...v,
    provider: v.provider || 'unknown',
    provider_name: v.provider_name || v.provider || '模型',
    model: v.model || '',
    reply,
    cached_at: v.cached_at || '',
    from_cache: v.from_cache !== false && !v.is_default,
    is_default: !!v.is_default || v.provider === 'default' || v.provider === 'offline',
  }
}

/** 在模型缓存中选 cached_at 最新的下标；没有则 -1 */
export function pickLatestVariantIndex(variants) {
  if (!Array.isArray(variants) || variants.length === 0) return -1
  let best = -1
  let bestTs = -1
  variants.forEach((v, i) => {
    if (!v || v.is_default || v.provider === 'default' || v.provider === 'offline' || v.provider === 'none') return
    const ts = Date.parse(v.cached_at || '') || 0
    if (ts > bestTs || (ts === bestTs && i >= best)) {
      bestTs = ts
      best = i
    }
  })
  return best
}

/** 去掉 none/current 等伪变体，合并重复 default，按 reply 去重 */
function sanitizeVariants(variants) {
  const out = []
  const seenReply = new Set()
  for (const v of variants) {
    if (!v) continue
    let provider = v.provider || 'unknown'
    // none/current/offline → 并入 default，避免与「默认语录」重复
    if (provider === 'none' || provider === 'current' || provider === 'offline') {
      provider = 'default'
      v = {
        ...v,
        provider: 'default',
        provider_name: v.provider_name === 'none' || v.provider_name === 'current' || !v.provider_name
          ? '默认语录'
          : v.provider_name,
        is_default: true,
        from_cache: false,
        model: '',
      }
    }
    const reply = String(v.reply || '').trim()
    if (!reply || seenReply.has(reply)) continue
    seenReply.add(reply)
    // 已有 default 时跳过第二个 default（保留第一条，通常已被会话文案覆盖）
    if ((provider === 'default' || v.is_default) && out.some((x) => x.is_default || x.provider === 'default')) {
      continue
    }
    out.push({ ...v, provider, reply })
  }
  return out
}

/**
 * 补齐同问题下的多模型缓存答案（匹配/降级路径也可能有缓存可切换）。
 */
async function enrichWithCacheVariants(roleSlug, message, result) {
  let variants = (Array.isArray(result.variants) ? result.variants : [])
    .map(normalizeVariant)
    .filter(Boolean)

  if (variants.length < 2) {
    try {
      const data = await request('/chat-cache-variants.php', {
        method: 'POST',
        body: JSON.stringify({ role: roleSlug, message }),
      })
      const fetched = (Array.isArray(data?.variants) ? data.variants : [])
        .map(normalizeVariant)
        .filter(Boolean)
      if (fetched.length > variants.length) {
        variants = fetched
        if (data.question_hash) {
          result = { ...result, question_hash: data.question_hash }
        }
      }
    } catch {
      // 忽略变体查询失败
    }
  }

  // 当前离线回复：并入 default，绝不插入 provider=none
  const currentReply = String(result.reply || '').trim()
  const hasModelCache = variants.some(
    (v) => v && !v.is_default && v.provider !== 'default' && v.provider !== 'offline' && v.provider !== 'none',
  )
  if (currentReply && !hasModelCache && !variants.some((v) => v.reply === currentReply)) {
    const isOfflineProvider =
      !result.provider ||
      result.provider === 'none' ||
      result.provider === 'default' ||
      result.provider === 'offline' ||
      result.source === 'match' ||
      result.source === 'quotes' ||
      result.source === 'mock' ||
      result.source === 'local'
    if (isOfflineProvider) {
      variants = [
        {
          provider: 'default',
          provider_name: result.source === 'quotes' || result.source === 'mock' ? '默认语录' : '默认匹配',
          model: '',
          reply: currentReply,
          from_cache: false,
          is_default: true,
        },
        ...variants.filter((v) => v.provider !== 'default' && v.provider !== 'offline' && v.provider !== 'none'),
      ]
    } else {
      variants = [
        {
          provider: result.provider || 'current',
          provider_name: result.provider_name || result.provider || '当前',
          model: result.model || '',
          reply: currentReply,
          from_cache: !!result.from_cache,
          cached_at: '',
        },
        ...variants,
      ]
    }
  }

  variants = sanitizeVariants(variants)

  // 只要有模型缓存，一律优先展示最新缓存
  let variantIndex = 0
  const latest = pickLatestVariantIndex(variants)
  if (latest >= 0) {
    variantIndex = latest
  } else if (typeof result.variant_index === 'number' && result.variant_index >= 0) {
    variantIndex = Math.min(result.variant_index, Math.max(0, variants.length - 1))
  } else {
    const byDefault = variants.findIndex((v) => v.is_default || v.provider === 'default')
    variantIndex = byDefault >= 0 ? byDefault : 0
  }

  const active = variants[variantIndex]
  return {
    ...result,
    variants,
    variant_index: variantIndex,
    reply: active?.reply || result.reply,
    provider: active?.provider || result.provider,
    provider_name: active?.provider_name || result.provider_name,
    model: active?.model ?? result.model,
    from_cache: latest >= 0 ? true : !!result.from_cache,
    source: latest >= 0 ? 'llm' : result.source,
  }
}

/**
 * @param {{ role, message, history, token, isLoggedIn, provider }} opts
 * provider: none|deepseek|qwen|...
 */
export async function sendChatMessage({ role, message, history, token, isLoggedIn, provider = 'none' }) {
  const roleSlug = role.slug
  const session_id = sessionIdFor(roleSlug)
  const historyPayload = history.map(({ role: r, content }) => ({ role: r, content }))
  const useLlm = isLoggedIn && token && provider && provider !== 'none'
  let llmError = ''

  if (useLlm) {
    try {
      const data = await request('/chat-llm.php', {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
        body: JSON.stringify({
          role: roleSlug,
          message,
          history: historyPayload,
          session_id,
          provider,
        }),
      })
      if (data?.reply) {
        return enrichWithCacheVariants(
          roleSlug,
          message,
          packResult({
            ...data,
            source: data.source || 'llm',
            endpoint: data.endpoint || 'chat-llm.php',
            llm_error: data.llm_error || data.error || '',
          }),
        )
      }
      llmError = data?.llm_error || data?.error || '模型未返回内容'
    } catch (e) {
      // 鉴权失败交给上层处理；其它错误继续降级并带上原因
      if (e && (e.status === 401 || e.code === 401)) {
        throw e
      }
      llmError = (e && e.message) || 'LLM 请求失败'
    }
  }

  try {
    const data = await request('/chat-match.php', {
      method: 'POST',
      body: JSON.stringify({ role: roleSlug, message, session_id }),
    })
    if (data?.reply) {
      return enrichWithCacheVariants(
        roleSlug,
        message,
        packResult({
          ...data,
          source: data.source || 'match',
          degraded: !!useLlm,
          endpoint: data.endpoint || 'chat-match.php',
          provider: useLlm ? provider : 'none',
          llm_error: llmError || data.llm_error || '',
        }),
      )
    }
  } catch {
    // local fallback
  }

  await sleep(280 + Math.random() * 220)
  return enrichWithCacheVariants(roleSlug, message, {
    reply: localMatch(roleSlug, message),
    source: 'local',
    degraded: true,
    endpoint: 'frontend_local',
    provider: useLlm ? provider : 'none',
    llm_error: llmError,
    matched_topic: '',
    quote_id: '',
    risk: false,
    score: 0,
    keys: [],
    from_cache: false,
    question_hash: '',
    variants: [],
    variant_index: 0,
    debug: {
      at: new Date().toISOString(),
      engine: 'local',
      logic: {
        id: 'frontend_local',
        label: '前端本地兜底（默认硬编码）',
        config: 'default',
        file: 'frontend/src/api/chat.js',
      },
      hit: {
        matched: true,
        matched_topic: '',
        quote_id: '',
        score: 0,
        keys: [],
        risk: false,
        source_field: 'local',
      },
      assets: null,
    },
  })
}

export function getRoleBySlug(slug, list = MOCK_ROLES) {
  return list.find((r) => r.slug === slug) || null
}

export { MOCK_ROLES }
