import { cn } from '@/lib/utils'

const badgeVariants = {
  default: 'border-transparent bg-blue-100 text-blue-800',
  secondary: 'border-transparent bg-slate-100 text-slate-700',
  outline: 'border-border text-slate-700',
  success: 'border-transparent bg-emerald-100 text-emerald-800',
  warning: 'border-transparent bg-amber-100 text-amber-800',
  destructive: 'border-transparent bg-red-100 text-red-800',
  info: 'border-transparent bg-blue-100 text-blue-800',
  purple: 'border-transparent bg-purple-100 text-purple-800',
  premium: 'border-transparent bg-gold/15 text-[#8A6A1F]',
}

export function Badge({ className, variant = 'default', ...props }) {
  return (
    <div
      className={cn(
        'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors',
        badgeVariants[variant],
        className
      )}
      {...props}
    />
  )
}