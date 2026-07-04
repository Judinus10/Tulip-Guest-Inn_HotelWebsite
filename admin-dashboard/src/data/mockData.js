export const dashboardStats = [
  { label: 'Total Bookings', value: '0', change: 'Live data loads from database', key: 'bookings' },
  { label: 'Confirmed Bookings', value: '0', change: 'Confirmed stays only', key: 'confirmed' },
  { label: 'Pending Bookings', value: '0', change: 'Waiting for admin review', key: 'pending' },
  { label: 'Active Rooms', value: '6', change: 'Jebal Homes room count', key: 'rooms' },
]

export const revenueChartData = [
  { month: 'Jan', revenue: 0, bookings: 0 },
  { month: 'Feb', revenue: 0, bookings: 0 },
  { month: 'Mar', revenue: 0, bookings: 0 },
  { month: 'Apr', revenue: 0, bookings: 0 },
  { month: 'May', revenue: 0, bookings: 0 },
  { month: 'Jun', revenue: 0, bookings: 0 },
]

export const occupancyChartData = [
  { day: 'Mon', rate: 0 },
  { day: 'Tue', rate: 0 },
  { day: 'Wed', rate: 0 },
  { day: 'Thu', rate: 0 },
  { day: 'Fri', rate: 0 },
  { day: 'Sat', rate: 0 },
  { day: 'Sun', rate: 0 },
]

export const recentBookings = []

export const rooms = [
  { id: 'R-001', name: 'Ground Floor Room 1', type: 'Ground Floor', price: 'LKR 0', status: 'Available', floor: 'Ground' },
  { id: 'R-002', name: 'Ground Floor Room 2', type: 'Ground Floor', price: 'LKR 0', status: 'Available', floor: 'Ground' },
  { id: 'R-003', name: 'First Floor Room 1', type: 'First Floor', price: 'LKR 0', status: 'Available', floor: 'First' },
  { id: 'R-004', name: 'First Floor Room 2', type: 'First Floor', price: 'LKR 0', status: 'Available', floor: 'First' },
  { id: 'R-005', name: 'Family Room', type: 'Family', price: 'LKR 0', status: 'Available', floor: 'Ground' },
  { id: 'R-006', name: 'Private Cottage', type: 'Cottage', price: 'LKR 0', status: 'Available', floor: 'Ground' },
]

export const bookings = []

export const calendarEvents = []

export const payments = []

export const galleryItems = [
  { id: 'G-01', title: 'Ground Floor Room', category: 'Rooms', status: 'Published' },
  { id: 'G-02', title: 'First Floor Room', category: 'Rooms', status: 'Published' },
  { id: 'G-03', title: 'Private Cottage', category: 'Rooms', status: 'Published' },
  { id: 'G-04', title: 'Garden Area', category: 'Amenities', status: 'Published' },
]

export const offers = [
  {
    id: 'O-01',
    title: 'Family Stay Offer',
    description: 'Comfortable guest house stay option for families using clean rooms and shared facilities.',
    package_category: 'Family Stay Offer',
    discount_type: 'percentage',
    discount_value: 0,
    start_date: '2026-06-01',
    end_date: '2026-12-31',
    status: 'inactive',
    image_path: '',
    image_preview: '',
    created_at: '2026-06-01T08:00:00Z',
    updated_at: '2026-06-15T08:00:00Z',
  },
  {
    id: 'O-02',
    title: 'Short Stay Offer',
    description: 'Simple short-stay option for guests who need a clean and peaceful place to stay.',
    package_category: 'Short Stay Offer',
    discount_type: 'fixed',
    discount_value: 0,
    start_date: '2026-06-10',
    end_date: '2026-09-30',
    status: 'inactive',
    image_path: '',
    image_preview: '',
    created_at: '2026-06-02T08:00:00Z',
    updated_at: '2026-06-15T08:00:00Z',
  },
]

export const messages = []

export const reviews = [
  { id: 'RV-01', guest: 'Guest', rating: 5, comment: 'Clean room, peaceful environment, and helpful service.', date: 'Jun 12, 2026', status: 'Published' },
  { id: 'RV-02', guest: 'Family Guest', rating: 4, comment: 'Good guest house for a short family stay.', date: 'Jun 10, 2026', status: 'Published' },
]

export const users = [
  { id: 'U-01', name: 'Admin User', email: 'admin@jebalhomes.com', role: 'Administrator', status: 'Active' },
  { id: 'U-02', name: 'Booking Manager', email: 'bookings@jebalhomes.com', role: 'Front Desk', status: 'Active' },
]

export const activityLogs = [
  { id: 'AL-501', user: 'Admin User', action: 'Reviewed booking inquiries', module: 'Bookings', timestamp: 'Jun 15, 2026 08:42' },
  { id: 'AL-500', user: 'Booking Manager', action: 'Updated room availability', module: 'Rooms', timestamp: 'Jun 15, 2026 08:15' },
]

export const notifications = [
  { id: 'N-101', title: 'Booking dashboard ready', message: 'Bookings are loaded from the database when available.', time: 'Today', read: false },
  { id: 'N-100', title: 'Contact enquiries ready', message: 'Guest enquiries can be reviewed from Messages.', time: 'Today', read: true },
]

export const languages = [
  { code: 'en', name: 'English', status: 'Default', progress: '100%' },
  { code: 'ta', name: 'Tamil', status: 'Draft', progress: '0%' },
  { code: 'si', name: 'Sinhala', status: 'Draft', progress: '0%' },
]

export const seoPages = [
  { page: 'Homepage', title: 'Jebal Homes — Comfortable Guest House', score: 0, status: 'Needs Review' },
  { page: 'Rooms', title: 'Rooms | Jebal Homes', score: 0, status: 'Needs Review' },
  { page: 'Contact', title: 'Contact Jebal Homes', score: 0, status: 'Needs Review' },
]

export const contentPages = [
  { id: 'CP-01', title: 'Homepage Hero', type: 'Hero Banner', lastUpdated: 'Jun 14, 2026', status: 'Published' },
  { id: 'CP-02', title: 'About Jebal Homes', type: 'Page Content', lastUpdated: 'Jun 13, 2026', status: 'Published' },
  { id: 'CP-03', title: 'Rooms Content', type: 'Page Content', lastUpdated: 'Jun 12, 2026', status: 'Published' },
  { id: 'CP-04', title: 'Contact Details', type: 'Contact Content', lastUpdated: 'Jun 11, 2026', status: 'Published' },
]

export const settings = {
  hotelName: 'Jebal Homes',
  phone: '+94 77 000 0000',
  email: 'bookings@jebalhomes.com',
  address: 'Jebal Homes, Sri Lanka',
  timezone: 'Asia/Colombo',
  currency: 'LKR',
}

export const profile = {
  name: 'Admin User',
  email: 'admin@jebalhomes.com',
  role: 'Administrator',
}
