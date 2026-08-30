/** 前端请求封装 */
const API_BASE = import.meta.env.VITE_API_BASE || '/api'

async function request(path, options = {}) {
  const { headers: extraHeaders, ...rest } = options
  const res = await fetch(`${API_BASE}${path}`, {
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

export { API_BASE, request }
