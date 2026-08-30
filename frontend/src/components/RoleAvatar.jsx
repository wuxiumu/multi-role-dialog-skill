/**
 * 角色头像：优先 avatarSrc（本地 asset / CDN），否则文字 avatar + 渐变底。
 */
export function RoleAvatar({
  role,
  className = '',
  size,
  style,
  fallbackText,
}) {
  const src = role?.avatarSrc || ''
  const text = fallbackText ?? role?.avatar ?? (role?.name ? String(role.name).slice(0, 1) : '?')
  const bg = role?.avatarColor || 'linear-gradient(135deg,#94a3b8,#64748b)'

  const merged = {
    background: src ? 'transparent' : bg,
    ...(size ? { width: size, height: size } : null),
    ...style,
  }

  if (src) {
    return (
      <span className={`role-avatar role-avatar--img ${className}`.trim()} style={merged} aria-hidden>
        <img src={src} alt="" loading="lazy" decoding="async" />
      </span>
    )
  }

  return (
    <span className={`role-avatar ${className}`.trim()} style={merged} aria-hidden>
      {text}
    </span>
  )
}
