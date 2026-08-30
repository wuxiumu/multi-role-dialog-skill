/**
 * 前端请求封装
 *
 * 环境变量（见 .env.example）：
 * - VITE_API_BASE   接口根，如 /api 或 https://api.example.com/api
 * - VITE_ASSET_BASE 角色静态资源源站，如 https://api.example.com（可选）
 */

function stripTrailingSlash(url) {
  return String(url || '').trim().replace(/\/+$/, '')
}

const rawApiBase = stripTrailingSlash(import.meta.env.VITE_API_BASE || '/api')
const API_BASE = rawApiBase || '/api'

/** 绝对 API 地址时，推导静态资源源站（去掉末尾 /api） */
function deriveAssetBase(apiBase) {
  if (!/^https?:\/\//i.test(apiBase)) return ''
  return stripTrailingSlash(apiBase.replace(/\/api$/i, ''))
}

const ASSET_BASE = stripTrailingSlash(
  import.meta.env.VITE_ASSET_BASE || deriveAssetBase(API_BASE) || '',
)

/**
 * 把后端返回的相对资源路径（如 /roles/yingxue/asset/logo.png）接到资源域名。
 * 已是 http(s) / data: 则原样返回。
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
