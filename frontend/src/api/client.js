/**
 * 前端请求封装
 *
 * .env 中 VITE_API_BASE：
 * - 有值（如 https://api.example.com/api）→ 走远程
 * - 未配置 / 留空 → 本地默认 /api（开发代理）
 */

function stripTrailingSlash(url) {
  return String(url || '').trim().replace(/\/+$/, '')
}

const configured = stripTrailingSlash(import.meta.env.VITE_API_BASE || '')
const API_BASE = configured || '/api'

/** 远程 API 时推导静态资源源站（去掉末尾 /api）；本地相对路径不拼前缀 */
const ASSET_BASE = /^https?:\/\//i.test(API_BASE)
  ? stripTrailingSlash(API_BASE.replace(/\/api$/i, ''))
  : ''

/**
 * 相对路径 /roles/... 在跨域时接到后端源站；本地保持相对路径。
 */
function resolveAssetUrl(src) {
  const s = String(src || '').trim()
  if (!s) return ''
  if (/^(https?:|data:|blob:)/i.test(s)) return s
  if (s.startsWith('/') && ASSET_BASE) return `${ASSET_BASE}${s}`
  return s
}

async function request(path, options = {}) {
  const { headers: extraHeaders, ...rest } = options
  const urlPath = path.startsWith('/') ? path : `/${path}`
  const res = await fetch(`${API_BASE}${urlPath}`, {
    ...rest,
    headers: {
      'Content-Type': 'application/json',
      ...(extraHeaders || {}),
    },
  })

  let json = null
  try {
    json = await res.json()
  } catch {
    json = null
  }

  if (res.status === 401) {
    const err = new Error((json && json.msg) || '未登录或登录已失效')
    err.code = 401
    err.status = 401
    throw err
  }

  if (!res.ok) {
    const err = new Error((json && json.msg) || `HTTP ${res.status}`)
    err.status = res.status
    throw err
  }

  if (json && json.code !== undefined && json.code !== 200) {
    const err = new Error(json.msg || '请求失败')
    err.code = json.code
    throw err
  }
  return json && json.data !== undefined ? json.data : json
}

export { API_BASE, ASSET_BASE, resolveAssetUrl, request }
