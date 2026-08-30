import { useCallback, useEffect, useRef, useState } from 'react'
import './Toast.css'

let pushToastExternal = null

export function toast(message, type = 'info') {
  pushToastExternal?.({ message, type, id: Date.now() + Math.random() })
}

export function ToastHost() {
  const [items, setItems] = useState([])

  useEffect(() => {
    pushToastExternal = (item) => {
      setItems((prev) => [...prev.slice(-2), item])
      setTimeout(() => {
        setItems((prev) => prev.filter((x) => x.id !== item.id))
      }, 2600)
    }
    return () => {
      pushToastExternal = null
    }
  }, [])

  return (
    <div className="toast-host" aria-live="polite">
      {items.map((item) => (
        <div key={item.id} className={`toast toast--${item.type}`}>
          {item.message}
        </div>
      ))}
    </div>
  )
}

export function useToastReady() {
  return useCallback((msg, type) => toast(msg, type), [])
}
