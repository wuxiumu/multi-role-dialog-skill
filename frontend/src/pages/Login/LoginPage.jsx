import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../store/AuthContext.jsx'
import { request } from '../../api/client.js'
import { toast } from '../../components/Toast.jsx'
import { Seo } from '../../seo/Seo.jsx'
import './LoginPage.css'

export function LoginPage() {
  const { isLoggedIn, login, logout } = useAuth()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)

  async function onSubmit(e) {
    e.preventDefault()
    if (!name.trim() || !password.trim()) {
      toast('请填写账号和密码', 'error')
      return
    }
    setLoading(true)
    try {
      const data = await request('/auth.php', {
        method: 'POST',
        body: JSON.stringify({ name: name.trim(), password }),
      })
      if (!data?.token) throw new Error('登录失败：无 Token')
      login(data.token, data.user || { name: name.trim() })
      toast('登录成功，可在个人中心选择模型', 'ok')
      navigate('/profile')
    } catch (err) {
      toast(err.message || '登录失败', 'error')
    } finally {
      setLoading(false)
    }
  }

  if (isLoggedIn) {
    return (
      <main className="login">
        <Seo title="已登录" path="/login" noIndex />
        <div className="login__card">
          <h1>你已登录</h1>
          <p>可前往个人中心切换模型，或回广场开始对话。</p>
          <div className="login__actions">
            <Link to="/profile" className="btn btn--brand">
              个人中心
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

  return (
    <main className="login">
      <Seo
        title="登录"
        description="登录角色场，解锁 DeepSeek / 通义千问等大模型对话；也可关闭模型仅用离线匹配。"
        path="/login"
      />
      <form className="login__card" onSubmit={onSubmit}>
        <p className="login__eyebrow">Unlock AI</p>
        <h1>登录</h1>
        <p className="login__lead">
          登录后可在个人中心选择 DeepSeek / 千问，或关闭模型只用离线语录。演示站任意非空账号密码即可。
        </p>

        <label className="login__field">
          <span>账号</span>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="随便填一个昵称"
            autoComplete="username"
          />
        </label>
        <label className="login__field">
          <span>密码</span>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="任意非空即可"
            autoComplete="current-password"
          />
        </label>

        <button type="submit" className="btn btn--brand login__submit" disabled={loading}>
          {loading ? '登录中…' : '登录'}
        </button>
        <Link to="/" className="login__back">
          ← 先逛角色广场
        </Link>
      </form>
    </main>
  )
}
