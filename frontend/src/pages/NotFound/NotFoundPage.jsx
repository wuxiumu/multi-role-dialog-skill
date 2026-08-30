import { Link } from 'react-router-dom'
import { Seo } from '../../seo/Seo.jsx'
import './NotFoundPage.css'

export function NotFoundPage() {
  return (
    <main className="notfound">
      <Seo title="页面不存在" path="/404" noIndex />
      <p className="notfound__code">404</p>
      <h1>页面不存在</h1>
      <p>链接可能写错了，回角色广场重新挑一个吧。</p>
      <Link to="/" className="btn btn--brand">
        回角色广场
      </Link>
    </main>
  )
}
