import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { AuthProvider } from '@/context/AuthContext'
import { ToastProvider } from '@/context/ToastContext'
import App from './App.jsx'
import './index.css'

const routerBase = import.meta.env.BASE_URL.replace(/\/$/, '') || '/'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter basename={routerBase}>
      <ToastProvider>
        <AuthProvider>
          <App />
        </AuthProvider>
      </ToastProvider>
    </BrowserRouter>
  </StrictMode>
)

