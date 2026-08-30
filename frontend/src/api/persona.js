import { request } from './client.js'

/**
 * 拉取角色人格卡（快捷提问 / 口头禅 / 免责等）
 * @returns {Promise<object|null>}
 */
export async function fetchPersona(roleSlug) {
  if (!roleSlug) return null
  try {
    const data = await request(`/persona.php?role=${encodeURIComponent(roleSlug)}`)
    return data && typeof data === 'object' ? data : null
  } catch {
    return null
  }
}
