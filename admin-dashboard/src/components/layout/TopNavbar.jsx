import { useEffect, useMemo, useRef, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import {
  Menu,
  Search,
  Bell,
  ChevronDown,
  PanelLeftClose,
  PanelLeft,
  LogOut,
  User,
  Settings,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Dropdown, DropdownItem, DropdownLabel, DropdownSeparator } from '@/components/ui/dropdown'
import { Badge } from '@/components/ui/badge'
import { pageTitles, pageDescriptions } from '@/config/navigation'
import { cn } from '@/lib/utils'
import { fetchNotificationActivities, fetchTopbarSearchData, getNotificationNavigation, markNotificationActivityRead } from '@/services/notificationsApi'

function shortDate(value) {
  if (!value) return ''
  const date = new Date(String(value).replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(date)
}

function SearchBox({ compact = false, onNavigate }) {
  const wrapperRef = useRef(null)
  const [query, setQuery] = useState('')
  const [items, setItems] = useState([])
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    let active = true
    const load = async () => {
      setLoading(true)
      try {
        const data = await fetchTopbarSearchData()
        if (active) setItems(data)
      } finally {
        if (active) setLoading(false)
      }
    }
    load()
    return () => { active = false }
  }, [])

  useEffect(() => {
    const closeOnOutside = (event) => {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target)) setOpen(false)
    }
    document.addEventListener('mousedown', closeOnOutside)
    return () => document.removeEventListener('mousedown', closeOnOutside)
  }, [])

  const results = useMemo(() => {
    const value = query.trim().toLowerCase()
    if (!value) return []
    return items
      .filter((item) => [item.title, item.subtitle, item.type, item.searchText].join(' ').toLowerCase().includes(value))
      .slice(0, 8)
  }, [items, query])

  const goToResult = (item) => {
    setOpen(false)
    setQuery('')
    onNavigate(item.route, { state: { topbarSearch: query } })
  }

  const submitSearch = (event) => {
    event.preventDefault()
    if (results[0]) goToResult(results[0])
  }

  return (
    <div ref={wrapperRef} className="relative">
      <form onSubmit={submitSearch}>
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
        <Input
          value={query}
          onChange={(event) => { setQuery(event.target.value); setOpen(true) }}
          onFocus={() => setOpen(true)}
          placeholder={compact ? 'Search...' : 'Search bookings, payments, guests, messages...'}
          className={cn('h-9 border-transparent bg-slate-100/60 pl-9', !compact && 'transition-colors focus-visible:border-slate-200 focus-visible:bg-white')}
        />
      </form>

      {open && query.trim() && (
        <div className="absolute left-0 right-0 top-11 z-50 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
          {loading ? (
            <p className="px-4 py-3 text-sm text-slate-500">Loading real data...</p>
          ) : results.length === 0 ? (
            <p className="px-4 py-3 text-sm text-slate-500">No matching booking, payment, guest, or message found.</p>
          ) : (
            <div className="max-h-80 overflow-y-auto py-1">
              {results.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => goToResult(item)}
                  className="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-blue-50/70"
                >
                  <Badge variant="outline" className="mt-0.5 shrink-0">{item.type}</Badge>
                  <span className="min-w-0">
                    <span className="block truncate text-sm font-semibold text-slate-900">{item.title}</span>
                    <span className="block truncate text-xs text-slate-500">{item.subtitle}</span>
                  </span>
                </button>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}

export function TopNavbar({ collapsed, onMenuClick, onToggleCollapse }) {
  const location = useLocation()
  const navigate = useNavigate()
  const pageTitle = pageTitles[location.pathname] || 'Dashboard'
  const pageDescription = pageDescriptions[location.pathname]
  const { user, logout } = useAuth()
  const [notifications, setNotifications] = useState([])

  useEffect(() => {
    let active = true
    let loading = false

    const load = async () => {
      if (loading) return
      loading = true
      try {
        const data = await fetchNotificationActivities()
        if (active) setNotifications(data)
      } catch {
        // Keep the last successful notification state. The top bar must not flicker
        // just because one fast polling request failed.
      } finally {
        loading = false
      }
    }

    const loadWhenVisible = () => {
      if (!document.hidden) load()
    }

    load()

    window.addEventListener('focus', load)
    window.addEventListener('pageshow', load)
    window.addEventListener('notification-read-changed', load)
    document.addEventListener('visibilitychange', loadWhenVisible)

    // Booking notifications must be near-real-time while the admin panel is open.
    // 60 seconds was too slow; 5 seconds keeps the top bar fresh without hammering
    // the server with overlapping requests.
    const timer = window.setInterval(loadWhenVisible, 5000)

    return () => {
      active = false
      window.clearInterval(timer)
      window.removeEventListener('focus', load)
      window.removeEventListener('pageshow', load)
      window.removeEventListener('notification-read-changed', load)
      document.removeEventListener('visibilitychange', loadWhenVisible)
    }
  }, [])

  const unreadNotifications = notifications.filter((n) => n.status === 'New')
  const unreadCount = unreadNotifications.length
  const dropdownNotifications = unreadCount > 0 ? unreadNotifications : notifications.slice(0, 6)

  const currentUser = user || {
    name: 'Hotel Administrator',
    email: 'admin@hotel.com',
    role: 'admin',
  }

  const initials = currentUser.name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  const handleLogout = () => {
    logout()
    navigate('/login', { replace: true })
  }

  const handleNotificationClick = (notif, close) => {
    markNotificationActivityRead(notif.id)
    setNotifications((current) => current.map((item) => (item.id === notif.id ? { ...item, status: 'Viewed' } : item)))

    fetchNotificationActivities()
      .then((data) => setNotifications(data))
      .catch(() => {})

    close()
    const target = getNotificationNavigation(notif)
    navigate(target.pathname, { state: target.state })
  }

  return (
    <header className="sticky top-0 z-30 shrink-0 border-b border-slate-200 bg-white/90 backdrop-blur-md">
      <div className="flex h-16 items-center gap-3 px-4 sm:gap-4 lg:px-8">
        <Button variant="ghost" size="icon" className="shrink-0 lg:hidden" onClick={onMenuClick} aria-label="Open menu">
          <Menu className="h-5 w-5" />
        </Button>

        <Button variant="ghost" size="icon" className="hidden shrink-0 lg:inline-flex" onClick={onToggleCollapse} aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'} title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}>
          {collapsed ? <PanelLeft className="h-5 w-5" /> : <PanelLeftClose className="h-5 w-5" />}
        </Button>

        <div className="min-w-0 flex-1">
          <h1 className="truncate font-sans text-lg font-semibold text-slate-900 sm:text-xl">{pageTitle}</h1>
          {pageDescription && <p className="hidden truncate text-xs text-slate-500 sm:block">{pageDescription}</p>}
        </div>

        <div className="hidden min-w-0 flex-1 md:block md:max-w-sm lg:max-w-md">
          <SearchBox onNavigate={navigate} />
        </div>

        <div className="flex shrink-0 items-center gap-1 sm:gap-2">
          <Dropdown
            align="right"
            contentClassName="w-80 max-w-[calc(100vw-2rem)]"
            trigger={
              <Button variant="ghost" size="icon" className="relative" aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ''}`}>
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                  <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold text-white">{unreadCount}</span>
                )}
              </Button>
            }
          >
            {(close) => (
              <>
                <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                  <p className="text-sm font-semibold text-slate-900">Notifications</p>
                  {unreadCount > 0 && <Badge variant="default">{unreadCount} new</Badge>}
                </div>
                <div className="max-h-72 overflow-y-auto">
                  {dropdownNotifications.length === 0 ? (
                    <p className="px-4 py-5 text-sm text-slate-500">No live notifications found.</p>
                  ) : dropdownNotifications.map((notif) => (
                    <button
                      key={notif.id}
                      type="button"
                      onClick={() => handleNotificationClick(notif, close)}
                      className={cn('flex w-full flex-col gap-0.5 border-b border-slate-200 px-4 py-3 text-left transition-colors last:border-0 hover:bg-slate-100/60', notif.status === 'New' && 'bg-blue-600/5')}
                    >
                      <div className="flex items-center gap-2">
                        <p className="text-sm font-medium text-slate-900">{notif.title}</p>
                        {notif.status === 'New' && <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600" />}
                      </div>
                      <p className="line-clamp-2 text-xs text-slate-500">{notif.description}</p>
                      <p className="text-[11px] text-slate-500/80">{shortDate(notif.created_at)}</p>
                    </button>
                  ))}
                </div>
                <div className="border-t border-slate-200 p-2">
                  <Button variant="ghost" className="w-full justify-center text-sm text-blue-800 hover:bg-blue-50/5 hover:text-blue-800" onClick={() => { close(); navigate('/notifications') }}>
                    View all notifications
                  </Button>
                </div>
              </>
            )}
          </Dropdown>

          <Dropdown
            align="right"
            contentClassName="w-56"
            trigger={
              <button type="button" className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-100/30 px-2 py-1.5 transition-colors hover:border-blue-300/30 hover:bg-slate-100/60 sm:gap-3 sm:px-3" aria-label="Admin profile menu">
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-blue-200">{initials}</div>
                <div className="hidden text-left md:block">
                  <p className="max-w-[120px] truncate text-sm font-medium text-slate-900">{currentUser.name}</p>
                  <p className="text-xs text-slate-500">{currentUser.email}</p>
                </div>
                <ChevronDown className="hidden h-4 w-4 text-slate-500 sm:block" />
              </button>
            }
          >
            {(close) => (
              <>
                <div className="border-b border-slate-200 px-4 py-3">
                  <p className="text-sm font-semibold text-slate-900">{currentUser.name}</p>
                  <p className="truncate text-xs text-slate-500">{currentUser.email}</p>
                  <Badge variant="default" className="mt-2 capitalize">{currentUser.role}</Badge>
                </div>
                <DropdownLabel>Account</DropdownLabel>
                <DropdownItem onClick={() => { close(); navigate('/website-settings') }}><User className="h-4 w-4 text-slate-500" />Profile</DropdownItem>
                <DropdownItem onClick={() => { close(); navigate('/admin-password') }}><Settings className="h-4 w-4 text-slate-500" />Settings</DropdownItem>
                <DropdownSeparator />
                <DropdownItem destructive onClick={() => { close(); handleLogout() }}><LogOut className="h-4 w-4" />Logout</DropdownItem>
              </>
            )}
          </Dropdown>
        </div>
      </div>

      <div className="border-t border-slate-200 px-4 py-2 md:hidden">
        <SearchBox compact onNavigate={navigate} />
      </div>
    </header>
  )
}
