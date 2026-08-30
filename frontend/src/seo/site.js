/** 站点 SEO 常量（吴庆宝 · wuqingbao） */
export const SITE = {
  name: '角色场',
  tagline: '多角色克隆对话',
  titleDefault: '角色场 · 多角色克隆对话',
  description:
    '角色场是吴庆宝（wuqingbao）开源的多角色克隆对话平台：名人戏仿、客服、星座、榜样等角色可 MD 驱动扩展，支持游客离线匹配与登录后大模型对话。',
  keywords: [
    '角色场',
    '多角色对话',
    'AI角色扮演',
    '克隆对话',
    '吴庆宝',
    'wuqingbao',
    'multi-role-dialog-skill',
    '客服机器人',
    '星座助手',
  ].join(','),
  url: 'https://multi-role-dialog-skill.wuqingbao.com',
  author: '吴庆宝',
  authorEn: 'wuqingbao',
  locale: 'zh_CN',
  themeColor: '#0f1115',
}

export function absoluteUrl(path = '/') {
  const p = path.startsWith('/') ? path : `/${path}`
  return `${SITE.url}${p}`
}
