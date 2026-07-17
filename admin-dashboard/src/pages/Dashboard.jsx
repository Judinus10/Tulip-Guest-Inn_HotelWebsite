import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  BedDouble,
  CalendarCheck,
  CreditCard,
  DollarSign,
  Image,
  Mail,
  MessageSquareText,
  Tag,
  TrendingUp,
  Home,
  Layers,
  Building2,
  Download,
} from 'lucide-react'
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { PageHeader } from '@/components/ui/page-header'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Dropdown, DropdownItem } from '@/components/ui/dropdown'
import { fetchDashboardStats } from '@/services/dashboardApi'
import { exportCsv, exportExcel, exportPdf } from '@/utils/exportData'

const TABLE_LIMIT = 5

const defaultDashboardData = {
  cards: {
    totalBookings: 0,
    websiteBookings: 0,
    bookingComBookings: 0,
    pendingBookings: 0,
    confirmedBookings: 0,
    cancelledBookings: 0,
    totalEnquiries: 0,
    totalRevenue: 0,
    paidBookings: 0,
    paymentPendingBookings: 0,
  },
  revenue: {
    today: 0,
    currentMonth: 0,
    currentYear: 0,
    lifetime: 0,
  },
  rooms: {
    totalRooms: 6,
    availableRooms: 6,
    groundFloorRooms: 2,
    firstFloorRooms: 2,
    cottageUnits: 1,
    mostBookedRoom: 'No confirmed bookings yet',
    occupancyRate: 0,
  },
  charts: {
    monthlyBookingTrend: [],
    revenueTrend: [],
    bookingStatusDistribution: [],
    paymentStatusDistribution: [],
  },
  enquiries: {
    new: 0,
    read: 0,
    replied: 0,
  },
  payments: {
    paid: 0,
    failed: 0,
    refunded: 0,
    pending: 0,
  },
  lists: {
    recentBookings: [],
    upcomingCheckIns: [],
    recentPayments: [],
    latestMessages: [],
  },
}

const chartColors = ['#2563EB', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6']

const bookingStatusColors = {
  Pending: '#F59E0B',
  Confirmed: '#10B981',
  'Checked In': '#3B82F6',
  'Checked Out': '#64748B',
  Cancelled: '#EF4444',
  'No Show': '#A855F7',
  'Booking.com': '#6366F1',
}

const paymentStatusColors = {
  Paid: '#10B981',
  'Payment Pending': '#F59E0B',
  'No Pay': '#0EA5E9',
  Cancelled: '#EF4444',
  Refunded: '#8B5CF6',
  Failed: '#6B7280',
}

function getStatusColor(status, type) {
  if (type === 'booking') return bookingStatusColors[status] || '#94A3B8'
  if (type === 'payment') return paymentStatusColors[status] || '#94A3B8'
  return '#94A3B8'
}

const statusVariant = {
  Available: 'success',
  Occupied: 'secondary',
  Pending: 'warning',
  Booked: 'success',
  Confirmed: 'success',
  'Checked In': 'default',
  'Checked Out': 'secondary',
  'No Show': 'purple',
  Cancelled: 'destructive',
  Completed: 'default',
  Paid: 'success',
  Failed: 'destructive',
  Refunded: 'secondary',
  'Payment Pending': 'warning',
  New: 'warning',
  Read: 'secondary',
  Replied: 'success',
  Active: 'success',
  Scheduled: 'warning',
}

function displayStatus(value, fallback = 'Pending') {
  const text = String(value || '').trim()
  return text || fallback
}

function DashboardStatusBadge({ status, fallback = 'Pending' }) {
  const label = displayStatus(status, fallback)
  return (
    <Badge className="min-w-[76px] justify-center whitespace-nowrap px-3" variant={statusVariant[label] || 'secondary'}>
      {label}
    </Badge>
  )
}

const currencyFormatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'LKR',
  maximumFractionDigits: 0,
})

const shortDateFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
})

function formatDate(date) {
  if (!date) return 'Not set'
  const parsedDate = new Date(date)
  if (Number.isNaN(parsedDate.getTime())) return 'Not set'
  return shortDateFormatter.format(parsedDate)
}

function getPercentage(value, total) {
  if (!total) return 0
  return Math.round((Number(value || 0) / Number(total || 0)) * 100)
}

