import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { fetchRoleList } from '../../api/roles.js'
import { fetchPersona } from '../../api/persona.js'
import { getRoleBySlug, pickLatestVariantIndex, sendChatMessage } from '../../api/chat.js'
import { request } from '../../api/client.js'
import { useAuth } from '../../store/AuthContext.jsx'
import { toast } from '../../components/Toast.jsx'
import { ChatDebugPanel, useDebugGate } from '../../components/ChatDebugPanel.jsx'
import { RoleAvatar } from '../../components/RoleAvatar.jsx'
import { ShareSheet } from '../../components/ShareSheet.jsx'
import MarkdownBubble from '../../components/MarkdownBubble.jsx'
import { Seo } from '../../seo/Seo.jsx'
import { copyText, pairAroundMessage } from '../../utils/share.js'
import '../../components/RoleAvatar.css'
import './ChatPage.css'

/** 上行约 3 个自然宽；其余全部进下行横滑 */
const CHIP_ROW1 = 3

const QUICK_BY_CATEGORY = {
  名人: [
    '怎么理解格局？',
    '比特币还能买吗？',
    '被人黑了怎么办？',
    '我被套牢了想割肉',
    '如何做好营销？',
    '人生迷茫该怎么破？',
  ],
  客服: ['我想退货', '物流到哪了？', '怎么开发票？', '订单能改地址吗', '售后多久到账', '人工客服'],
  星座: ['今日运势怎么样？', '我适合什么工作？', '最近感情运如何？', '本周宜忌', '财运如何', '适合和谁相处'],
  榜样: ['帮我拆解这个目标', '怎么开始学习？', '做个决策清单', '拖延怎么办', '如何复盘', '给点行动建议'],
}

function shuffle(arr) {
  const a = arr.slice()
  for (let i = a.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[a[i], a[j]] = [a[j], a[i]]
  }
  return a
}

/** 合并去重后随机打乱，首次展示全部 */
function buildAllChips(initial, pool) {
  const seen = new Set()
  const all = []
  for (const q of [...(initial || []), ...(pool || [])]) {
    const t = typeof q === 'string' ? q.trim() : ''
    if (!t || seen.has(t)) continue
    seen.add(t)
    all.push(t)
  }
  return {
    visible: shuffle(all),
    pool: all.slice(),
  }
}

function typewriter(full, onTick, signal) {
  return new Promise((resolve) => {
    let i = 0
    const step = () => {
      if (signal.aborted) return resolve()
      i = Math.min(i + 1 + Math.floor(Math.random() * 2), full.length)
      onTick(full.slice(0, i))
      if (i >= full.length) return resolve()
      setTimeout(step, 16 + Math.random() * 18)
    }
    step()
  })
}

