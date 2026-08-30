import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './store/AuthContext.jsx'
import { AppShell } from './components/AppShell.jsx'
import { HomePage } from './pages/Home/HomePage.jsx'
import { ChatPage } from './pages/Chat/ChatPage.jsx'
import { LoginPage } from './pages/Login/LoginPage.jsx'
import { ProfilePage } from './pages/Profile/ProfilePage.jsx'
import { LlmKeyTestPage } from './pages/LlmKeyTest/LlmKeyTestPage.jsx'
import { NotFoundPage } from './pages/NotFound/NotFoundPage.jsx'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<AppShell />}>
            <Route index element={<HomePage />} />
            <Route path="chat/:roleSlug" element={<ChatPage />} />
            <Route path="login" element={<LoginPage />} />
            <Route path="profile" element={<ProfilePage />} />
            <Route path="llm-key-test" element={<LlmKeyTestPage />} />
            <Route path="404" element={<NotFoundPage />} />
            <Route path="*" element={<Navigate to="/404" replace />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