function normalizeDistribution(items = []) {
  const total = items.reduce((sum, item) => sum + Number(item.value || 0), 0)

  return items.map((item) => ({
    ...item,
    percentage: item.percentage ?? getPercentage(item.value, total),
  }))
}


function fillLastSixMonths(data = [], valueKey) {
  const now = new Date()

  const months = []

  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)

    months.push({
      month: d.toLocaleString('en-US', { month: 'short' }),
      [valueKey]: 0,
      websiteBookings: 0,
      bookingComBookings: 0,
    })
  }

  data.forEach((item) => {
    const existing = months.find((m) => m.month === item.month)

    if (existing) {
      existing[valueKey] = Number(item[valueKey] || 0)
      existing.websiteBookings = Number(item.websiteBookings || 0)
      existing.bookingComBookings = Number(item.bookingComBookings || 0)
    }
  })

  return months
}


function buildDashboardReportRows(data, monthlyBookingTrend, revenueTrend) {
  const rows = [
    { Section: 'Bookings', Metric: 'Total Bookings', Value: data.cards.totalBookings },
    { Section: 'Bookings', Metric: 'Booking.com Bookings', Value: data.cards.bookingComBookings },
    { Section: 'Bookings', Metric: 'Website Bookings', Value: data.cards.websiteBookings },
    { Section: 'Bookings', Metric: 'Pending Bookings', Value: data.cards.pendingBookings },
    { Section: 'Bookings', Metric: 'Confirmed Bookings', Value: data.cards.confirmedBookings },
    { Section: 'Bookings', Metric: 'Cancelled Bookings', Value: data.cards.cancelledBookings },
    { Section: 'Payments', Metric: 'Total Revenue', Value: currencyFormatter.format(data.cards.totalRevenue) },
    { Section: 'Payments', Metric: 'Paid Bookings', Value: data.cards.paidBookings },
    { Section: 'Payments', Metric: 'Payment Pending', Value: data.cards.paymentPendingBookings },
    { Section: 'Messages', Metric: 'Total Enquiries', Value: data.cards.totalEnquiries },
    { Section: 'Rooms', Metric: 'Total Rooms', Value: data.rooms.totalRooms },
    { Section: 'Rooms', Metric: 'Available Rooms', Value: data.rooms.availableRooms },
    { Section: 'Rooms', Metric: 'Occupancy Rate', Value: `${data.rooms.occupancyRate}%` },
  ]

  monthlyBookingTrend.forEach((item) => {
    rows.push({ Section: 'Monthly Booking Trend', Metric: item.month, Value: item.bookings })
  })

  revenueTrend.forEach((item) => {
    rows.push({ Section: 'Revenue Trend', Metric: item.month, Value: currencyFormatter.format(item.revenue) })
  })

  return rows
}

function ClickableCard({ to, children, className = '' }) {
  return (
    <Link to={to} className={`block h-full ${className}`}>
      {children}
    </Link>
  )
}

function KpiCard({ title, value, helper, icon: Icon, to }) {
  return (
    <ClickableCard to={to}>
      <Card className="h-full cursor-pointer transition-all hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
        <CardContent className="p-5">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <p className="text-sm font-medium text-muted">{title}</p>
              <p className="mt-2 break-words text-2xl font-semibold text-charcoal">{value}</p>
              {helper && <p className="mt-1 text-xs text-muted">{helper}</p>}
            </div>
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
              <Icon className="h-5 w-5" />
            </div>
          </div>
        </CardContent>
      </Card>
    </ClickableCard>
  )
}

function MiniSummaryCard({ title, value, helper, icon: Icon, to }) {
  return (
    <ClickableCard to={to}>
      <Card className="h-full min-h-[112px] cursor-pointer transition-all hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
        <CardContent className="p-4">
          <div className="flex h-full items-start gap-3">
            <div className="rounded-xl bg-blue-50 p-3 text-blue-700">
              <Icon className="h-5 w-5" />
            </div>
            <div className="min-w-0">
              <p className="text-sm font-medium leading-snug text-charcoal">{title}</p>
              <p className="mt-1 break-words text-xl font-semibold leading-tight text-charcoal">{value}</p>
              {helper && <p className="mt-1 line-clamp-2 text-xs leading-snug text-muted">{helper}</p>}
            </div>
          </div>
        </CardContent>
      </Card>
    </ClickableCard>
  )
}

