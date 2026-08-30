import { useEffect, useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { useAuth } from '../../store/AuthContext.jsx'
import { fetchLlmProviders } from '../../api/llm.js'
import { toast } from '../../components/Toast.jsx'
import { Seo } from '../../seo/Seo.jsx'
import './ProfilePage.css'

export function ProfilePage() {
  const { isLoggedIn, user, llmProvider, setLlmProvider, logout } = useAuth()
  const [providers, setProviders] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let alive = true
    ;(async () => {
      setLoading(true)
      const data = await fetchLlmProviders()
      if (!alive) return
      setProviders(data.providers || [])
      setLoading(false)
    })()
    return () => {
      alive = false
    }
  }, [])

  if (!isLoggedIn) {
    return <Navigate to="/login" replace />
  }

  function pick(id, ready) {
    if (id !== 'none' && ready === false) {
      toast('该模型尚未配置密钥，暂不可用', 'error')
      return
    }
    setLlmProvider(id)
    toast(id === 'none' ? '已切换为离线匹配' : `已切换模型：${id}`, 'ok')
  }

  return (
    <main className="profile">
      <Seo title="个人中心" path="/profile" noIndex />
      <div className="profile__card">
        <p className="profile__eyebrow">Account</p>
        <h1>个人中心</h1>
        <p className="profile__lead">
          你好，<strong>{user?.name || '用户'}</strong>。在这里选择对话使用的大模型；也可关闭模型，仅用语录 / 关键词。
        </p>

        <section className="profile__section">
          <h2>对话模型</h2>
          {loading ? (
            <p className="profile__muted">加载可选模型…</p>
          ) : (
            <div className="profile__providers" role="radiogroup" aria-label="选择模型">
              {providers.map((p) => {
                const active = llmProvider === p.id
                const disabled = p.id !== 'none' && p.ready === false
                return (
                  <button
                    key={p.id}
                    type="button"
                    role="radio"
                    aria-checked={active}
                    disabled={disabled}
                    className={`profile__provider${active ? ' on' : ''}${disabled ? ' off' : ''}`}
                    onClick={() => pick(p.id, p.ready)}
                  >
                    <span className="profile__provider-name">
                      {p.name}
                      {active ? <em>当前</em> : null}
                    </span>
                    <span className="profile__provider-desc">{p.desc || p.model || ''}</span>
                  </button>
                )
              })}
            </div>
          )}
        </section>

        <div className="profile__actions">
          <Link to="/llm-key-test" className="btn btn--brand">
            测试模型 Key
          </Link>
          <Link to="/" className="btn btn--ghost">
            回广场
          </Link>
          <button type="button" className="btn btn--ghost" onClick={logout}>
            退出登录
          </button>
        </div>
      </div>
    </main>
  )
}
