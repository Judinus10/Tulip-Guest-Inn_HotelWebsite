import { useState, useEffect } from 'react'
import { Outlet } from 'react-router-dom'
import { Sidebar } from './Sidebar'
import { TopNavbar } from './TopNavbar'
import { cn } from '@/lib/utils'

const STORAGE_KEY = 'dashboard-sidebar-collapsed'

export function AdminLayout() {
  const [mobileOpen, setMobileOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(() => {
    try {
      return localStorage.getItem(STORAGE_KEY) === 'true'
    } catch {
      return false
    }
  })

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, String(collapsed))
    } catch {
      // ignore storage errors
    }
  }, [collapsed])

  useEffect(() => {
    document.body.style.overflow = mobileOpen ? 'hidden' : ''
    return () => {
      document.body.style.overflow = ''
    }
  }, [mobileOpen])

  return (
    <div className="min-h-screen bg-slate-50">
      <Sidebar
        mobileOpen={mobileOpen}
        collapsed={collapsed}
        onCloseMobile={() => setMobileOpen(false)}
        onToggleCollapse={() => setCollapsed((prev) => !prev)}
      />

      <div
        className={cn(
          'flex min-h-screen min-w-0 flex-col transition-[margin-left] duration-300 ease-in-out',
          collapsed ? 'lg:ml-[72px]' : 'lg:ml-72'
        )}
      >
        <TopNavbar
          collapsed={collapsed}
          onMenuClick={() => setMobileOpen(true)}
          onToggleCollapse={() => setCollapsed((prev) => !prev)}
        />

        <main className="flex-1 overflow-x-hidden px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
          <div className="mx-auto w-full max-w-[1600px]">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
