import {
  LayoutDashboard,
  BedDouble,
  CalendarCheck,
  CalendarDays,
  CreditCard,
  Images,
  Tag,
  MessageSquare,
  Globe,
  Bell,
  Sparkles,
  KeyRound,
  Mail,
} from 'lucide-react'

export const SIDEBAR_WIDTH_EXPANDED = 288
export const SIDEBAR_WIDTH_COLLAPSED = 72

export const navigation = [
  {
    label: null,
    items: [{ name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard }],
  },
  {
    label: 'Reservation Management',
    items: [
      { name: 'Bookings', href: '/bookings', icon: CalendarCheck },
      { name: 'Booking Calendar', href: '/booking-calendar', icon: CalendarDays },
      { name: 'Payments', href: '/payments', icon: CreditCard },
    ],
  },
  {
    label: 'Hotel Management',
    items: [
      { name: 'Rooms', href: '/rooms', icon: BedDouble },
      { name: 'Gallery', href: '/gallery', icon: Images },
      { name: 'Experience', href: '/experience', icon: Sparkles },
      { name: 'Offers & Packages', href: '/offers', icon: Tag },
    ],
  },
  {
    label: 'Communication',
    items: [
      { name: 'Messages', href: '/messages', icon: MessageSquare },
      { name: 'Notifications', href: '/notifications', icon: Bell },
    ],
  },
  {
    label: 'Website Management',
    items: [
      { name: 'Website Settings', href: '/website-settings', icon: Globe },
      { name: 'Mail Integrations', href: '/mail-settings', icon: Mail },
      { name: 'Admin Password', href: '/admin-password', icon: KeyRound },
    ],
  },
]

export const pageTitles = {
  '/dashboard': 'Dashboard',
  '/rooms': 'Rooms',
  '/bookings': 'Bookings',
  '/booking-calendar': 'Booking Calendar',
  '/payments': 'Payments',
  '/gallery': 'Gallery',
  '/experience': 'Experience',
  '/offers': 'Offers & Packages',
  '/messages': 'Messages',
  '/website-settings': 'Website Settings',
  '/admin-password': 'Admin Password',
  '/mail-settings': 'Mail & Business Integrations',
  '/notifications': 'Notifications',
}

export const pageDescriptions = {
  '/dashboard': 'Overview of guest house reservations, rooms, payments, and packages.',
  '/rooms': 'Manage guest house rooms and accommodation details.',
  '/bookings': 'View and manage guest reservations.',
  '/booking-calendar': 'Visual overview of room occupancy and booking schedules.',
  '/payments': 'Track booking payments and refunds.',
  '/gallery': 'Manage room, garden, facility, and property photos.',
  '/experience': 'Manage public website experiences, attractions, food, culture, and activities.',
  '/offers': 'Create and manage accommodation offers and packages.',
  '/messages': 'Manage guest inquiries and contact requests.',
  '/website-settings': 'Manage public website contact information and social links.',
  '/admin-password': 'Change the dashboard administrator password.',
  '/mail-settings': 'Manage mail providers, sender accounts, routing and business links.',
  '/notifications': 'View booking, payment, and system notifications.',
}
