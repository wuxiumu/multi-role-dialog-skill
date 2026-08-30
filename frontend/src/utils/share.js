/** 剪贴板 / 系统分享 / 对话文案 */

export function chatUrlForRole(roleSlug) {
  return `${window.location.origin}/chat/${encodeURIComponent(roleSlug)}`
}

export async function copyText(text) {
  const value = String(text || '')
  if (!value) throw new Error('没有可复制的内容')
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value)
    return
  }
  const ta = document.createElement('textarea')
  ta.value = value
  ta.setAttribute('readonly', '')
  ta.style.position = 'fixed'
  ta.style.left = '-9999px'
  document.body.appendChild(ta)
  ta.select()
  const ok = document.execCommand('copy')
  document.body.removeChild(ta)
  if (!ok) throw new Error('复制失败')
}

export function canNativeShare() {
  return typeof navigator !== 'undefined' && typeof navigator.share === 'function'
}

export function canNativeShareFiles() {
  return (
    canNativeShare() &&
    typeof navigator.canShare === 'function' &&
    navigator.canShare({ files: [new File([''], 't.png', { type: 'image/png' })] })
  )
}

export async function nativeShare({ title, text, url, files } = {}) {
  if (!canNativeShare()) throw new Error('当前环境不支持系统分享')
  const payload = {}
  if (title) payload.title = title
  if (text) payload.text = text
  if (url) payload.url = url
  if (files?.length) payload.files = files
  if (files?.length && navigator.canShare && !navigator.canShare(payload)) {
    delete payload.files
  }
  await navigator.share(payload)
}

export function formatConversationText(role, messages) {
  const name = role?.name || '智能体'
  const lines = [`【${name}】对话记录`, '']
  for (const m of messages || []) {
    if (!m?.content || m.pending) continue
    const who = m.role === 'user' ? '我' : name
    lines.push(`${who}：${m.content}`)
    lines.push('')
  }
  lines.push(`来自 ${chatUrlForRole(role.slug)}`)
  return lines.join('\n').trim()
}

export function formatAgentShareText(role, tagline) {
  const name = role?.name || '智能体'
  const tip = (tagline || role?.tagline || '').trim()
  const url = chatUrlForRole(role.slug)
  return [`我在和「${name}」聊天`, tip ? tip : null, url].filter(Boolean).join('\n')
}

export function formatMessageShareText(role, { userText, aiText, singleText, singleRole } = {}) {
  const name = role?.name || '智能体'
  const url = chatUrlForRole(role.slug)
  if (singleText) {
    const who = singleRole === 'user' ? '我' : name
    return [`【${name}】`, `${who}：${singleText}`, '', url].join('\n')
  }
  const parts = [`【${name}】精彩对话`, '']
  if (userText) parts.push(`我：${userText}`, '')
  if (aiText) parts.push(`${name}：${aiText}`, '')
  parts.push(url)
  return parts.join('\n').trim()
}

/** 取某条消息的分享上下文：助手消息尽量带上相邻用户问句 */
export function pairAroundMessage(messages, messageId) {
  const list = messages || []
  const idx = list.findIndex((m) => m.id === messageId)
  if (idx < 0) return null
  const cur = list[idx]
  if (cur.role === 'user') {
    const next = list[idx + 1]
    return {
      userText: cur.content,
      aiText: next?.role === 'assistant' && !next.pending ? next.content : '',
      focusId: cur.id,
    }
  }
  let userText = ''
  for (let i = idx - 1; i >= 0; i -= 1) {
    if (list[i].role === 'user') {
      userText = list[i].content
      break
    }
  }
  return {
    userText,
    aiText: cur.content,
    focusId: cur.id,
  }
}
