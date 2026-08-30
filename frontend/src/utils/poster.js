/** Canvas 海报：智能体卡 / 对话卡（含可扫码二维码） */
import QRCode from 'qrcode'

function roundRect(ctx, x, y, w, h, r) {
  const radius = Math.min(r, w / 2, h / 2)
  ctx.beginPath()
  ctx.moveTo(x + radius, y)
  ctx.arcTo(x + w, y, x + w, y + h, radius)
  ctx.arcTo(x + w, y + h, x, y + h, radius)
  ctx.arcTo(x, y + h, x, y, radius)
  ctx.arcTo(x, y, x + w, y, radius)
  ctx.closePath()
}

function wrapLines(ctx, text, maxWidth, maxLines = 12) {
  const raw = String(text || '')
    .replace(/\r\n/g, '\n')
    .trim()
  if (!raw) return []
  const paragraphs = raw.split('\n')
  const lines = []
  for (const para of paragraphs) {
    if (!para) {
      lines.push('')
      if (lines.length >= maxLines) break
      continue
    }
    let line = ''
    for (const ch of para) {
      const test = line + ch
      if (ctx.measureText(test).width > maxWidth && line) {
        lines.push(line)
        line = ch
        if (lines.length >= maxLines) break
      } else {
        line = test
      }
    }
    if (lines.length >= maxLines) break
    if (line) lines.push(line)
    if (lines.length >= maxLines) break
  }
  if (lines.length > maxLines) {
    const clipped = lines.slice(0, maxLines)
    const last = clipped[maxLines - 1]
    clipped[maxLines - 1] = `${last.slice(0, Math.max(0, last.length - 1))}…`
    return clipped
  }
  if (lines.length === maxLines && raw.length > 0) {
    const joined = lines.join('')
    if (joined.replace(/\s/g, '').length < raw.replace(/\s/g, '').length) {
      const last = lines[maxLines - 1]
      lines[maxLines - 1] = `${last.slice(0, Math.max(0, last.length - 1))}…`
    }
  }
  return lines
}

function loadImage(src) {
  return new Promise((resolve) => {
    if (!src) return resolve(null)
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.onload = () => resolve(img)
    img.onerror = () => resolve(null)
    img.src = src
  })
}

/** 生成指向对话页的二维码 canvas */
async function makeQrCanvas(text, size = 200) {
  const value = String(text || '').trim()
  if (!value) return null
  const c = document.createElement('canvas')
  await QRCode.toCanvas(c, value, {
    width: size,
    margin: 1,
    errorCorrectionLevel: 'M',
    color: {
      dark: '#1a1205',
      light: '#ffffff',
    },
  })
  return c
}

function drawBackground(ctx, w, h) {
  const g = ctx.createLinearGradient(0, 0, w, h)
  g.addColorStop(0, '#141820')
  g.addColorStop(0.45, '#1a1f2a')
  g.addColorStop(1, '#0f1218')
  ctx.fillStyle = g
  ctx.fillRect(0, 0, w, h)

  const orb = ctx.createRadialGradient(w * 0.85, h * 0.08, 20, w * 0.85, h * 0.08, w * 0.55)
  orb.addColorStop(0, 'rgba(255, 182, 77, 0.22)')
  orb.addColorStop(1, 'rgba(255, 182, 77, 0)')
  ctx.fillStyle = orb
  ctx.fillRect(0, 0, w, h)

  const orb2 = ctx.createRadialGradient(w * 0.1, h * 0.75, 10, w * 0.1, h * 0.75, w * 0.5)
  orb2.addColorStop(0, 'rgba(255, 122, 89, 0.14)')
  orb2.addColorStop(1, 'rgba(255, 122, 89, 0)')
  ctx.fillStyle = orb2
  ctx.fillRect(0, 0, w, h)
}

function drawAvatar(ctx, img, letter, color, x, y, size) {
  ctx.save()
  roundRect(ctx, x, y, size, size, size * 0.22)
  ctx.clip()
  if (img) {
    const s = Math.min(img.width, img.height)
    const sx = (img.width - s) / 2
    const sy = (img.height - s) / 2
    ctx.drawImage(img, sx, sy, s, s, x, y, size, size)
  } else {
    ctx.fillStyle = color || '#ffb64d'
    ctx.fillRect(x, y, size, size)
    ctx.fillStyle = '#1a1205'
    ctx.font = `800 ${Math.floor(size * 0.42)}px "Noto Sans SC", "PingFang SC", sans-serif`
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(letter || '?', x + size / 2, y + size / 2 + 2)
  }
  ctx.restore()
}

