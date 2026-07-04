import { cn } from '@/lib/utils'

export function PageHeader({ title, description, children }) {
  return (
    <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 className="text-2xl font-bold text-text-primary md:text-3xl">{title}</h1>
        {description && <p className="mt-1 text-sm text-text-secondary">{description}</p>}
      </div>
      {children && <div className="flex flex-wrap items-center gap-2">{children}</div>}
    </div>
  )
}

export function PlaceholderTable({ columns, rows }) {
  return (
    <div className="-mx-1 overflow-x-auto rounded-2xl border border-border bg-white px-1 shadow-sm shadow-slate-200/60 sm:mx-0 sm:px-0">
      <table className="w-full min-w-[640px] text-sm">
        <thead className="sticky top-0 z-10">
          <tr className="border-b border-border bg-slate-50">
            {columns.map((col) => (
              <th
                key={col}
                className="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-text-secondary"
              >
                {col}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {rows.map((row, i) => (
            <tr key={i} className="transition-colors hover:bg-blue-50/40">
              {row.map((cell, j) => (
                <td key={j} className="whitespace-nowrap px-4 py-4 text-text-primary">
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export function StatCard({ label, value, change, icon: Icon }) {
  return (
    <div className="rounded-2xl border border-border bg-white p-5 shadow-sm shadow-slate-200/60 transition-shadow hover:shadow-md">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-medium text-text-secondary">{label}</p>
          <p className="mt-2 text-2xl font-bold text-text-primary">{value}</p>
          {change && <p className="mt-1 text-xs font-medium text-emerald-600">{change}</p>}
        </div>
        {Icon && (
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-primary-600">
            <Icon className="h-5 w-5" />
          </div>
        )}
      </div>
    </div>
  )
}

export function EmptyState({ icon: Icon, title, description }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-white py-16 text-center shadow-sm shadow-slate-200/60">
      {Icon && (
        <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-primary-600">
          <Icon className="h-7 w-7" />
        </div>
      )}
      <h3 className="text-lg font-semibold text-text-primary">{title}</h3>
      <p className="mt-2 max-w-sm text-sm text-text-secondary">{description}</p>
    </div>
  )
}

export function SectionCard({ title, description, children, className }) {
  return (
    <div className={cn('rounded-2xl border border-border bg-white p-6 shadow-sm shadow-slate-200/60', className)}>
      {(title || description) && (
        <div className="mb-4">
          {title && <h2 className="text-lg font-semibold text-text-primary">{title}</h2>}
          {description && <p className="mt-1 text-sm text-text-secondary">{description}</p>}
        </div>
      )}
      {children}
    </div>
  )
}
