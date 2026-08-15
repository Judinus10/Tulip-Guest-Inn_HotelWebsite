import { lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { useLocation } from 'react-router-dom';
import Navbar from './components/layout/Navbar';
import Footer from './components/layout/Footer';
import ScrollToTop, { ScrollRestorer } from './components/layout/ScrollToTop';
import SeoManager from './components/seo/SeoManager';

const Home = lazy(() => import('./pages/Home'));
const Rooms = lazy(() => import('./pages/Rooms'));
const RoomDetails = lazy(() => import('./pages/RoomDetails'));
const Facilities = lazy(() => import('./pages/Facilities'));
const Gallery = lazy(() => import('./pages/Gallery'));
const Attractions = lazy(() => import('./pages/Attractions'));
const About = lazy(() => import('./pages/About'));
const Contact = lazy(() => import('./pages/Contact'));
const Booking = lazy(() => import('./pages/Booking'));
const MultiRoomBooking = lazy(() => import('./pages/MultiRoomBooking'));
const BookingBill = lazy(() => import('./pages/BookingBill'));
const PointPedroAccommodation = lazy(() => import('./pages/PointPedroAccommodation'));
const NotFound = lazy(() => import('./pages/NotFound'));

function PageWrapper({ children }: { children: React.ReactNode }) {
  const location = useLocation();
  return (
    <AnimatePresence mode="wait">
      <motion.div
        key={location.pathname}
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        transition={{ duration: 0.3 }}
      >
        {children}
      </motion.div>
    </AnimatePresence>
  );
}

function AppLayout() {
  const location = useLocation();
  return (
    <>
      <ScrollRestorer />
      <SeoManager />
      <Navbar />
      <PageWrapper key={location.pathname}>
        <Suspense fallback={<div className="min-h-screen bg-white" aria-label="Loading page" />}>
        <Routes location={location}>
          <Route path="/" element={<Home />} />
          <Route path="/rooms" element={<Rooms />} />
          <Route path="/rooms/:id" element={<RoomDetails />} />
          <Route path="/facilities" element={<Facilities />} />
          <Route path="/gallery" element={<Gallery />} />
          <Route path="/attractions" element={<Attractions />} />
          <Route path="/accommodation-point-pedro" element={<PointPedroAccommodation />} />
          <Route path="/about" element={<About />} />
          <Route path="/contact" element={<Contact />} />
          <Route path="/booking" element={<Booking />} />
          <Route path="/multi-room-booking" element={<MultiRoomBooking />} />
          <Route path="/booking-bill" element={<BookingBill />} />
          <Route path="*" element={<NotFound />} />
        </Routes>
        </Suspense>
      </PageWrapper>
      <Footer />
      <ScrollToTop />
    </>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AppLayout />
    </BrowserRouter>
  );
}
