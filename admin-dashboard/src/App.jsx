import { Routes, Route, Navigate } from 'react-router-dom'
import { AdminLayout } from '@/components/layout/AdminLayout'
import ProtectedRoute from '@/routes/ProtectedRoute'
import Login from '@/pages/Login'
import Dashboard from '@/pages/Dashboard'
import Rooms from '@/pages/Rooms'
import Bookings from '@/pages/Bookings'
import BookingCalendar from '@/pages/BookingCalendar'
import Payments from '@/pages/Payments'
import Gallery from '@/pages/Gallery'
import Offers from '@/pages/Offers'
import Messages from '@/pages/Messages'
import WebsiteSettings from '@/pages/WebsiteSettings'
import Notifications from '@/pages/Notifications'
import Experience from '@/pages/Experience'
import AdminPassword from '@/pages/AdminPassword'
import MailSettings from '@/pages/MailSettings'

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<Login />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<AdminLayout />}>
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/rooms" element={<Rooms />} />
          <Route path="/bookings" element={<Bookings />} />
          <Route path="/booking-calendar" element={<BookingCalendar />} />
          <Route path="/payments" element={<Payments />} />
          <Route path="/gallery" element={<Gallery />} />
          <Route path="/offers" element={<Offers />} />
          <Route path="/messages" element={<Messages />} />
          <Route path="/website-settings" element={<WebsiteSettings />} />
          <Route path="/notifications" element={<Notifications />} />
          <Route path="/experience" element={<Experience />} />
          <Route path="/admin-password" element={<AdminPassword />} />
          <Route path="/mail-settings" element={<MailSettings />} />
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  )
}
