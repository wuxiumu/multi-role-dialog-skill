import { useEffect, useMemo, useState } from 'react'
import { toast } from './Toast.jsx'
import {
  canNativeShare,
  chatUrlForRole,
  copyText,
  formatAgentShareText,
  formatConversationText,
  formatMessageShareText,
  nativeShare,
} from '../utils/share.js'
import {
  canvasToDataUrl,
  downloadPoster,
  renderAgentPoster,
  renderDialoguePoster,
  sharePosterFile,
} from '../utils/poster.js'
import './ShareSheet.css'

/**
 * mode:
 * - agent: 分享当前智能体
 * - content: 分享单条/一对问答
 * - conversation: 整段对话
 */
export function ShareSheet({ open, onClose, mode = 'agent', role, tagline, messages = [], pair = null }) {
  const [busy, setBusy] = useState('')
  const [posterUrl, setPosterUrl] = useState('')
  const [posterCanvas, setPosterCanvas] = useState(null)

  const url = useMemo(() => (role?.slug ? chatUrlForRole(role.slug) : ''), [role])

  const title = useMemo(() => {
    if (mode === 'agent') return '分享智能体'
    if (mode === 'conversation') return '分享对话'
    return '分享内容'
  }, [mode])

  const shareText = useMemo(() => {
    if (!role) return ''
    if (mode === 'agent') return formatAgentShareText(role, tagline)
    if (mode === 'conversation') return formatConversationText(role, messages)
    return formatMessageShareText(role, {
      userText: pair?.userText,
      aiText: pair?.aiText,
    })
  }, [mode, role, tagline, messages, pair])

  useEffect(() => {
    if (!open) return undefined
    const onKey = (e) => {
      if (e.key === 'Escape') handleClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  function handleClose() {
    setPosterUrl('')
    setPosterCanvas(null)
    setBusy('')
    onClose?.()
  }

  if (!open || !role) return null

  async function withBusy(key, fn) {
    if (busy) return
    setBusy(key)
    try {
      await fn()
    } catch (e) {
      if (e?.name !== 'AbortError') toast(e?.message || '操作失败', 'error')
    } finally {
      setBusy('')
    }
  }

  function onCopyLink() {
    withBusy('link', async () => {
      await copyText(url)
      toast('链接已复制', 'ok')
    })
  }

  function onCopyText() {
    withBusy('text', async () => {
      await copyText(shareText)
      toast(mode === 'conversation' ? '对话已复制' : '文案已复制', 'ok')
    })
  }

  function onSystemShare() {
    withBusy('sys', async () => {
      if (!canNativeShare()) {
        await copyText(shareText)
        toast('已复制文案，可粘贴分享', 'info')
        return
      }
      await nativeShare({
        title: role.name,
        text: shareText,
        url,
      })
    })
  }

  function onMakePoster() {
    withBusy('poster', async () => {
      let canvas
      if (mode === 'agent') {
        canvas = await renderAgentPoster({ role, tagline, url })
      } else {
        let userText = pair?.userText || ''
        let aiText = pair?.aiText || ''
        if (mode === 'conversation' && !userText && !aiText) {
          const finished = (messages || []).filter((m) => m.content && !m.pending)
          const lastAi = [...finished].reverse().find((m) => m.role === 'assistant')
          if (lastAi) {
            aiText = lastAi.content
            const idx = finished.indexOf(lastAi)
            for (let i = idx - 1; i >= 0; i -= 1) {
              if (finished[i].role === 'user') {
                userText = finished[i].content
                break
              }
            }
          }
        }
        if (!userText && !aiText) {
          toast('暂无可生成海报的对话内容', 'info')
          return
        }
        canvas = await renderDialoguePoster({ role, userText, aiText, url })
      }
      setPosterCanvas(canvas)
      setPosterUrl(canvasToDataUrl(canvas))
      toast('海报已生成', 'ok')
    })
  }

  function onSavePoster() {
    withBusy('save', async () => {
      if (!posterCanvas) return
      const name =
        mode === 'agent'
          ? `${role.slug || 'agent'}-poster.png`
          : `${role.slug || 'chat'}-dialogue.png`
      await downloadPoster(posterCanvas, name)
      toast('海报已保存', 'ok')
    })
  }

  function onSharePoster() {
    withBusy('sharePoster', async () => {
      if (!posterCanvas) return
      const ok = await sharePosterFile(posterCanvas, {
        title: role.name,
        text: shareText,
        filename: `${role.slug || 'share'}-poster.png`,
      })
      if (!ok) {
        await downloadPoster(posterCanvas, `${role.slug || 'share'}-poster.png`)
        toast('已下载海报，可手动分享', 'info')
      }
    })
  }

  return (
    <div className="share-sheet" role="dialog" aria-modal="true" aria-label={title}>
      <button type="button" className="share-sheet__mask" aria-label="关闭" onClick={handleClose} />
      <div className="share-sheet__panel">
        <div className="share-sheet__head">
          <div>
            <h3>{title}</h3>
            <p>{role.name}</p>
          </div>
          <button type="button" className="share-sheet__close" onClick={handleClose} aria-label="关闭">
            ×
          </button>
        </div>

        <div className="share-sheet__actions">
          <button type="button" className="share-act" disabled={!!busy} onClick={onCopyLink}>
            <span className="share-act__ico">🔗</span>
            <span>{busy === 'link' ? '复制中…' : '复制链接'}</span>
          </button>
          <button type="button" className="share-act" disabled={!!busy} onClick={onCopyText}>
            <span className="share-act__ico">📋</span>
            <span>
              {busy === 'text' ? '复制中…' : mode === 'conversation' ? '复制对话' : '复制文案'}
            </span>
          </button>
          <button type="button" className="share-act" disabled={!!busy} onClick={onSystemShare}>
            <span className="share-act__ico">↗</span>
            <span>{busy === 'sys' ? '分享中…' : '系统分享'}</span>
          </button>
          <button type="button" className="share-act share-act--brand" disabled={!!busy} onClick={onMakePoster}>
            <span className="share-act__ico">🖼</span>
            <span>{busy === 'poster' ? '生成中…' : '生成海报'}</span>
          </button>
        </div>

        {posterUrl && (
          <div className="share-poster">
            <img src={posterUrl} alt="分享海报预览" />
            <div className="share-poster__bar">
              <button type="button" className="btn btn--ghost" disabled={!!busy} onClick={onSavePoster}>
                {busy === 'save' ? '保存中…' : '保存图片'}
              </button>
              <button type="button" className="btn btn--brand" disabled={!!busy} onClick={onSharePoster}>
                {busy === 'sharePoster' ? '分享中…' : '分享海报'}
              </button>
            </div>
          </div>
        )}

        <p className="share-sheet__hint">
          海报含二维码，微信/系统扫一扫可打开对话页；本地开发扫码需手机能访问同一地址。
        </p>
      </div>
    </div>
  )
}
