/**
 * 角色列表 API
 * 后端 role-list.php 未就绪时，自动降级到本地 mock（含赢学等示例）
 */
import { request } from './client.js'
import { MOCK_ROLES } from './mockRoles.js'

export async function fetchRoleList() {
  try {
    const data = await request('/role-list.php')
    if (Array.isArray(data) && data.length > 0) return data
    if (data?.list && data.list.length > 0) return data.list
    return MOCK_ROLES
  } catch {
    // PHP API 尚未迁移时走 mock，保证首页可独立开发
    return MOCK_ROLES
  }
}
