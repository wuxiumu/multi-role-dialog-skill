import { useEffect, useMemo, useState } from 'react'
import { fetchRoleList } from '../../api/roles.js'
import { CATEGORIES } from '../../api/mockRoles.js'
import { RoleCard } from '../../components/RoleCard.jsx'
import './HomePage.css'

export function HomePage() {
  const [roles, setRoles] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [query, setQuery] = useState('')
  const [category, setCategory] = useState('全部')

  useEffect(() => {
    let alive = true
    ;(async () => {
      setLoading(true)
      setError('')
      try {
        const list = await fetchRoleList()
        if (alive) setRoles(list)
      } catch (e) {
        if (alive) setError(e.message || '加载失败')
      } finally {
        if (alive) setLoading(false)
      }
    })()
    return () => {
      alive = false
    }
  }, [])

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    return roles.filter((role) => {
      if (category !== '全部' && role.category !== category) return false
      if (!q) return true
      const hay = [role.name, role.tagline, role.description, ...(role.tags || [])]
        .join(' ')
        .toLowerCase()
      return hay.includes(q)
    })
  }, [roles, query, category])

  return (
    <main className="home">
      <section className="home__hero">
        <p className="home__eyebrow">Role Plaza</p>
        <h1 className="home__brand">角色场</h1>
        <p className="home__lead">挑选一个人设，立刻开聊。游客走关键词兜底，登录解锁大模型。</p>

        <label className="home__search">
          <span className="sr-only">搜索角色</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="2" />
            <path d="M20 20l-3.5-3.5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
          </svg>
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="搜索角色、标签、简介…"
            autoComplete="off"
          />
        </label>
      </section>

      <section className="home__toolbar" aria-label="分类筛选">
        <div className="home__cats">
          {CATEGORIES.map((cat) => (
            <button
              key={cat}
              type="button"
              className={category === cat ? 'on' : undefined}
              onClick={() => setCategory(cat)}
            >
              {cat}
            </button>
          ))}
        </div>
        <p className="home__count">
          {loading ? '加载中…' : `${filtered.length} 个角色`}
        </p>
      </section>

      {error && <p className="home__error">{error}</p>}

      {loading ? (
        <div className="home__grid" aria-busy="true">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="home__skeleton" style={{ animationDelay: `${i * 40}ms` }} />
          ))}
        </div>
      ) : filtered.length === 0 ? (
        <div className="home__empty">
          <h2>没有匹配的角色</h2>
          <p>换个关键词，或切换分类看看。</p>
          <button
            type="button"
            className="btn btn--ghost"
            onClick={() => {
              setQuery('')
              setCategory('全部')
            }}
          >
            清除筛选
          </button>
        </div>
      ) : (
        <div className="home__grid">
          {filtered.map((role, index) => (
            <RoleCard key={role.id || role.slug} role={role} index={index} />
          ))}
        </div>
      )}

      <footer className="home__foot">
        后端 API 就绪后将自动切换真实角色列表；当前展示本地示例（含赢学 MVP）。
      </footer>
    </main>
  )
}
