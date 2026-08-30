import { Link } from 'react-router-dom'
import { RoleAvatar } from './RoleAvatar.jsx'
import './RoleAvatar.css'
import './RoleCard.css'

export function RoleCard({ role, index = 0 }) {
  return (
    <Link
      to={`/chat/${role.slug}`}
      className={`role-card ${role.featured ? 'role-card--featured' : ''}`}
      style={{ animationDelay: `${Math.min(index, 8) * 45}ms` }}
    >
      <div className="role-card__head">
        <RoleAvatar role={role} className="role-card__avatar" />
        <div className="role-card__meta">
          <div className="role-card__title-row">
            <h3>{role.name}</h3>
            <span className="role-card__cat">{role.category}</span>
          </div>
          <p className="role-card__tagline">{role.tagline}</p>
        </div>
      </div>
      <p className="role-card__desc">{role.description}</p>
      <div className="role-card__foot">
        <div className="role-card__tags">
          {(role.tags || []).slice(0, 3).map((tag) => (
            <span key={tag}>{tag}</span>
          ))}
        </div>
        <span className="role-card__cta">开始对话 →</span>
      </div>
    </Link>
  )
}
