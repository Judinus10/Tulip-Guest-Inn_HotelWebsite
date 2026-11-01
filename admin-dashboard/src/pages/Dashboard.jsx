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
  LogIn,
  LogOut,
  Download,
  RotateCcw,
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
import { fetchBookings } from '@/services/bookingsApi'
import { fetchPayments } from '@/services/paymentsApi'
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
    totalRooms: 0,
    availableRooms: 0,
    availableTonight: 0,
    todayCheckIns: 0,
    todayCheckOuts: 0,
    today: '',
    checkInReferences: [],
    checkOutReferences: [],
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

function localDateString(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function getDateFilterRange(preset, fromDate, toDate) {
  const todayParts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Colombo', year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(new Date()).reduce((parts, item) => ({ ...parts, [item.type]: item.value }), {})
  const todayString = `${todayParts.year}-${todayParts.month}-${todayParts.day}`
  const today = new Date(`${todayString}T12:00:00`)
  if (preset === 'today') return { from: todayString, to: todayString }
  if (preset === 'week') {
    const start = new Date(today)
    const weekday = (start.getDay() + 6) % 7
    start.setDate(start.getDate() - weekday)
    const end = new Date(start)
    end.setDate(end.getDate() + 6)
    return { from: localDateString(start), to: localDateString(end) }
  }
  if (preset === 'month') return { from: `${todayParts.year}-${todayParts.month}-01`, to: todayString }
  if (preset === 'year') return { from: `${todayParts.year}-01-01`, to: todayString }
  if (preset === 'custom') return { from: fromDate, to: toDate }
  return { from: '', to: '' }
}

function matchesDateRange(value, range) {
  const date = String(value || '').slice(0, 10)
  if (!date) return false
  return (!range.from || date >= range.from) && (!range.to || date <= range.to)
}

function distributionFrom(items, getStatus, labels) {
  const counts = new Map(labels.map((label) => [label, 0]))
  items.forEach((item) => {
    const status = getStatus(item)
    if (counts.has(status)) counts.set(status, counts.get(status) + 1)
  })
  return labels.map((name) => ({ name, value: counts.get(name) || 0 }))
}

function sumPaidPayments(payments, within = null) {
  return payments.reduce((sum, payment) => {
    if (payment.payment_status !== 'paid') return sum
    if (within && !matchesDateRange(payment.created_at, within)) return sum
    return sum + Number(payment.amount || 0)
  }, 0)
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
      monthKey: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
      month: d.toLocaleString('en-US', { month: 'short' }),
      [valueKey]: 0,
      websiteBookings: 0,
      bookingComBookings: 0,
    })
  }

  data.forEach((item) => {
    const existing = months.find((m) => item.monthKey ? m.monthKey === item.monthKey : m.month === item.month)

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
    { Section: 'Rooms', Metric: 'Available Tonight', Value: data.rooms.availableTonight },
    { Section: 'Bookings', Metric: 'Today Check-ins', Value: data.rooms.todayCheckIns },
    { Section: 'Bookings', Metric: 'Today Check-outs', Value: data.rooms.todayCheckOuts },
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

function MiniSummaryCard({ title, value, helper, icon: Icon, to, tone = 'blue' }) {
  return (
    <ClickableCard to={to}>
      <Card className="h-full min-h-[112px] cursor-pointer transition-all hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
        <CardContent className="p-4">
          <div className="flex h-full items-start gap-3">
            <div className={`rounded-xl p-3 ${tone === 'amber' ? 'bg-amber-50 text-amber-700' : tone === 'green' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700'}`}>
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
  const [bookingRecords, setBookingRecords] = useState([])
  const [paymentRecords, setPaymentRecords] = useState([])
  const [detailedRecordsLoaded, setDetailedRecordsLoaded] = useState(false)
  const [datePreset, setDatePreset] = useState('all')
  const [customFromDate, setCustomFromDate] = useState('')
  const [customToDate, setCustomToDate] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let isMounted = true

    async function loadDashboardStats() {
      try {
        setIsLoading(true)
        setError('')
        const [statsResult, bookingsResult, paymentsResult] = await Promise.allSettled([
          fetchDashboardStats(),
          fetchBookings(),
          fetchPayments(),
        ])

        if (isMounted) {
          if (statsResult.status === 'rejected') throw statsResult.reason
          const data = statsResult.value
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
          if (bookingsResult.status === 'fulfilled') setBookingRecords(bookingsResult.value)
          if (paymentsResult.status === 'fulfilled') setPaymentRecords(paymentsResult.value)
          setDetailedRecordsLoaded(bookingsResult.status === 'fulfilled' && paymentsResult.status === 'fulfilled')
          if (bookingsResult.status === 'rejected' || paymentsResult.status === 'rejected') {
            setError('Some dashboard filters may be unavailable because booking or payment records could not be loaded.')
          }
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

  const dateRange = useMemo(
    () => getDateFilterRange(datePreset, customFromDate, customToDate),
    [datePreset, customFromDate, customToDate]
  )

  const filteredBookings = useMemo(() => bookingRecords.filter((booking) => {
    if (!matchesDateRange(booking.check_in, dateRange)) return false
    return true
  }), [bookingRecords, dateRange])

  const filteredBookingIds = useMemo(() => new Set(filteredBookings.filter((booking) => !booking.is_external).map((booking) => booking.id)), [filteredBookings])
  const filteredGroupIds = useMemo(() => new Set(filteredBookings.filter((booking) => !booking.is_external && booking.booking_group_id).map((booking) => booking.booking_group_id)), [filteredBookings])
  const filteredPayments = useMemo(() => paymentRecords.filter((payment) => {
    const belongsToFilteredBooking = filteredBookingIds.has(payment.booking_id)
      || (payment.booking_group_id && filteredGroupIds.has(payment.booking_group_id))
    return Boolean(belongsToFilteredBooking)
  }), [paymentRecords, filteredBookingIds, filteredGroupIds])

  const filteredDashboardData = useMemo(() => {
    if (!detailedRecordsLoaded) return dashboardData

    const bookingStatusNames = {
      pending: 'Pending', confirmed: 'Confirmed', checked_in: 'Checked In', checked_out: 'Checked Out',
      cancelled: 'Cancelled', no_show: 'No Show', external: 'Booking.com',
    }
    const paymentStatusNames = {
      paid: 'Paid', pending: 'Payment Pending', failed: 'Failed', cancelled: 'Cancelled',
      refunded: 'Refunded', no_pay: 'No Pay', external: 'No Pay',
    }
    const statusLabel = (booking) => booking.is_external
      ? (booking.booking_status === 'cancelled' ? 'Cancelled' : 'Booking.com')
      : (bookingStatusNames[booking.booking_status] || 'Pending')
    const paymentLabel = (booking) => booking.is_external
      ? 'No Pay'
      : (paymentStatusNames[booking.payment_status] || 'Payment Pending')
    const bookingStatuses = ['Pending', 'Confirmed', 'Checked In', 'Checked Out', 'Cancelled', 'No Show', 'Booking.com']
    const paymentStatuses = ['Paid', 'Payment Pending', 'No Pay', 'Failed', 'Cancelled', 'Refunded']
    const bookingCounts = filteredBookings.reduce((counts, booking) => {
      const status = booking.booking_status
      counts.total += 1
      if (booking.is_external) counts.bookingCom += 1
      else counts.website += 1
      if (status === 'pending') counts.pending += 1
      if (status === 'confirmed') counts.confirmed += 1
      if (status === 'cancelled') counts.cancelled += 1
      if (booking.payment_status === 'paid') counts.paid += 1
      if (booking.payment_status === 'pending') counts.paymentPending += 1
      return counts
    }, { total: 0, website: 0, bookingCom: 0, pending: 0, confirmed: 0, cancelled: 0, paid: 0, paymentPending: 0 })

    const monthlyBookings = new Map()
    filteredBookings.forEach((booking) => {
      const monthKey = String(booking.check_in || '').slice(0, 7)
      if (!monthKey) return
      const current = monthlyBookings.get(monthKey) || { bookings: 0, websiteBookings: 0, bookingComBookings: 0 }
      current.bookings += 1
      if (booking.is_external) current.bookingComBookings += 1
      else current.websiteBookings += 1
      monthlyBookings.set(monthKey, current)
    })
    const monthlyBookingTrend = Array.from(monthlyBookings, ([monthKey, values]) => ({ monthKey, ...values }))
      .sort((left, right) => left.monthKey.localeCompare(right.monthKey))
      .map(({ monthKey, ...values }) => ({
        monthKey,
        month: new Date(`${monthKey}-01T12:00:00`).toLocaleString('en-US', { month: 'short', year: '2-digit' }),
        ...values,
      }))

    const revenueByMonth = new Map()
    filteredPayments.forEach((payment) => {
      if (payment.payment_status !== 'paid') return
      const monthKey = String(payment.created_at || '').slice(0, 7)
      if (!monthKey) return
      revenueByMonth.set(monthKey, (revenueByMonth.get(monthKey) || 0) + Number(payment.amount || 0))
    })
    const revenueTrend = Array.from(revenueByMonth, ([monthKey, revenue]) => ({ monthKey, revenue }))
      .sort((left, right) => left.monthKey.localeCompare(right.monthKey))
      .map(({ monthKey, revenue }) => ({
        monthKey,
        month: new Date(`${monthKey}-01T12:00:00`).toLocaleString('en-US', { month: 'short', year: '2-digit' }),
        revenue,
      }))

    const recentBookings = [...filteredBookings]
      .sort((left, right) => String(right.created_at || '').localeCompare(String(left.created_at || '')))
      .slice(0, 5)
      .map((booking) => ({
        bookingNo: booking.booking_no,
        guest: booking.guest_name,
        room: booking.room_name,
        checkIn: booking.check_in,
        nights: booking.total_nights,
        amount: booking.total_amount,
        status: statusLabel(booking),
        createdAt: booking.created_at,
      }))
    const recentPayments = [...filteredPayments]
      .sort((left, right) => String(right.created_at || '').localeCompare(String(left.created_at || '')))
      .slice(0, 5)
      .map((payment) => ({
        transaction: payment.transaction_id,
        guest: payment.guest_name,
        amount: payment.amount,
        status: paymentStatusNames[payment.payment_status] || 'Payment Pending',
        createdAt: payment.created_at,
      }))

    const todayRange = getDateFilterRange('today', '', '')
    const monthRange = getDateFilterRange('month', '', '')
    const yearRange = getDateFilterRange('year', '', '')
    const revenueForRange = (range) => sumPaidPayments(filteredPayments, range)

    return {
      ...dashboardData,
      cards: {
        ...dashboardData.cards,
        totalBookings: bookingCounts.total,
        websiteBookings: bookingCounts.website,
        bookingComBookings: bookingCounts.bookingCom,
        pendingBookings: bookingCounts.pending,
        confirmedBookings: bookingCounts.confirmed,
        cancelledBookings: bookingCounts.cancelled,
        paidBookings: bookingCounts.paid,
        paymentPendingBookings: bookingCounts.paymentPending,
        totalRevenue: sumPaidPayments(filteredPayments),
      },
      revenue: {
        ...dashboardData.revenue,
        today: revenueForRange(todayRange),
        currentMonth: revenueForRange(monthRange),
        currentYear: revenueForRange(yearRange),
      },
      charts: {
        ...dashboardData.charts,
        monthlyBookingTrend,
        revenueTrend,
        bookingStatusDistribution: distributionFrom(filteredBookings, statusLabel, bookingStatuses),
        paymentStatusDistribution: distributionFrom(filteredBookings, paymentLabel, paymentStatuses),
      },
      lists: {
        ...dashboardData.lists,
        recentBookings,
        recentPayments,
      },
    }
  }, [dashboardData, detailedRecordsLoaded, filteredBookings, filteredPayments])

  const bookingStatusData = useMemo(
    () => normalizeDistribution(filteredDashboardData.charts.bookingStatusDistribution),
    [filteredDashboardData.charts.bookingStatusDistribution]
  )

  const paymentStatusData = useMemo(
    () => normalizeDistribution(filteredDashboardData.charts.paymentStatusDistribution),
    [filteredDashboardData.charts.paymentStatusDistribution]
  )

  const monthlyBookingTrend = useMemo(
    () => datePreset === 'all'
      ? fillLastSixMonths(
        filteredDashboardData.charts.monthlyBookingTrend,
        'bookings'
      )
      : filteredDashboardData.charts.monthlyBookingTrend,
    [datePreset, filteredDashboardData.charts.monthlyBookingTrend]
  )

  const revenueTrend = useMemo(
    () => datePreset === 'all'
      ? fillLastSixMonths(
        filteredDashboardData.charts.revenueTrend,
        'revenue'
      )
      : filteredDashboardData.charts.revenueTrend,
    [datePreset, filteredDashboardData.charts.revenueTrend]
  )

  const kpis = [
    { title: 'Total Bookings', value: filteredDashboardData.cards.totalBookings, helper: 'Matching bookings', icon: CalendarCheck, to: '/bookings' },
    { title: 'Booking.com Bookings', value: filteredDashboardData.cards.bookingComBookings, helper: 'Matching imports', icon: TrendingUp, to: '/bookings' },
    { title: 'Website Bookings', value: filteredDashboardData.cards.websiteBookings, helper: 'Direct website bookings', icon: CalendarCheck, to: '/bookings' },
    { title: 'Cancelled Bookings', value: filteredDashboardData.cards.cancelledBookings, helper: 'Matching cancelled requests', icon: BedDouble, to: '/bookings' },
    { title: 'Total Enquiries', value: filteredDashboardData.cards.totalEnquiries, helper: 'Guest messages · not filtered', icon: Mail, to: '/messages' },
    { title: 'Total Revenue', value: currencyFormatter.format(filteredDashboardData.cards.totalRevenue), helper: 'Paid matching bookings', icon: DollarSign, to: '/payments' },
    { title: 'Paid Bookings', value: filteredDashboardData.cards.paidBookings, helper: 'Matching paid bookings', icon: CreditCard, to: '/payments' },
    { title: 'Payment Pending', value: filteredDashboardData.cards.paymentPendingBookings, helper: 'Matching unpaid bookings', icon: CreditCard, to: '/payments' },
  ]

  const dailyBookingsLink = (kind, references) => `/bookings?${new URLSearchParams({
    dashboard_stay: kind, stay_date: dashboardData.rooms.today,
    references: (references || []).join(','),
  }).toString()}`

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
      rows: buildDashboardReportRows(filteredDashboardData, monthlyBookingTrend, revenueTrend),
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
        <div className="flex flex-wrap items-center justify-end gap-1.5 lg:flex-nowrap">
          <select
            aria-label="Dashboard date range"
            value={datePreset}
            onChange={(event) => setDatePreset(event.target.value)}
            className="h-10 w-[130px] shrink-0 rounded-lg border border-border bg-white px-2 text-sm text-text-primary shadow-sm"
          >
            <option value="all">All Dates</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="year">This Year</option>
            <option value="custom">Custom Range</option>
          </select>
          {datePreset === 'custom' ? (
            <div className="flex shrink-0 items-center gap-1.5">
              <input
                aria-label="Start date"
                type="date"
                value={customFromDate}
                onChange={(event) => setCustomFromDate(event.target.value)}
                className="h-10 w-[108px] rounded-lg border border-border bg-white px-1 text-sm text-text-primary shadow-sm"
              />
              <span className="text-sm text-muted">to</span>
              <input
                aria-label="End date"
                type="date"
                value={customToDate}
                min={customFromDate || undefined}
                onChange={(event) => setCustomToDate(event.target.value)}
                className="h-10 w-[108px] rounded-lg border border-border bg-white px-1 text-sm text-text-primary shadow-sm"
              />
            </div>
          ) : null}
          <Button type="button" variant="outline" size="sm" className="shrink-0 px-2.5" onClick={() => {
            setDatePreset('all')
            setCustomFromDate('')
            setCustomToDate('')
          }}>
            <RotateCcw className="h-4 w-4" /> Clear Filter
          </Button>
          <Dropdown
            trigger={
              <Button type="button" variant="outline" size="sm" className="shrink-0 px-2.5">
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
        </div>
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
        <MiniSummaryCard title="Today Revenue" value={currencyFormatter.format(filteredDashboardData.revenue.today)} helper="Paid today · matching bookings" icon={DollarSign} to="/payments" />
        <MiniSummaryCard title="Month Revenue" value={currencyFormatter.format(filteredDashboardData.revenue.currentMonth)} helper="Paid this month · matching bookings" icon={TrendingUp} to="/payments" />
        <MiniSummaryCard title="Year Revenue" value={currencyFormatter.format(filteredDashboardData.revenue.currentYear)} helper="Paid this year · matching bookings" icon={CreditCard} to="/payments" />

        <MiniSummaryCard title="Today’s Check-ins" value={dashboardData.rooms.todayCheckIns} helper="Scheduled arrivals · includes Booking.com" icon={LogIn} to={dailyBookingsLink('checkins', dashboardData.rooms.checkInReferences)} />
        <MiniSummaryCard title="Today’s Check-outs" value={dashboardData.rooms.todayCheckOuts} helper="Scheduled departures · includes Booking.com" icon={LogOut} tone="amber" to={dailyBookingsLink('checkouts', dashboardData.rooms.checkOutReferences)} />

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

        <MiniSummaryCard title="Available Tonight" value={`${dashboardData.rooms.availableTonight} / ${dashboardData.rooms.totalRooms}`} helper="Available units · check availability calendar" icon={BedDouble} tone="green" to="/booking-calendar" />
        <MiniSummaryCard title="Most Booked Room" value={dashboardData.rooms.mostBookedRoom} helper="Based on confirmed bookings" icon={BedDouble} to="/bookings" />
        <MiniSummaryCard title="Occupancy Rate" value={`${dashboardData.rooms.occupancyRate}%`} helper="Confirmed stays · includes Booking.com" icon={Home} to="/booking-calendar" />
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
          items={filteredDashboardData.lists.recentBookings}
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
          items={filteredDashboardData.lists.recentPayments}
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
