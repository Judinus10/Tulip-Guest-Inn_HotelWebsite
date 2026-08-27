import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ChevronLeft, ChevronRight, Quote } from 'lucide-react';
import { testimonials } from '../../data/testimonials';

function RatingStars({ rating }: { rating: number }) {
  return <div className="flex items-center justify-center gap-1 mb-6" aria-label={`${rating} out of 5 stars`} data-no-translate>
    {Array.from({ length: 5 }).map((_, index) => {
      const fill = Math.max(0, Math.min(1, rating - index));
      return <span key={index} className="relative block h-4 w-4">
        <svg className="absolute inset-0 h-4 w-4 text-gold/35" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.4"><path d="M10 1.8l2.35 4.76 5.25.76-3.8 3.7.9 5.23L10 13.78l-4.7 2.47.9-5.23-3.8-3.7 5.25-.76L10 1.8z" /></svg>
        {fill > 0 && <span className="absolute inset-y-0 left-0 overflow-hidden" style={{ width: `${fill * 100}%` }}><svg className="h-4 w-4 max-w-none text-gold fill-current" viewBox="0 0 20 20"><path d="M10 1.8l2.35 4.76 5.25.76-3.8 3.7.9 5.23L10 13.78l-4.7 2.47.9-5.23-3.8-3.7 5.25-.76L10 1.8z" /></svg></span>}
      </span>;
    })}
  </div>;
}

export default function TestimonialSlider() {
  const [current, setCurrent] = useState(0);
  const [direction, setDirection] = useState(1);

  useEffect(() => {
    const interval = setInterval(() => {
      setDirection(1);
      setCurrent((prev) => (prev + 1) % testimonials.length);
    }, 6000);
    return () => clearInterval(interval);
  }, []);

  const goTo = (index: number) => {
    setDirection(index > current ? 1 : -1);
    setCurrent(index);
  };

  const prev = () => {
    setDirection(-1);
    setCurrent((c) => (c - 1 + testimonials.length) % testimonials.length);
  };

  const next = () => {
    setDirection(1);
    setCurrent((c) => (c + 1) % testimonials.length);
  };

  const testimonial = testimonials[current];

  return (
    <div className="relative max-w-3xl mx-auto px-12">
      {/* Nav Arrows */}
      <button
        onClick={prev}
        className="absolute left-0 top-1/2 -translate-y-1/2 w-10 h-10 border border-border flex items-center justify-center text-gray-400 hover:border-gold hover:text-gold transition-all duration-300"
        aria-label="Previous testimonial"
      >
        <ChevronLeft size={18} />
      </button>
      <button
        onClick={next}
        className="absolute right-0 top-1/2 -translate-y-1/2 w-10 h-10 border border-border flex items-center justify-center text-gray-400 hover:border-gold hover:text-gold transition-all duration-300"
        aria-label="Next testimonial"
      >
        <ChevronRight size={18} />
      </button>

      {/* Testimonial */}
      <div className="text-center overflow-hidden">
        <Quote size={36} className="text-gold/30 mx-auto mb-6" />
        <AnimatePresence mode="wait" custom={direction}>
          <motion.div
            key={current}
            custom={direction}
            initial={{ opacity: 0, x: direction * 40 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -direction * 40 }}
            transition={{ duration: 0.5, ease: 'easeInOut' }}
          >
            {/* Stars */}
            <RatingStars rating={testimonial.rating} />

            <p className="font-serif text-xl lg:text-2xl text-dark font-light leading-relaxed mb-8 italic">
              "{testimonial.quote}"
            </p>

            <div className="flex items-center justify-center gap-4">
              <div
                className="w-12 h-12 rounded-full bg-deep-green text-white border-2 border-gold/30 flex items-center justify-center text-sm font-medium"
                aria-hidden="true"
              >
                {testimonial.name
                  .split(' ')
                  .map((part) => part[0])
                  .join('')
                  .slice(0, 2)}
              </div>
              <div className="text-left">
                <p className="font-medium text-dark text-sm">{testimonial.name}</p>
                <p className="text-xs text-gray-400 tracking-wide">{testimonial.country}</p>
              </div>
            </div>
          </motion.div>
        </AnimatePresence>
      </div>

      {/* Dots */}
      <div className="flex items-center justify-center gap-2 mt-10">
        {testimonials.map((_, i) => (
          <button
            key={i}
            onClick={() => goTo(i)}
            className={`transition-all duration-300 ${
              i === current ? 'w-6 h-1.5 bg-gold' : 'w-1.5 h-1.5 rounded-full bg-gray-300 hover:bg-gold'
            }`}
            aria-label={`Go to testimonial ${i + 1}`}
          />
        ))}
      </div>
    </div>
  );
}
