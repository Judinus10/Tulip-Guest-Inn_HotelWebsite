import { NavLink } from 'react-router-dom'
import {
  X,
  PanelLeftClose,
  PanelLeft,
  Headphones,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { navigation } from '@/config/navigation'
import { Button } from '@/components/ui/button'

function NavItem({ item, collapsed, onNavigate }) {
  return (
    <li>
      <NavLink
        to={item.href}
        end={item.href === '/'}
        onClick={onNavigate}
        title={collapsed ? item.name : undefined}
        className={({ isActive }) =>
          cn(
            'group relative flex items-center rounded-xl text-sm font-medium transition-all duration-200',
            collapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5',
            isActive
              ? 'bg-blue-500/15 text-white shadow-sm ring-1 ring-blue-400/20'
              : 'text-slate-300 hover:bg-white/8 hover:text-white'
          )
        }
      >
        {({ isActive }) => (
          <>
            {isActive && (
              <span
                className={cn(
                  'absolute rounded-full bg-accent transition-all duration-200',
                  collapsed
                    ? 'bottom-1 left-1/2 h-0.5 w-4 -translate-x-1/2'
                    : 'left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full'
                )}
              />
            )}

            <item.icon
              className={cn(
                'h-[18px] w-[18px] shrink-0 transition-colors duration-200',
                isActive ? 'text-white' : 'text-slate-300 group-hover:text-white'
              )}
            />

            {!collapsed && (
              <>
                <span className="truncate">{item.name}</span>
                {isActive && (
                  <span className="ml-auto h-1.5 w-1.5 shrink-0 rounded-full bg-accent" />
                )}
              </>
            )}
          </>
        )}
      </NavLink>
    </li>
  )
}

export function Sidebar({
  mobileOpen,
  collapsed,
  onCloseMobile,
  onToggleCollapse,
}) {
  return (
    <>
      <div
        className={cn(
          'fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-[2px] transition-opacity duration-300 lg:hidden',
          mobileOpen ? 'opacity-100' : 'pointer-events-none opacity-0'
        )}
        onClick={onCloseMobile}
        aria-hidden="true"
      />

      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex flex-col border-r border-white/10 bg-[#0F172A] shadow-2xl shadow-slate-950/10 transition-all duration-300 ease-in-out',
          collapsed ? 'w-[72px]' : 'w-72',
          mobileOpen ? 'translate-x-0' : '-translate-x-full',
          'lg:translate-x-0'
        )}
        aria-label="Main navigation"
      >
        <div
          className={cn(
            'flex h-16 shrink-0 items-center border-b border-white/10 transition-all duration-300',
            collapsed ? 'justify-center px-2' : 'justify-between px-5'
          )}
        >
          <div className={cn('flex items-center gap-3', collapsed && 'justify-center')}>
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 shadow-sm shadow-blue-950/30 ring-1 ring-white/10">
              <span className="text-sm font-bold text-white">JH</span>
            </div>

            {!collapsed && (
              <div className="min-w-0">
                <p className="truncate text-base font-semibold text-white">
                  Jebal Guest House
                </p>
                <p className="text-[10px] font-medium uppercase tracking-[0.2em] text-blue-200">
                  Guest House PMS
                </p>
              </div>
            )}
          </div>

          <button
            type="button"
            onClick={onCloseMobile}
            className="rounded-lg p-1.5 text-slate-300 transition-colors hover:bg-white/10 hover:text-white lg:hidden"
            aria-label="Close sidebar"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <nav className="sidebar-scroll flex-1 overflow-y-auto overflow-x-hidden px-3 py-5">
          {navigation.map((section, index) => (
            <div
              key={section.label ?? `section-${index}`}
              className={cn(index > 0 && 'mt-6 border-t border-white/10 pt-6')}
            >
              {section.label && !collapsed && (
                <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                  {section.label}
                </p>
              )}

              {section.label && collapsed && (
                <div className="mb-2 flex justify-center">
                  <span className="h-px w-6 bg-white/15" />
                </div>
              )}

              <ul className="space-y-1">
                {section.items.map((item) => (
                  <NavItem
                    key={item.href}
                    item={item}
                    collapsed={collapsed}
                    onNavigate={onCloseMobile}
                  />
                ))}
              </ul>
            </div>
          ))}
        </nav>

        <div className={cn('shrink-0 border-t border-white/10 p-3', collapsed && 'px-2')}>
          {!collapsed && (
            <div className="mb-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
              <div className="flex items-start gap-2.5">
                <Headphones className="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                <div>
                  <p className="text-xs font-medium text-white">
                    Need assistance?
                  </p>
                  <p className="mt-0.5 text-xs text-slate-300">
                    support.compylx@gmail.com
                  </p>
                </div>
              </div>
            </div>
          )}

          <Button
            variant="ghost"
            size={collapsed ? 'icon' : 'default'}
            onClick={onToggleCollapse}
            className={cn(
              'hidden w-full text-slate-300 hover:bg-white/10 hover:text-white lg:inline-flex',
              collapsed && 'h-10 w-10'
            )}
            title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          >
            {collapsed ? (
              <PanelLeft className="h-4 w-4" />
            ) : (
              <>
                <PanelLeftClose className="h-4 w-4" />
                Collapse
              </>
            )}
          </Button>
        </div>
      </aside>
    </>
  )
}