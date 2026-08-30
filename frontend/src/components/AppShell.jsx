import { Outlet, useLocation } from 'react-router-dom'
import { Topbar } from './Topbar.jsx'
import './AppShell.css'

export function AppShell() {
  const { pathname } = useLocation()
  const isChat = pathname.startsWith('/chat/')

  return (
    <div className={`shell ${isChat ? 'shell--chat' : ''}`}>
      {!isChat && <Topbar />}
      <Outlet />
    </div>
  )
}