/** 页脚：文案；可选右侧可扫二维码 */
async function drawFooter(ctx, w, h, url, { withQr = true } = {}) {
  const pad = 56
  const footerTop = withQr ? h - 188 : h - 120

  ctx.fillStyle = 'rgba(255,255,255,0.08)'
  ctx.fillRect(pad, footerTop, w - pad * 2, 1)

  let textMax = w - pad * 2
  if (withQr) {
    const qrSize = 132
    const qr = await makeQrCanvas(url, qrSize * 2)
    const qx = w - pad - qrSize - 8
    const qy = footerTop + 28
    ctx.fillStyle = '#ffffff'
    roundRect(ctx, qx - 8, qy - 8, qrSize + 16, qrSize + 16, 14)
    ctx.fill()
    if (qr) ctx.drawImage(qr, qx, qy, qrSize, qrSize)
    textMax = qx - pad - 24
  }

  ctx.fillStyle = '#ffb64d'
  ctx.font = '700 22px "Outfit", "Noto Sans SC", sans-serif'
  ctx.textAlign = 'left'
  ctx.fillText('RoleCast', pad, footerTop + 48)

  ctx.fillStyle = '#e8eaed'
  ctx.font = '600 22px "Noto Sans SC", sans-serif'
  ctx.fillText(withQr ? '扫一扫 · 打开对话' : '保存海报 · 扫码开聊', pad, footerTop + 84)

  ctx.fillStyle = '#9aa3b2'
  ctx.font = '400 16px "Noto Sans SC", sans-serif'
  const short = String(url || '').replace(/^https?:\/\//, '')
  const lines = wrapLines(ctx, short, textMax, withQr ? 3 : 2)
  lines.forEach((line, i) => {
    ctx.fillText(line, pad, footerTop + 118 + i * 22)
  })
}

/** 智能体海报中部大二维码 */
async function drawCenterQr(ctx, w, url, y) {
  const size = 200
  const qr = await makeQrCanvas(url, size * 2)
  const x = (w - size) / 2

  ctx.fillStyle = '#ffffff'
  roundRect(ctx, x - 14, y - 14, size + 28, size + 28, 18)
  ctx.fill()
  if (qr) ctx.drawImage(qr, x, y, size, size)

  ctx.fillStyle = '#ffb64d'
  ctx.font = '600 24px "Noto Sans SC", sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText('扫一扫开始对话', w / 2, y + size + 48)
}

/**
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function renderAgentPoster({ role, tagline, url }) {
  const W = 750
  const H = 1280
  const canvas = document.createElement('canvas')
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')
  drawBackground(ctx, W, H)

  const img = await loadImage(role?.avatarSrc)
  const letter = role?.avatar || (role?.name ? String(role.name).slice(0, 1) : '?')
  const color = typeof role?.avatarColor === 'string' && role.avatarColor.startsWith('#')
    ? role.avatarColor
    : '#ffb64d'

  ctx.fillStyle = 'rgba(23, 26, 33, 0.72)'
  roundRect(ctx, 48, 88, W - 96, 960, 28)
  ctx.fill()
  ctx.strokeStyle = 'rgba(255, 182, 77, 0.28)'
  ctx.lineWidth = 2
  roundRect(ctx, 48, 88, W - 96, 960, 28)
  ctx.stroke()

  drawAvatar(ctx, img, letter, color, (W - 140) / 2, 140, 140)

  ctx.fillStyle = '#e8eaed'
  ctx.font = '800 46px "Noto Sans SC", "PingFang SC", sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText(role?.name || '智能体', W / 2, 340)

  if (role?.category) {
    ctx.fillStyle = 'rgba(255, 182, 77, 0.9)'
    ctx.font = '600 22px "Noto Sans SC", sans-serif'
    ctx.fillText(role.category, W / 2, 384)
  }

  const tip = (tagline || role?.tagline || '来和我聊两句').trim()
  ctx.fillStyle = '#9aa3b2'
  ctx.font = '400 26px "Noto Sans SC", sans-serif'
  const tipLines = wrapLines(ctx, tip, W - 180, 3)
  tipLines.forEach((line, i) => {
    ctx.fillText(line, W / 2, 440 + i * 38)
  })

  await drawCenterQr(ctx, W, url, 560)
  await drawFooter(ctx, W, H, url, { withQr: false })
  return canvas
}

/**
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function renderDialoguePoster({ role, userText, aiText, url }) {
  const W = 750
  const H = 1400
  const canvas = document.createElement('canvas')
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')
  drawBackground(ctx, W, H)

  const img = await loadImage(role?.avatarSrc)
  const letter = role?.avatar || (role?.name ? String(role.name).slice(0, 1) : '?')
  const color = typeof role?.avatarColor === 'string' && role.avatarColor.startsWith('#')
    ? role.avatarColor
    : '#ffb64d'

  drawAvatar(ctx, img, letter, color, 56, 64, 72)
  ctx.textAlign = 'left'
  ctx.fillStyle = '#e8eaed'
  ctx.font = '800 34px "Noto Sans SC", sans-serif'
  ctx.fillText(role?.name || '智能体', 148, 100)
  ctx.fillStyle = '#9aa3b2'
  ctx.font = '400 20px "Noto Sans SC", sans-serif'
  ctx.fillText('对话分享 · 扫码可续聊', 148, 132)

  let y = 200
  const footerReserve = 220

  if (userText) {
    ctx.font = '400 26px "Noto Sans SC", sans-serif'
    const qLines = wrapLines(ctx, userText, W - 160, 6)
    const boxH = 52 + qLines.length * 40
    ctx.fillStyle = 'rgba(43, 108, 255, 0.18)'
    roundRect(ctx, 56, y, W - 112, boxH, 20)
    ctx.fill()
    ctx.fillStyle = '#8eb4ff'
    ctx.font = '700 20px "Noto Sans SC", sans-serif'
    ctx.fillText('我', 80, y + 32)
    ctx.fillStyle = '#e8eaed'
    ctx.font = '400 26px "Noto Sans SC", sans-serif'
    qLines.forEach((line, i) => {
      ctx.fillText(line, 80, y + 72 + i * 40)
    })
    y += boxH + 28
  }

  if (aiText) {
    ctx.font = '400 26px "Noto Sans SC", sans-serif'
    const aLines = wrapLines(ctx, aiText, W - 160, 14)
    const boxH = 52 + aLines.length * 40
    const maxBox = H - y - footerReserve
    const usedH = Math.min(boxH, maxBox)
    ctx.fillStyle = 'rgba(32, 36, 46, 0.92)'
    roundRect(ctx, 56, y, W - 112, usedH, 20)
    ctx.fill()
    ctx.strokeStyle = 'rgba(255, 182, 77, 0.25)'
    ctx.lineWidth = 1.5
    roundRect(ctx, 56, y, W - 112, usedH, 20)
    ctx.stroke()

    ctx.fillStyle = '#ffb64d'
    ctx.font = '700 20px "Noto Sans SC", sans-serif'
    ctx.fillText(role?.name || 'TA', 80, y + 32)
    ctx.fillStyle = '#e8eaed'
    ctx.font = '400 26px "Noto Sans SC", sans-serif'
    const visible = Math.max(1, Math.floor((usedH - 52) / 40))
    aLines.slice(0, visible).forEach((line, i) => {
      const text = i === visible - 1 && aLines.length > visible ? `${line.replace(/…$/, '')}…` : line
      ctx.fillText(text, 80, y + 72 + i * 40)
    })
  }

  await drawFooter(ctx, W, H, url)
  return canvas
}

export function canvasToDataUrl(canvas, type = 'image/png', quality = 0.92) {
  return canvas.toDataURL(type, quality)
}

export function canvasToBlob(canvas, type = 'image/png', quality = 0.92) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob) reject(new Error('海报生成失败'))
      else resolve(blob)
    }, type, quality)
  })
}

export async function downloadPoster(canvas, filename = 'share-poster.png') {
  const blob = await canvasToBlob(canvas)
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1500)
}

export async function sharePosterFile(canvas, { title, text, filename = 'share-poster.png' } = {}) {
  const blob = await canvasToBlob(canvas)
  const file = new File([blob], filename, { type: blob.type || 'image/png' })
  if (navigator.canShare?.({ files: [file] })) {
    await navigator.share({ title, text, files: [file] })
    return true
  }
  return false
}
