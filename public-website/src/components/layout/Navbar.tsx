import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Menu, X } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

const navLinks = [
  { label: 'Home', path: '/' },
  { label: 'Rooms', path: '/rooms' },
  { label: 'Facilities', path: '/facilities' },
  { label: 'Gallery', path: '/gallery' },
  { label: 'Attractions', path: '/attractions' },
  { label: 'About', path: '/about' },
  { label: 'Contact', path: '/contact' },
];

export default function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const location = useLocation();

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 60);
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    setMobileOpen(false);
  }, [location]);

  useEffect(() => {
    document.body.style.overflow = mobileOpen ? 'hidden' : '';
    return () => { document.body.style.overflow = ''; };
  }, [mobileOpen]);

  const isActive = (path: string) =>
    path === '/' ? location.pathname === '/' : location.pathname.startsWith(path);

  return (
    <>
      <motion.header
        initial={{ y: -100 }}
        animate={{ y: 0 }}
        transition={{ duration: 0.6, ease: 'easeOut' }}
        className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${
          scrolled ? 'bg-white shadow-luxury border-b border-border' : 'bg-transparent'
        }`}
      >
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-20">
            {/* Logo */}
            <Link to="/" className="flex items-center" aria-label="Tulip Guest Inn home">
              <img
                src="/assets/tulip-logo.png"
                alt="Tulip Guest Inn"
                className="h-14 w-auto object-contain"
              />
            </Link>

            {/* Desktop Nav */}
            <nav className="hidden lg:flex items-center gap-8">
              {navLinks.map((link) => (
                <Link
                  key={link.path}
                  to={link.path}
                  className={`relative text-[11px] tracking-[0.18em] uppercase font-medium transition-colors duration-300 group ${
                    scrolled
                      ? isActive(link.path)
                        ? 'text-gold'
                        : 'text-dark hover:text-gold'
                      : isActive(link.path)
                      ? 'text-gold'
                      : 'text-white hover:text-gold'
                  }`}
                >
                  {link.label}
                  <span
                    className={`absolute -bottom-1 left-0 h-[1px] bg-gold transition-all duration-300 ${
                      isActive(link.path) ? 'w-full' : 'w-0 group-hover:w-full'
                    }`}
                  />
                </Link>
              ))}
            </nav>

            {/* Book Now Button */}
            <div className="hidden lg:block">
              <Link
                to="/booking"
                className="btn-primary text-[10px] py-2.5 px-6"
              >
                Book Now
              </Link>
            </div>

            {/* Mobile Toggle */}
            <button
              onClick={() => setMobileOpen(!mobileOpen)}
              className={`lg:hidden p-2 transition-colors duration-300 ${
                scrolled ? 'text-dark' : 'text-white'
              }`}
              aria-label="Toggle menu"
            >
              {mobileOpen ? <X size={24} /> : <Menu size={24} />}
            </button>
          </div>
        </div>
      </motion.header>

      {/* Mobile Drawer */}
      <AnimatePresence>
        {mobileOpen && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="fixed inset-0 bg-black/60 z-40 lg:hidden"
              onClick={() => setMobileOpen(false)}
            />
            <motion.div
              initial={{ x: '100%' }}
              animate={{ x: 0 }}
              exit={{ x: '100%' }}
              transition={{ duration: 0.35, ease: 'easeOut' }}
              className="fixed top-0 right-0 h-full w-[280px] bg-white z-50 lg:hidden shadow-luxury-lg flex flex-col"
            >
              <div className="flex items-center justify-between p-6 border-b border-border">
                <Link to="/" aria-label="Tulip Guest Inn home">
                  <img
                    src="/assets/tulip-logo.png"
                    alt="Tulip Guest Inn"
                    className="h-12 w-auto object-contain"
                  />
                </Link>
                <button onClick={() => setMobileOpen(false)} className="text-dark p-1">
                  <X size={20} />
                </button>
              </div>

              <nav className="flex flex-col p-6 gap-1 flex-1">
                {navLinks.map((link, i) => (
                  <motion.div
                    key={link.path}
                    initial={{ opacity: 0, x: 20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: i * 0.06, duration: 0.3 }}
                  >
                    <Link
                      to={link.path}
                      className={`block py-3 text-[11px] tracking-[0.18em] uppercase font-medium border-b border-border transition-colors duration-200 ${
                        isActive(link.path) ? 'text-gold' : 'text-dark hover:text-gold'
                      }`}
                    >
                      {link.label}
                    </Link>
                  </motion.div>
                ))}
              </nav>

              <div className="p-6">
                <Link to="/booking" className="btn-primary w-full justify-center text-[10px] py-3">
                  Book Your Stay
                </Link>
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </>
  );
}