export function ChatPage() {
  const { roleSlug } = useParams()
  const navigate = useNavigate()
  const { isLoggedIn, token, llmProvider, logout } = useAuth()
  const [role, setRole] = useState(null)
  const [persona, setPersona] = useState(null)
  const [loadingRole, setLoadingRole] = useState(true)
  const [messages, setMessages] = useState([])
  const [input, setInput] = useState('')
  const [sending, setSending] = useState(false)
  const [visibleChips, setVisibleChips] = useState([])
  const poolRef = useRef([])
  const [chipTotal, setChipTotal] = useState(0)
  const [chipsVisible, setChipsVisible] = useState(true)
  const [debugEnabled, setDebugEnabled] = useState(false)
  const [debugOpen, setDebugOpen] = useState(false)
  const [chipSourceLabel, setChipSourceLabel] = useState('前端默认分类兜底')
  const [lastCall, setLastCall] = useState(null)
  const [callLog, setCallLog] = useState([])
  const [shareOpen, setShareOpen] = useState(false)
  const [shareMode, setShareMode] = useState('agent')
  const [sharePair, setSharePair] = useState(null)
  const [allQuestions, setAllQuestions] = useState([])
  const [questionsOpen, setQuestionsOpen] = useState(false)
  const listRef = useRef(null)
  const abortRef = useRef(null)
  const taRef = useRef(null)

  const setDebugMode = useCallback((v) => {
    setDebugEnabled((prev) => {
      const next = typeof v === 'function' ? v(prev) : !!v
      if (!next) setDebugOpen(false)
      else setDebugOpen(true)
      return next
    })
  }, [])
  useDebugGate(setDebugMode)

  useEffect(() => {
    if (!debugEnabled) setDebugOpen(false)
  }, [debugEnabled])

  useEffect(() => {
    let alive = true
    ;(async () => {
      setLoadingRole(true)
      const [list, p] = await Promise.all([fetchRoleList(), fetchPersona(roleSlug)])
      if (!alive) return
      const found = getRoleBySlug(roleSlug, list)
      setRole(found)
      setPersona(p)
      setLoadingRole(false)
      if (!found) return

      const fallback = QUICK_BY_CATEGORY[found.category] || ['你好', '你能做什么？', '介绍一下自己']
      let questions = fallback
      let pool = []
      let sourceLabel = '前端默认（QUICK_BY_CATEGORY）全量随机'

      if (Array.isArray(p?.quick_questions) && p.quick_questions.length > 0) {
        questions = p.quick_questions
        sourceLabel = 'persona.json · quick_questions 全量随机'
      }
      if (Array.isArray(p?.quick_pool) && p.quick_pool.length > 0) {
        pool = p.quick_pool
        sourceLabel = p.has_persona_file
          ? 'persona.json · quick_questions + quick_pool 全量随机'
          : 'persona API · 推荐全量随机'
      }

      const state = buildAllChips(questions, pool.length ? pool : [])
      // 若只有 questions 没有独立 pool，也展示 questions 全部
      setVisibleChips(state.visible)
      poolRef.current = state.pool
      setAllQuestions(state.pool)
      setChipTotal(state.pool.length)
      setChipSourceLabel(sourceLabel)
      setChipsVisible(true)
      setQuestionsOpen(false)
      setMessages([])
      setLastCall(null)
      setCallLog([])
    })()
    return () => {
      alive = false
      abortRef.current?.abort()
    }
  }, [roleSlug])

  useEffect(() => {
    const el = listRef.current
    if (el) el.scrollTop = el.scrollHeight
  }, [messages, sending])

  const empty = messages.length === 0

  const statusHint = useMemo(() => {
    if (!isLoggedIn) {
      if (persona?.mode === 'mock' || roleSlug === 'yingxue') return '游客 · 语录匹配'
      return '游客 · 关键词匹配'
    }
    if (!llmProvider || llmProvider === 'none') return '已登录 · 离线匹配（未选模型）'
    if (llmProvider === 'deepseek') return '已登录 · DeepSeek'
    if (llmProvider === 'qwen') return '已登录 · 通义千问'
    return `已登录 · ${llmProvider}`
  }, [isLoggedIn, persona, roleSlug, llmProvider])

  const chipMeta = useMemo(
    () => ({
      sourceLabel: chipSourceLabel,
      visible: visibleChips.length,
      total: chipTotal,
      standby: 0,
      row1: Math.min(CHIP_ROW1, visibleChips.length),
      row2: Math.max(0, visibleChips.length - CHIP_ROW1),
      quickQuestions: persona?.assets?.persona?.quick_questions ?? persona?.quick_questions?.length,
      quickPool: persona?.assets?.persona?.quick_pool ?? persona?.quick_pool?.length,
    }),
    [chipSourceLabel, visibleChips.length, chipTotal, persona],
  )

  /** 点一个：从列表移除；全部用完后重新随机全量 */
  function takeChip(picked) {
    const next = visibleChips.filter((x) => x !== picked)
    if (next.length === 0 && poolRef.current.length > 0) {
      const reshuffled = shuffle(poolRef.current)
      setVisibleChips(reshuffled)
      return
    }
    setVisibleChips(next)
  }

  async function handleSend(raw) {
    const text = (raw ?? input).trim()
    if (!text || sending || !role) return

    setInput('')
    setSending(true)
    setChipsVisible(false)
    if (taRef.current) taRef.current.style.height = 'auto'

    const userMsg = { id: `u-${Date.now()}`, role: 'user', content: text }
    const history = [...messages, userMsg].map(({ role: r, content }) => ({
      role: r === 'user' ? 'user' : 'assistant',
      content,
    }))
    setMessages((prev) => [...prev, userMsg])

    const aiId = `a-${Date.now()}`
    setMessages((prev) => [...prev, { id: aiId, role: 'assistant', content: '', pending: true }])

    abortRef.current?.abort()
    const ac = new AbortController()
    abortRef.current = ac

    try {
      const result = await sendChatMessage({
        role,
        message: text,
        history,
        token,
        isLoggedIn,
        provider: llmProvider || 'none',
      })
      if (ac.signal.aborted) return

      const { reply: rawReply, source, degraded } = result
      let reply = rawReply
      let variants = Array.isArray(result.variants) ? result.variants.slice() : []
      let variantIndex =
        typeof result.variant_index === 'number' && result.variant_index >= 0
          ? result.variant_index
          : Math.max(
              0,
              variants.findIndex((v) => v.provider === result.provider),
            )

      // 兜底：若接口没带回多版本，再查一次同问题缓存
      if (variants.length < 2 && role?.slug && text) {
        try {
          const extra = await request('/chat-cache-variants.php', {
            method: 'POST',
            body: JSON.stringify({ role: role.slug, message: text }),
          })
          const fetched = Array.isArray(extra?.variants) ? extra.variants : []
          if (fetched.length > variants.length) {
            variants = fetched
            if (extra.question_hash) {
              result.question_hash = extra.question_hash
            }
            const latest = pickLatestVariantIndex(variants)
            if (latest >= 0) {
              variantIndex = latest
              result.from_cache = true
            } else {
              const idx = variants.findIndex((v) => v.is_default || v.provider === 'default')
              variantIndex = idx >= 0 ? idx : 0
            }
          }
        } catch {
          // ignore
        }
      }

      // 降级且后端未附带 default 时，补一条当前离线答案
      if (degraded && variants.length > 0 && source !== 'llm') {
        const hasDefault = variants.some((v) => v.provider === 'default' || v.provider === 'offline' || v.is_default)
        if (!hasDefault) {
          const offline = {
            provider: 'default',
            provider_name: '默认匹配',
            model: '',
            reply,
            from_cache: false,
            is_default: true,
          }
          variants = [offline, ...variants.filter((v) => v.provider !== 'offline' && v.provider !== 'default')]
          variantIndex = 0
        }
      }

      // 规范化 + 去掉 none 伪变体
      variants = variants
        .map((v) => {
          let provider = v.provider || 'unknown'
          let provider_name = v.provider_name || provider
          let is_default = !!v.is_default || provider === 'default' || provider === 'offline'
          if (provider === 'none' || provider === 'current' || provider === 'offline') {
            provider = 'default'
            provider_name =
              provider_name === 'none' || provider_name === 'current' ? '默认语录' : provider_name || '默认语录'
            is_default = true
          }
          return {
            ...v,
            provider,
            provider_name,
            reply: String(v?.reply ?? v?.content ?? '').trim(),
            is_default,
            from_cache: !!v.from_cache && !is_default,
          }
        })
        .filter((v) => v.reply)

      // reply 去重 + 只保留一条 default
      {
        const seen = new Set()
        const cleaned = []
        for (const v of variants) {
          if (seen.has(v.reply)) continue
          if (v.is_default && cleaned.some((x) => x.is_default)) continue
          seen.add(v.reply)
          cleaned.push(v)
        }
        variants = cleaned
      }

      // 只要有模型缓存，一律优先展示最新（含离线/未选模型路径）
      const latest = pickLatestVariantIndex(variants)
      if (latest >= 0) {
        variantIndex = latest
        result.from_cache = true
      } else if (variantIndex >= variants.length) {
        variantIndex = 0
      }

      const active = variants[variantIndex]
      if (active?.reply) {
        reply = active.reply
        result.provider = active.provider
        result.provider_name = active.provider_name
        result.model = active.model || ''
        if (active.from_cache || latest >= 0) {
          result.from_cache = true
        }
      }

      const entry = {
        at: new Date().toISOString(),
        userMessage: text,
        reply,
        source,
        degraded,
        llm_error: result.llm_error || '',
        matched_topic: result.matched_topic,
        quote_id: result.quote_id,
        risk: result.risk,
        score: result.score,
        keys: result.keys,
        endpoint: result.endpoint,
        provider: result.provider,
        provider_name: result.provider_name,
        model: result.model,
        from_cache: result.from_cache,
        question_hash: result.question_hash,
        variants,
        variant_index: variantIndex,
        debug: result.debug,
      }
      setLastCall(entry)
      setCallLog((prev) => [entry, ...prev].slice(0, 30))

      await typewriter(
        reply,
        (partial) => {
          setMessages((prev) =>
            prev.map((m) => (m.id === aiId ? { ...m, content: partial, pending: true, source } : m)),
          )
        },
        ac.signal,
      )

      setMessages((prev) =>
        prev.map((m) =>
          m.id === aiId
            ? {
                ...m,
                content: reply,
                pending: false,
                source,
                degraded,
                provider: result.provider,
                provider_name: result.provider_name || '',
                model: result.model || '',
                from_cache: !!result.from_cache,
                question_hash: result.question_hash || '',
                variants,
                variantIndex,
              }
            : m,
        ),
      )
      if (variants.length > 1) {
        toast(`该问题有 ${variants.length} 个答案，可左右切换`, 'info')
      } else if (degraded && isLoggedIn) {
        const failed = result.requested_provider || '模型'
        const detail = result.llm_error ? `：${result.llm_error}` : ''
        toast(`${failed} 调用失败，已降级离线匹配${detail}`, 'info')
      }
    } catch (e) {
      setMessages((prev) => prev.filter((m) => m.id !== aiId))
      if (e && (e.status === 401 || e.code === 401)) {
        logout()
        toast('登录已失效，请重新登录后再用模型', 'error')
      } else {
        toast(e.message || '发送失败', 'error')
      }
    } finally {
      setSending(false)
      setChipsVisible(true)
    }
  }

  function onChip(text) {
    if (sending) return
    setQuestionsOpen(false)
    takeChip(text)
    handleSend(text)
  }

  useEffect(() => {
    if (!questionsOpen) return undefined
    const onKey = (e) => {
      if (e.key === 'Escape') setQuestionsOpen(false)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [questionsOpen])

  function onKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  function autoGrow(e) {
    const el = e.target
    el.style.height = 'auto'
    el.style.height = `${Math.min(el.scrollHeight, 120)}px`
  }

  const disclaimer =
    (persona?.disclaimer_text && String(persona.disclaimer_text).trim()) ||
    '角色对话仅供娱乐/演示，不构成投资、医疗或法律建议。'

  function clearChat() {
    setMessages([])
    setChipsVisible(true)
    toast('已清空对话', 'ok')
  }

  function openAgentShare() {
    setShareMode('agent')
    setSharePair(null)
    setShareOpen(true)
  }

  function openConversationShare() {
    if (!messages.some((m) => m.content && !m.pending)) {
      toast('还没有可分享的对话', 'info')
      return
    }
    setShareMode('conversation')
    setSharePair(null)
    setShareOpen(true)
  }

  function openContentShare(messageId) {
    const pair = pairAroundMessage(messages, messageId)
    if (!pair || (!pair.userText && !pair.aiText)) {
      toast('暂无可分享内容', 'info')
      return
    }
    setShareMode('content')
    setSharePair(pair)
    setShareOpen(true)
  }

  async function copyOneMessage(m) {
    try {
      await copyText(m.content || '')
      toast('已复制', 'ok')
    } catch (e) {
      toast(e.message || '复制失败', 'error')
    }
  }

  function switchVariant(messageId, delta) {
    setMessages((prev) =>
      prev.map((m) => {
        if (m.id !== messageId || !Array.isArray(m.variants) || m.variants.length < 2) {
          return m
        }
        const len = m.variants.length
        const cur = typeof m.variantIndex === 'number' ? m.variantIndex : 0
        const next = (cur + delta + len) % len
        const v = m.variants[next]
        if (!v) return m
        const isDefault =
          v.provider === 'default' ||
          v.provider === 'offline' ||
          v.provider === 'none' ||
          v.provider === 'current' ||
          !!v.is_default
        const text = v.reply || v.content || ''
        return {
          ...m,
          variantIndex: next,
          content: text,
          provider: v.provider,
          provider_name: v.provider_name || v.provider,
          model: v.model || '',
          source: isDefault ? v.source || 'match' : 'llm',
          degraded: isDefault,
          from_cache: !!v.from_cache && !isDefault,
        }
      }),
    )
  }

  function variantLabel(m) {
    const name = m.provider_name || m.provider || m.model || '模型'
    const model = m.model && m.model !== name ? ` · ${m.model}` : ''
    return `${name}${model}`
  }

  if (loadingRole) {
    return (
      <div className="chat-page chat-page--center">
        <Seo title="加载角色" path={`/chat/${roleSlug || ''}`} />
        <p className="chat-muted">加载角色…</p>
      </div>
    )
  }

  if (!role) {
    return (
      <div className="chat-page chat-page--center">
        <Seo title="角色不存在" path={`/chat/${roleSlug || ''}`} noIndex />
        <h2>角色不存在</h2>
        <p className="chat-muted">这个人设还没入库，或链接写错了。</p>
        <Link to="/" className="btn btn--brand">
          回角色广场
        </Link>
      </div>
    )
  }

  return (
    <div className="chat-page">
      <Seo
        title={`和${role.name}对话`}
        description={
          role.tagline ||
          role.description ||
          `在角色场与「${role.name}」对话，支持离线匹配与大模型。`
        }
        path={`/chat/${role.slug || roleSlug}`}
      />
      <header className="chat-top">
        <button type="button" className="chat-icon" onClick={() => navigate('/')} title="返回">
          ←
        </button>
        <div className="chat-brand">
          <RoleAvatar role={role} className="chat-avatar" />
          <div>
            <h1>{role.name}</h1>
            <p>{statusHint}</p>
          </div>
        </div>
        <div className="chat-tools">
          {!isLoggedIn && (
            <Link to="/login" className="chat-link">
              登录
            </Link>
          )}
          {messages.some((m) => m.content && !m.pending) && (
            <button
              type="button"
              className="chat-icon"
              title="复制/分享对话"
              onClick={openConversationShare}
            >
              💬
            </button>
          )}
          <button type="button" className="chat-icon" title="分享智能体" onClick={openAgentShare}>
            ↗
          </button>
          <button type="button" className="chat-icon" title="清空对话" onClick={clearChat}>
            🗑
          </button>
        </div>
      </header>

      <main className="chat-stream" ref={listRef} aria-live="polite">
        {empty && (
          <div className="chat-empty">
            <RoleAvatar role={role} className="chat-empty__logo" />
            <h2>你好，我是{role.name}</h2>
            <p>{persona?.tagline || role.tagline}</p>
          </div>
        )}

        {messages.map((m) => (
          <div key={m.id} className={`msg ${m.role === 'user' ? 'me' : 'ai'}`}>
            {m.role === 'user' ? (
              <span className="msg__avatar">我</span>
            ) : (
              <RoleAvatar role={role} className="msg__avatar" />
            )}
            <div>
              <MarkdownBubble content={m.content} pending={!!m.pending} />
              {!m.pending && m.content && (
                <div className="msg__actions">
                  <button type="button" className="msg-act" onClick={() => copyOneMessage(m)}>
                    复制
                  </button>
                  <button type="button" className="msg-act" onClick={() => openContentShare(m.id)}>
                    分享
                  </button>
                </div>
              )}
              {!m.pending &&
                m.role === 'assistant' &&
                Array.isArray(m.variants) &&
                m.variants.length > 1 && (
                  <div className="msg__variants" role="group" aria-label="切换模型答案">
                    <button
                      type="button"
                      className="msg-var-btn"
                      title="上一个模型答案"
                      onClick={() => switchVariant(m.id, -1)}
                    >
                      ‹
                    </button>
                    <span className="msg__variants-label" title="可切换：默认匹配 / 各模型缓存答案">
                      答案 {(typeof m.variantIndex === 'number' ? m.variantIndex : 0) + 1}/{m.variants.length}
                      {' · '}
                      {variantLabel(m)}
                      {m.from_cache ? ' · 缓存' : ''}
                      {m.provider === 'default' || m.provider === 'offline' ? ' · 默认' : ''}
                    </span>
                    <button
                      type="button"
                      className="msg-var-btn"
                      title="下一个答案"
                      onClick={() => switchVariant(m.id, 1)}
                    >
                      ›
                    </button>
                  </div>
                )}
              {!m.pending &&
                m.role === 'assistant' &&
                (!m.variants || m.variants.length <= 1) &&
                m.source === 'llm' &&
                (m.provider_name || m.provider) && (
                  <span className="msg__model-tag">
                    {variantLabel(m)}
                    {m.from_cache ? ' · 缓存' : ''}
                  </span>
                )}
              {debugEnabled && !m.pending && m.source && m.role === 'assistant' && (
                <span className="msg__meta">
                  {m.source === 'llm' || m.source === 'live'
                    ? 'AI'
                    : m.source === 'quotes' || m.source === 'mock'
                      ? '语录'
                      : m.source === 'match'
                        ? '匹配'
                        : '本地兜底'}
                  {m.degraded ? ' · 已降级' : ''}
                  {m.from_cache ? ' · 缓存' : ''}
                  {Array.isArray(m.variants) && m.variants.length > 1
                    ? ` · ${m.variants.length}个答案`
                    : ''}
                </span>
              )}
            </div>
          </div>
        ))}
      </main>

      {chipsVisible && visibleChips.length > 0 && (
        <div className="chat-suggest" aria-label="推荐话术">
          <div className="chat-suggest__head">
            <p className="chat-suggest__label">为你推荐</p>
            {allQuestions.length > 0 && (
              <button
                type="button"
                className="chat-suggest__more"
                onClick={() => setQuestionsOpen(true)}
                disabled={sending}
              >
                更多
              </button>
            )}
          </div>
          <div className="chat-chips-row chat-chips-row--1">
            {visibleChips.slice(0, CHIP_ROW1).map((c) => (
              <button
                key={`r1-${c}`}
                type="button"
                className="chip"
                onClick={() => onChip(c)}
                disabled={sending}
                title={c}
              >
                {c}
              </button>
            ))}
          </div>
          {visibleChips.length > CHIP_ROW1 && (
            <div className="chat-chips-row chat-chips-row--2">
              {visibleChips.slice(CHIP_ROW1).map((c) => (
                <button
                  key={`r2-${c}`}
                  type="button"
                  className="chip"
                  onClick={() => onChip(c)}
                  disabled={sending}
                  title={c}
                >
                  {c}
                </button>
              ))}
            </div>
          )}
        </div>
      )}

      {questionsOpen && (
        <div className="q-sheet" role="dialog" aria-modal="true" aria-label="全部推荐问题">
          <button
            type="button"
            className="q-sheet__mask"
            aria-label="关闭"
            onClick={() => setQuestionsOpen(false)}
          />
          <div className="q-sheet__panel">
            <div className="q-sheet__head">
              <div>
                <h3>全部问题</h3>
                <p>按配置正序 · 共 {allQuestions.length} 条 · 点击即可提问</p>
              </div>
              <button
                type="button"
                className="q-sheet__close"
                aria-label="关闭"
                onClick={() => setQuestionsOpen(false)}
              >
                ×
              </button>
            </div>
            <ol className="q-sheet__list">
              {allQuestions.map((q, i) => (
                <li key={`q-${i}-${q}`}>
                  <button
                    type="button"
                    className="q-sheet__item"
                    disabled={sending}
                    onClick={() => onChip(q)}
                  >
                    <span className="q-sheet__idx">{i + 1}</span>
                    <span className="q-sheet__text">{q}</span>
                  </button>
                </li>
              ))}
            </ol>
          </div>
        </div>
      )}

      <footer className="chat-composer">
        <div className="chat-inputbar">
          <textarea
            ref={taRef}
            rows={1}
            value={input}
            disabled={sending}
            placeholder="问点什么…支持 Markdown（Enter 发送 / Shift+Enter 换行）"
            onChange={(e) => {
              setInput(e.target.value)
              autoGrow(e)
            }}
            onKeyDown={onKeyDown}
          />
          <button
            type="button"
            className="chat-send"
            disabled={sending || !input.trim()}
            onClick={() => handleSend()}
            title="发送"
          >
            ➤
          </button>
        </div>
        <p className="chat-disclaimer">{disclaimer}</p>
      </footer>

      {shareOpen && (
        <ShareSheet
          open
          onClose={() => setShareOpen(false)}
          mode={shareMode}
          role={role}
          tagline={persona?.tagline || role.tagline}
          messages={messages}
          pair={sharePair}
        />
      )}

      <ChatDebugPanel
        enabled={debugEnabled}
        open={debugOpen}
        onToggle={() => setDebugOpen((v) => !v)}
        onClose={() => setDebugOpen(false)}
        roleSlug={roleSlug}
        persona={persona}
        chipMeta={chipMeta}
        lastCall={lastCall}
        callLog={callLog}
      />
    </div>
  )
}
