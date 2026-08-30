import { Link, NavLink } from 'react-router-dom'
import { useAuth } from '../store/AuthContext.jsx'
import './Topbar.css'

export function Topbar() {
  const { isLoggedIn, user, llmProvider, logout } = useAuth()

  const modelHint =
    llmProvider === 'none' || !llmProvider
      ? '离线'
      : llmProvider === 'deepseek'
        ? 'DeepSeek'
        : llmProvider === 'qwen'
          ? '千问'
          : llmProvider

  return (
    <header className="topbar">
      <div className="topbar__inner">
        <Link to="/" className="brand">
          <span className="brand__logo" aria-hidden>
            角
          </span>
          <span className="brand__text">
            <strong>角色场</strong>
            <em>多角色克隆对话</em>
          </span>
        </Link>

        <nav className="topbar__nav" aria-label="主导航">
          <NavLink to="/" end className={({ isActive }) => (isActive ? 'on' : undefined)}>
            角色广场
          </NavLink>
          {isLoggedIn && (
            <>
              <NavLink to="/profile" className={({ isActive }) => (isActive ? 'on' : undefined)}>
                个人中心
              </NavLink>
              <NavLink to="/llm-key-test" className={({ isActive }) => (isActive ? 'on' : undefined)}>
                Key 测试
              </NavLink>
            </>
          )}
        </nav>

        <div className="topbar__actions">
          {isLoggedIn ? (
            <>
              <Link to="/profile" className="topbar__user" title="个人中心">
                {user?.name || '已登录'}
                <span className="topbar__model">{modelHint}</span>
              </Link>
              <button type="button" className="btn btn--ghost" onClick={logout}>
                退出
              </button>
            </>
          ) : (
            <Link to="/login" className="btn btn--brand">
              登录选模型
            </Link>
          )}
        </div>
      </div>
    </header>
  )
}
