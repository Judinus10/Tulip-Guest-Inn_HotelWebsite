import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, ChevronLeft, ChevronRight } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import { galleryImages } from '../data/gallery';
import type { GalleryCategory } from '../data/gallery';

const categories: { value: GalleryCategory; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'rooms', label: 'Rooms' },
  { value: 'pool', label: 'Pool' },
  { value: 'garden', label: 'Garden' },
  { value: 'exterior', label: 'Exterior' },
  { value: 'facilities', label: 'Facilities' },
];

export default function Gallery() {
  const [active, setActive] = useState<GalleryCategory>('all');
  const [lightbox, setLightbox] = useState<number | null>(null);

  const filtered =
    active === 'all' ? galleryImages : galleryImages.filter((img) => img.category === active);

  const openLightbox = (index: number) => setLightbox(index);
  const closeLightbox = () => setLightbox(null);
  const prevImage = () => setLightbox((i) => (i !== null ? (i - 1 + filtered.length) % filtered.length : null));
  const nextImage = () => setLightbox((i) => (i !== null ? (i + 1) % filtered.length : null));

  return (
    <main>
      <PageHero
        title="Gallery"
        subtitle="A visual journey through Tulip Guest Inn — our rooms, gardens, pool and surroundings."
        image="https://images.pexels.com/photos/1743229/pexels-photo-1743229.jpeg?auto=compress&cs=tinysrgb&w=1600"
        breadcrumb="Photo Gallery"
      />

      {/* Filters */}
      <section className="bg-white border-b border-border sticky top-20 z-30">
        <div className="container-custom">
          <div className="flex flex-wrap items-center gap-0">
            {categories.map((cat) => (
              <button
                key={cat.value}
                onClick={() => setActive(cat.value)}
                className={`px-7 py-5 text-[10px] tracking-[0.2em] uppercase font-medium transition-all duration-300 border-b-2 ${
                  active === cat.value
                    ? 'border-gold text-gold'
                    : 'border-transparent text-gray-400 hover:text-dark hover:border-border'
                }`}
              >
                {cat.label}
              </button>
            ))}
            <span className="ml-auto text-xs text-gray-400 pr-4 hidden sm:block">
              {filtered.length} {filtered.length === 1 ? 'image' : 'images'}
            </span>
          </div>
        </div>
      </section>

      {/* Gallery Grid */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <motion.div layout className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <AnimatePresence mode="popLayout">
              {filtered.map((img, i) => (
                <motion.div
                  key={img.id}
                  layout
                  initial={{ opacity: 0, scale: 0.9 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: 0.9 }}
                  transition={{ duration: 0.4 }}
                  className={`relative overflow-hidden cursor-pointer group ${
                    img.width === 'wide' ? 'col-span-2' : ''
                  }`}
                  style={{ aspectRatio: img.width === 'wide' ? '16/9' : '4/3' }}
                  onClick={() => openLightbox(i)}
                >
                  <img
                    src={img.src}
                    alt={img.alt}
                    className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                    loading="lazy"
                  />
                  <div className="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors duration-400 flex items-center justify-center">
                    <span className="text-white text-[9px] tracking-[0.25em] uppercase opacity-0 group-hover:opacity-100 transition-opacity duration-400 font-medium">
                      View
                    </span>
                  </div>
                </motion.div>
              ))}
            </AnimatePresence>
          </motion.div>
        </div>
      </section>

      {/* Lightbox */}
      <AnimatePresence>
        {lightbox !== null && filtered[lightbox] && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/95 z-50 flex items-center justify-center"
            onClick={closeLightbox}
          >
            {/* Close */}
            <button
              onClick={closeLightbox}
              className="absolute top-6 right-6 w-10 h-10 border border-white/30 flex items-center justify-center text-white hover:border-gold hover:text-gold transition-colors duration-200 z-10"
              aria-label="Close"
            >
              <X size={18} />
            </button>

            {/* Navigation */}
            <button
              onClick={(e) => { e.stopPropagation(); prevImage(); }}
              className="absolute left-6 top-1/2 -translate-y-1/2 w-11 h-11 border border-white/30 flex items-center justify-center text-white hover:border-gold hover:text-gold transition-colors duration-200"
              aria-label="Previous"
            >
              <ChevronLeft size={20} />
            </button>
            <button
              onClick={(e) => { e.stopPropagation(); nextImage(); }}
              className="absolute right-6 top-1/2 -translate-y-1/2 w-11 h-11 border border-white/30 flex items-center justify-center text-white hover:border-gold hover:text-gold transition-colors duration-200"
              aria-label="Next"
            >
              <ChevronRight size={20} />
            </button>

            {/* Image */}
            <motion.img
              key={lightbox}
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.3 }}
              src={filtered[lightbox].src}
              alt={filtered[lightbox].alt}
              className="max-w-[90vw] max-h-[85vh] object-contain"
              onClick={(e) => e.stopPropagation()}
            />

            {/* Caption */}
            <div className="absolute bottom-6 left-1/2 -translate-x-1/2 text-center">
              <p className="text-white/70 text-xs tracking-wide">{filtered[lightbox].alt}</p>
              <p className="text-gray-600 text-[10px] mt-1">{lightbox + 1} / {filtered.length}</p>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </main>
  );
}
