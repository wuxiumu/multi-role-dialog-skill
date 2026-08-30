import { createContext, useContext, useMemo, useState, useCallback } from 'react'

const AuthContext = createContext(null)

const TOKEN_KEY = 'rolecast_token'
const USER_KEY = 'rolecast_user'
const PROVIDER_KEY = 'rolecast_llm_provider'

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY) || '')
  const [user, setUser] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem(USER_KEY) || 'null')
    } catch {
      return null
    }
  })
  const [llmProvider, setLlmProviderState] = useState(
    () => localStorage.getItem(PROVIDER_KEY) || 'none',
  )

  const login = useCallback((nextToken, nextUser) => {
    setToken(nextToken)
    setUser(nextUser)
    localStorage.setItem(TOKEN_KEY, nextToken)
    localStorage.setItem(USER_KEY, JSON.stringify(nextUser))
  }, [])

  const logout = useCallback(() => {
    setToken('')
    setUser(null)
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  }, [])

  const setLlmProvider = useCallback((id) => {
    const next = id || 'none'
    setLlmProviderState(next)
    localStorage.setItem(PROVIDER_KEY, next)
  }, [])

  const value = useMemo(
    () => ({
      token,
      user,
      isLoggedIn: Boolean(token),
      llmProvider,
      setLlmProvider,
      login,
      logout,
    }),
    [token, user, llmProvider, setLlmProvider, login, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