function ChartLegend({ data, type }) {
  return (
    <div className="mt-4 grid gap-2 sm:grid-cols-2">
      {data.map((item, index) => (
        <div key={item.name} className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
          <div className="flex min-w-0 items-center gap-2">
            <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: getStatusColor(item.name, type) }} />
            <span className="truncate text-charcoal">{item.name}</span>
          </div>
          <span className="font-semibold text-blue-700">{item.percentage}%</span>
        </div>
      ))}
    </div>
  )
}

function StatusDonut({ title, data, to }) {
  const chartData = normalizeDistribution(data)

  return (
    <ClickableCard to={to}>
      <Card className="h-full cursor-pointer transition-all hover:border-blue-200 hover:shadow-md">
        <CardHeader>
          <CardTitle>{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={210}>
            <PieChart>
              <Pie data={chartData} dataKey="value" nameKey="name" innerRadius={55} outerRadius={82} paddingAngle={3}>
                {chartData.map((entry, index) => (
                  <Cell key={entry.name} fill={getStatusColor(entry.name, title === 'Booking Status' ? 'booking' : 'payment')} />
                ))}
              </Pie>
              <Tooltip
                contentStyle={{ borderRadius: '12px', border: '1px solid #e5e7eb', fontSize: '13px' }}
                formatter={(value, name, item) => [`${item.payload.percentage}% (${value})`, name]}
              />
            </PieChart>
          </ResponsiveContainer>
          <ChartLegend data={chartData} type={title === 'Booking Status' ? 'booking' : 'payment'} />
        </CardContent>
      </Card>
    </ClickableCard>
  )
}

function CompactList({ title, description, items, emptyText, renderItem, to }) {
  const visibleItems = items.slice(0, TABLE_LIMIT)

  return (
    <Card className="h-full transition-all hover:border-blue-200 hover:shadow-md">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-4">
          <div>
            <CardTitle>{title}</CardTitle>
            {description && <p className="mt-1 text-sm text-muted">{description}</p>}
          </div>
          <Link to={to} className="shrink-0 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100">
            View all
          </Link>
        </div>
      </CardHeader>
      <CardContent>
        {visibleItems.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border bg-slate-50 p-6 text-center text-sm text-muted">
            {emptyText}
          </div>
        ) : (
          <div className="space-y-2">{visibleItems.map(renderItem)}</div>
        )}
      </CardContent>
    </Card>
  )
}

function ListRow({ title, subtitle, right, badge, to }) {
  const content = (
    <div className="flex items-start justify-between gap-4">
      <div className="min-w-0">
        <p className="line-clamp-1 font-semibold text-charcoal">{title}</p>
        {subtitle && <p className="mt-1 line-clamp-1 text-sm text-muted">{subtitle}</p>}
      </div>
      <div className="flex min-w-[88px] shrink-0 items-center justify-end gap-2 text-right">
        {badge}
        {right && <span className="font-semibold text-charcoal">{right}</span>}
      </div>
    </div>
  )

  if (to) {
    return (
      <Link to={to} className="block rounded-xl border border-border px-4 py-3 transition-colors hover:bg-blue-50/40">
        {content}
      </Link>
    )
  }

  return (
    <div className="rounded-xl border border-border px-4 py-3 transition-colors hover:bg-blue-50/40">
      {content}
    </div>
  )
}


function PaymentListRow({ payment }) {
  return (
    <Link
      to={`/payments?focus=${encodeURIComponent(payment.transaction)}`}
      className="block rounded-xl border border-border px-4 py-3 transition-colors hover:bg-blue-50/40"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <p className="break-all text-sm font-semibold leading-snug text-charcoal sm:text-base">
            {payment.transaction}
          </p>
          <p className="mt-1 line-clamp-1 text-sm text-muted">
            {payment.guest} • {formatDate(payment.createdAt)}
          </p>
        </div>
        <div className="flex shrink-0 flex-col items-end gap-2 text-right sm:flex-row sm:items-center">
          <DashboardStatusBadge status={payment.status} fallback="Payment Pending" />
          <span className="whitespace-nowrap font-semibold text-charcoal">
            {currencyFormatter.format(payment.amount)}
          </span>
        </div>
      </div>
    </Link>
  )
}

