import { useEffect, useMemo, useRef, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, ChevronLeft, ChevronRight } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import { getPublicGallery, type PublicGalleryFolder } from '../services/publicApi';
import bannerGallery from '../assets/images/banners/banner-gallery.jpg';
import { useToast } from '../components/ui/ToastProvider';

type GalleryItem = {
  id: string;
  src: string;
  alt: string;
  category: string;
  width: 'normal' | 'wide';
};

export default function Gallery() {
  const toast = useToast();
  const touchStartX = useRef<number | null>(null);
  const [active, setActive] = useState('all');
  const [lightbox, setLightbox] = useState<number | null>(null);
  const [folders, setFolders] = useState<PublicGalleryFolder[]>([]);
  const [images, setImages] = useState<GalleryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let mounted = true;

    async function loadGallery() {
      try {
        setLoading(true);
        setError('');

        const data = await getPublicGallery();
        const apiFolders = data.folders;
        const apiImages = data.images;

        const normalizedImages: GalleryItem[] = apiImages
          .filter((img) => img.image_path)
          .map((img, index) => ({
            id: String(img.id),
            src: img.image_path,
            alt: img.title || img.folder_name || 'Tulip Guest Inn Gallery',
            category: img.folder_slug || String(img.folder_id || 'gallery'),
            width: index % 5 === 0 ? 'wide' : 'normal',
          }));

        if (mounted) {
          setFolders(apiFolders);
          setImages(normalizedImages);
        }
      } catch (err) {
        if (mounted) {
          const message = err instanceof Error ? err.message : 'Failed to load gallery.';
          setError(message);
          toast.error(message);
          setImages([]);
          setFolders([]);
        }
      } finally {
        if (mounted) setLoading(false);
      }
    }

    loadGallery();

    return () => {
      mounted = false;
    };
  }, [toast]);

  const categories = useMemo(() => {
    const dbFolders = folders
      .filter((folder) => folder.slug && folder.name)
      .map((folder) => ({
        value: folder.slug,
        label: folder.name,
      }));

    return [{ value: 'all', label: 'All' }, ...dbFolders];
  }, [folders]);

  const filtered = active === 'all' ? images : images.filter((img) => img.category === active);
  const galleryGroups = useMemo(() => {
    const groups: Array<Array<{ image: GalleryItem; index: number }>> = [];
    filtered.forEach((image, index) => {
      const groupIndex = Math.floor(index / 5);
      if (!groups[groupIndex]) groups[groupIndex] = [];
      groups[groupIndex].push({ image, index });
    });
    return groups;
  }, [filtered]);

  const openLightbox = (index: number) => setLightbox(index);
  const closeLightbox = () => setLightbox(null);

  const prevImage = () => {
    setLightbox((i) => (i !== null && filtered.length ? (i - 1 + filtered.length) % filtered.length : null));
  };

  const nextImage = () => {
    setLightbox((i) => (i !== null && filtered.length ? (i + 1) % filtered.length : null));
  };

  useEffect(() => {
    if (lightbox === null) return;
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === 'ArrowLeft') prevImage();
      if (event.key === 'ArrowRight') nextImage();
      if (event.key === 'Escape') closeLightbox();
    };
    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, [lightbox, filtered.length]);

  return (
    <main>
      <PageHero
        title="Gallery"
        subtitle="A visual journey through Tulip Guest Inn — our rooms, gardens, pool and surroundings."
        image={bannerGallery}
        breadcrumb="Photo Gallery"
      />

      <section className="bg-white border-b border-border sticky top-20 z-30">
        <div className="container-custom">
          <div className="flex flex-wrap items-center gap-0">
            {categories.map((cat) => (
              <button
                key={cat.value}
                onClick={() => {
                  setActive(cat.value);
                  setLightbox(null);
                }}
                className={`px-7 py-5 text-[10px] tracking-[0.2em] uppercase font-medium transition-all duration-300 border-b-2 ${
                  active === cat.value
                    ? 'border-gold text-gold'
                    : 'border-transparent text-gray-400 hover:text-dark hover:border-border'
                }`}
              >
                {cat.label}
              </button>
            ))}

            <span data-no-translate className="ml-auto text-xs text-gray-400 pr-4 hidden sm:block">
              {filtered.length} {filtered.length === 1 ? 'image' : 'images'}
            </span>
          </div>
        </div>
      </section>

      <section className="section-padding bg-background">
        <div className="container-custom">
          {loading && (
            <div className="text-center py-20 text-gray-500 text-sm tracking-wide">
              Loading gallery...
            </div>
          )}

          {!loading && error && (
            <div className="text-center py-20">
              <p className="text-red-500 text-sm">{error}</p>
              <p className="text-gray-400 text-xs mt-2">
                Check API URL, database connection, and gallery table data.
              </p>
            </div>
          )}

          {!loading && !error && filtered.length === 0 && (
            <div className="text-center py-20">
              <p className="text-gray-500 text-sm">No gallery images found.</p>
              <p className="text-gray-400 text-xs mt-2">
                Add active folders and active images from the admin gallery.
              </p>
            </div>
          )}

          {!loading && !error && filtered.length > 0 && (
            <motion.div layout className="space-y-3">
              <AnimatePresence mode="popLayout">
                {galleryGroups.map((group, groupIndex) => {
                  const big = group[0];
                  const small = group.slice(1);
                  const bigOnLeft = groupIndex % 2 === 1;
                  return <motion.div
                    key={group.map((item) => item.image.id).join('-')}
                    layout
                    initial={{ opacity: 0, scale: 0.9 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0, scale: 0.9 }}
                    transition={{ duration: 0.4 }}
                    className="grid grid-cols-1 gap-3 md:grid-cols-2"
                  >
                    {big && <button type="button" onClick={() => openLightbox(big.index)} className={`group relative aspect-square overflow-hidden ${bigOnLeft ? 'md:order-1' : 'md:order-2'}`}>
                      <img src={big.image.src} alt={big.image.alt} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy" />
                      <span className="absolute inset-0 flex items-center justify-center bg-black/0 text-[9px] font-medium uppercase tracking-[0.25em] text-white opacity-0 transition-all duration-300 group-hover:bg-black/35 group-hover:opacity-100">View</span>
                    </button>}
                    <div className={`grid aspect-square grid-cols-2 grid-rows-2 gap-3 ${bigOnLeft ? 'md:order-2' : 'md:order-1'}`}>
                      {small.map(({ image, index }) => <button type="button" key={image.id} onClick={() => openLightbox(index)} className="group relative min-h-0 overflow-hidden">
                        <img src={image.src} alt={image.alt} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" loading="lazy" />
                        <span className="absolute inset-0 flex items-center justify-center bg-black/0 text-[9px] font-medium uppercase tracking-[0.25em] text-white opacity-0 transition-all duration-300 group-hover:bg-black/35 group-hover:opacity-100">View</span>
                      </button>)}
                    </div>
                  </motion.div>;
                })}
              </AnimatePresence>
            </motion.div>
          )}
        </div>
      </section>

      <AnimatePresence>
        {lightbox !== null && filtered[lightbox] && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/95 z-50 flex items-center justify-center"
            onClick={closeLightbox}
            onTouchStart={(event) => { touchStartX.current = event.touches[0]?.clientX ?? null; }}
            onTouchEnd={(event) => {
              if (touchStartX.current === null) return;
              const distance = (event.changedTouches[0]?.clientX ?? touchStartX.current) - touchStartX.current;
              touchStartX.current = null;
              if (Math.abs(distance) < 45) return;
              if (distance > 0) prevImage(); else nextImage();
            }}
          >
            <button
              onClick={closeLightbox}
              className="absolute top-6 right-6 w-10 h-10 border border-white/30 flex items-center justify-center text-white hover:border-gold hover:text-gold transition-colors duration-200 z-10"
              aria-label="Close"
            >
              <X size={18} />
            </button>

            <button
              onClick={(e) => {
                e.stopPropagation();
                prevImage();
              }}
              className="absolute left-6 top-1/2 -translate-y-1/2 w-11 h-11 border border-white/30 flex items-center justify-center text-white hover:border-gold hover:text-gold transition-colors duration-200"
              aria-label="Previous"
            >
              <ChevronLeft size={20} />
            </button>

            <button
              onClick={(e) => {
                e.stopPropagation();
                nextImage();
              }}
              className="absolute right-6 top-1/2 -translate-y-1/2 w-11 h-11 border border-white/30 flex items-center justify-center text-white hover:border-gold hover:text-gold transition-colors duration-200"
              aria-label="Next"
            >
              <ChevronRight size={20} />
            </button>

            <motion.img
              key={filtered[lightbox].id}
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.3 }}
              src={filtered[lightbox].src}
              alt={filtered[lightbox].alt}
              className="max-w-[90vw] max-h-[85vh] object-contain"
              onClick={(e) => e.stopPropagation()}
            />

            <div className="absolute bottom-6 left-1/2 -translate-x-1/2 text-center">
              <p className="text-white/70 text-xs tracking-wide">{filtered[lightbox].alt}</p>
              <p className="text-gray-600 text-[10px] mt-1">
                {lightbox + 1} / {filtered.length}
              </p>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </main>
  );
}
