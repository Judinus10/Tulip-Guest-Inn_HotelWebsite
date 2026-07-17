import { useEffect, useMemo, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, ChevronLeft, ChevronRight } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import { getPublicGallery, type PublicGalleryFolder } from '../services/publicApi';
import bannerGallery from '../assets/images/banners/banner-gallery.jpg';

type GalleryItem = {
  id: string;
  src: string;
  alt: string;
  category: string;
  width: 'normal' | 'wide';
};

export default function Gallery() {
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
          setError(err instanceof Error ? err.message : 'Failed to load gallery.');
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
  }, []);

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

  const openLightbox = (index: number) => setLightbox(index);
  const closeLightbox = () => setLightbox(null);

  const prevImage = () => {
    setLightbox((i) => (i !== null && filtered.length ? (i - 1 + filtered.length) % filtered.length : null));
  };

  const nextImage = () => {
    setLightbox((i) => (i !== null && filtered.length ? (i + 1) % filtered.length : null));
  };

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

            <span className="ml-auto text-xs text-gray-400 pr-4 hidden sm:block">
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