export default function Dashboard() {
  const [dashboardData, setDashboardData] = useState(defaultDashboardData)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let isMounted = true

    async function loadDashboardStats() {
      try {
        setIsLoading(true)
        setError('')
        const data = await fetchDashboardStats()

        if (isMounted) {
          setDashboardData({
            ...defaultDashboardData,
            ...data,
            cards: { ...defaultDashboardData.cards, ...(data?.cards || {}) },
            revenue: { ...defaultDashboardData.revenue, ...(data?.revenue || {}) },
            rooms: { ...defaultDashboardData.rooms, ...(data?.rooms || {}) },
            charts: { ...defaultDashboardData.charts, ...(data?.charts || {}) },
            enquiries: { ...defaultDashboardData.enquiries, ...(data?.enquiries || {}) },
            payments: { ...defaultDashboardData.payments, ...(data?.payments || {}) },
            lists: { ...defaultDashboardData.lists, ...(data?.lists || {}) },
          })
        }
      } catch (loadError) {
        if (isMounted) {
          setError(loadError.message || 'Unable to load dashboard statistics.')
          setDashboardData(defaultDashboardData)
        }
      } finally {
        if (isMounted) {
          setIsLoading(false)
        }
      }
    }

    loadDashboardStats()

    return () => {
      isMounted = false
    }
  }, [])

  const bookingStatusData = useMemo(
    () => normalizeDistribution(dashboardData.charts.bookingStatusDistribution),
    [dashboardData.charts.bookingStatusDistribution]
  )

  const paymentStatusData = useMemo(
    () => normalizeDistribution(dashboardData.charts.paymentStatusDistribution),
    [dashboardData.charts.paymentStatusDistribution]
  )

  const monthlyBookingTrend = useMemo(
    () =>
      fillLastSixMonths(
        dashboardData.charts.monthlyBookingTrend,
        'bookings'
      ),
    [dashboardData.charts.monthlyBookingTrend]
  )

  const revenueTrend = useMemo(
    () =>
      fillLastSixMonths(
        dashboardData.charts.revenueTrend,
        'revenue'
      ),
    [dashboardData.charts.revenueTrend]
  )

  const kpis = [
    { title: 'Total Bookings', value: dashboardData.cards.totalBookings, helper: 'All booking requests', icon: CalendarCheck, to: '/bookings' },
    { title: 'Booking.com Bookings', value: dashboardData.cards.bookingComBookings, helper: 'Includes pending imports', icon: TrendingUp, to: '/bookings' },
    { title: 'Website Bookings', value: dashboardData.cards.websiteBookings, helper: 'Direct website bookings', icon: CalendarCheck, to: '/bookings' },
    { title: 'Cancelled Bookings', value: dashboardData.cards.cancelledBookings, helper: 'Cancelled requests', icon: BedDouble, to: '/bookings' },
    { title: 'Total Enquiries', value: dashboardData.cards.totalEnquiries, helper: 'Guest messages', icon: Mail, to: '/messages' },
    { title: 'Total Revenue', value: currencyFormatter.format(dashboardData.cards.totalRevenue), helper: 'Paid bookings only', icon: DollarSign, to: '/payments' },
    { title: 'Paid Bookings', value: dashboardData.cards.paidBookings, helper: 'Payment completed', icon: CreditCard, to: '/payments' },
    { title: 'Payment Pending', value: dashboardData.cards.paymentPendingBookings, helper: 'Awaiting payment', icon: CreditCard, to: '/payments' },
  ]

  const quickActions = [
    { label: 'Add Room', to: '/rooms', icon: BedDouble },
    { label: 'View Bookings', to: '/bookings', icon: CalendarCheck },
    { label: 'Open Calendar', to: '/booking-calendar', icon: CalendarCheck },
    { label: 'Manage Gallery', to: '/gallery', icon: Image },
    { label: 'Website Settings', to: '/website-settings', icon: Tag },
  ]

  const handleDownloadReport = (format) => {
    const payload = {
      fileName: 'tulip-guest-inn-dashboard-report',
      title: 'Tulip Guest Inn Dashboard Report',
      rows: buildDashboardReportRows(dashboardData, monthlyBookingTrend, revenueTrend),
    }

    if (format === 'csv') exportCsv(payload)
    if (format === 'excel') exportExcel(payload)
    if (format === 'pdf') exportPdf(payload)
  }

  return (
    <div className="space-y-8">
      <PageHeader
        title="Dashboard"
        description="Tulip Guest Inn overview for rooms, reservations, payments, messages, packages, and website content."
      >
        <Dropdown
          trigger={
            <Button type="button" variant="outline">
              <Download className="h-4 w-4" />
              Download
            </Button>
          }
        >
          {(close) => (
            <>
              <DropdownItem onClick={() => { close(); handleDownloadReport('excel') }}>Excel</DropdownItem>
              <DropdownItem onClick={() => { close(); handleDownloadReport('csv') }}>CSV</DropdownItem>
              <DropdownItem onClick={() => { close(); handleDownloadReport('pdf') }}>PDF</DropdownItem>
            </>
          )}
        </Dropdown>
      </PageHeader>

      {error ? (
        <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {error}
        </div>
      ) : null}

      {isLoading ? (
        <div className="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700">
          Loading real dashboard statistics...
        </div>
      ) : null}

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {kpis.map((kpi) => (
          <KpiCard key={kpi.title} {...kpi} />
        ))}
      </section>

      <section className="grid gap-4 md:grid-cols-3">
        <MiniSummaryCard title="Today Revenue" value={currencyFormatter.format(dashboardData.revenue.today)} helper="Paid today" icon={DollarSign} to="/payments" />
        <MiniSummaryCard title="Month Revenue" value={currencyFormatter.format(dashboardData.revenue.currentMonth)} helper="Paid this month" icon={TrendingUp} to="/payments" />
        <MiniSummaryCard title="Year Revenue" value={currencyFormatter.format(dashboardData.revenue.currentYear)} helper="Paid this year" icon={CreditCard} to="/payments" />

        <MiniSummaryCard title="Ground Floor Rooms" value={dashboardData.rooms.groundFloorRooms} helper="Easy access rooms" icon={Home} to="/rooms" />
        <MiniSummaryCard title="First Floor Rooms" value={dashboardData.rooms.firstFloorRooms} helper="Upper floor rooms" icon={Building2} to="/rooms" />

        <Card className="h-full min-h-[360px] md:row-span-3">
          <CardHeader className="pb-3">
            <CardTitle>Quick Actions</CardTitle>
            <p className="text-sm text-muted">Daily admin shortcuts.</p>
          </CardHeader>
          <CardContent className="space-y-3">
            {quickActions.map((action) => {
              const Icon = action.icon
              return (
                <Link
                  key={action.label}
                  to={action.to}
                  className="inline-flex h-10 w-full items-center justify-start gap-2 rounded-lg border border-border bg-white px-4 py-2 text-sm font-medium text-charcoal shadow-sm transition-colors hover:border-blue-200 hover:bg-blue-50/60"
                >
                  <Icon className="h-4 w-4 text-blue-700" />
                  {action.label}
                </Link>
              )
            })}
          </CardContent>
        </Card>

        <MiniSummaryCard title="Cottage Units" value={dashboardData.rooms.cottageUnits} helper="Separate cottage units" icon={Layers} to="/rooms" />
        <MiniSummaryCard title="Most Booked Room" value={dashboardData.rooms.mostBookedRoom} helper="Based on confirmed bookings" icon={BedDouble} to="/bookings" />
        <MiniSummaryCard title="Occupancy Rate" value={`${dashboardData.rooms.occupancyRate}%`} helper="Current confirmed stays" icon={Home} to="/booking-calendar" />
        <MiniSummaryCard title="New Enquiries" value={dashboardData.enquiries.new} helper="Unread guest messages" icon={MessageSquareText} to="/messages" />
      </section>

      <section className="grid gap-6 xl:grid-cols-2">
        <ClickableCard to="/bookings">
          <Card className="h-full cursor-pointer transition-all hover:border-blue-200 hover:shadow-md">
            <CardHeader>
              <CardTitle>Monthly Booking Trend</CardTitle>
              <p className="text-sm text-muted">Total, Booking.com and website reservations by stay month.</p>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <AreaChart data={monthlyBookingTrend}>
                  <defs>
                    <linearGradient id="bookingTrend" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#2563EB" stopOpacity={0.32} />
                      <stop offset="95%" stopColor="#2563EB" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
                  <XAxis dataKey="month" tick={{ fontSize: 12, fill: '#64748B' }} />
                  <YAxis tick={{ fontSize: 12, fill: '#64748B' }} allowDecimals={false} />
                  <Tooltip contentStyle={{ borderRadius: '12px', border: '1px solid #E2E8F0', fontSize: '13px' }} />
                  <Area type="monotone" name="Total Bookings" dataKey="bookings" stroke="#2563EB" strokeWidth={3} fill="url(#bookingTrend)" />
                  <Area type="monotone" name="Booking.com Bookings" dataKey="bookingComBookings" stroke="#7C3AED" strokeWidth={2} fill="transparent" />
                  <Area type="monotone" name="Website Bookings" dataKey="websiteBookings" stroke="#10B981" strokeWidth={2} fill="transparent" />
                </AreaChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </ClickableCard>

        <ClickableCard to="/payments">
          <Card className="h-full cursor-pointer transition-all hover:border-blue-200 hover:shadow-md">
            <CardHeader>
              <CardTitle>Revenue Trend</CardTitle>
              <p className="text-sm text-muted">Paid accommodation revenue trend.</p>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={revenueTrend}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
                  <XAxis dataKey="month" tick={{ fontSize: 12, fill: '#64748B' }} />
                  <YAxis tick={{ fontSize: 12, fill: '#64748B' }} tickFormatter={(value) => `${Math.round(value / 1000)}k`} />
                  <Tooltip
                    contentStyle={{ borderRadius: '12px', border: '1px solid #E2E8F0', fontSize: '13px' }}
                    formatter={(value) => [currencyFormatter.format(value), 'Revenue']}
                  />
                  <Bar dataKey="revenue" fill="#2563EB" radius={[8, 8, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </ClickableCard>
      </section>

      <section className="grid gap-6 md:grid-cols-2">
        <StatusDonut title="Booking Status" data={bookingStatusData} to="/bookings" />
        <StatusDonut title="Payment Status" data={paymentStatusData} to="/payments" />
      </section>

      <section className="grid gap-6 xl:grid-cols-2">
        <CompactList
          title="Recent Bookings"
          description="Latest 5 reservations only. Click to view all bookings."
          emptyText="No recent bookings found."
          items={dashboardData.lists.recentBookings}
          to="/bookings"
          renderItem={(booking) => (
            <ListRow
              key={booking.bookingNo}
              title={`${booking.guest} • ${booking.room}`}
              subtitle={`${booking.bookingNo} • Check-in ${formatDate(booking.checkIn)}`}
              // right={currencyFormatter.format(booking.amount)}
              badge={<DashboardStatusBadge status={booking.status} />}
              to={`/bookings?focus=${encodeURIComponent(booking.bookingNo)}`}
            />
          )}
        />

        <CompactList
          title="Upcoming Check-ins"
          description="Next 5 arrivals from today onward. Click to open calendar."
          emptyText="No upcoming check-ins."
          items={dashboardData.lists.upcomingCheckIns}
          to="/booking-calendar"
          renderItem={(booking) => (
            <ListRow
              key={booking.bookingNo}
              title={`${formatDate(booking.checkIn)} • ${booking.guest}`}
              subtitle={`${booking.room} • ${booking.nights} night${booking.nights > 1 ? 's' : ''}`}
              badge={<DashboardStatusBadge status={booking.status} />}
              to={`/bookings?focus=${encodeURIComponent(booking.bookingNo)}`}
            />
          )}
        />
      </section>

      <section className="grid gap-6 xl:grid-cols-2">
        <CompactList
          title="Recent Payments"
          description="Latest 5 payment records only. Click to view payments."
          emptyText="No payment records found."
          items={dashboardData.lists.recentPayments}
          to="/payments"
          renderItem={(payment) => <PaymentListRow key={payment.transaction} payment={payment} />}
        />

        <CompactList
          title="Latest Messages"
          description="Latest 5 guest inquiries. Click to view messages."
          emptyText="No messages found."
          items={dashboardData.lists.latestMessages}
          to="/messages"
          renderItem={(message) => (
            <ListRow
              key={message.id}
              title={message.subject}
              subtitle={`${message.from} • ${message.message}`}
              badge={<DashboardStatusBadge status={message.status} fallback="New" />}
              to={`/messages?focus=${encodeURIComponent(message.focusId || message.id)}`}
            />
          )}
        />
      </section>
    </div>
  )
}
