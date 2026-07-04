import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import AnimatedSection from './AnimatedSection';

interface CTASectionProps {
  image?: string;
  eyebrow?: string;
  title: string;
  subtitle?: string;
  btnLabel?: string;
  btnPath?: string;
  secondBtnLabel?: string;
  secondBtnPath?: string;
}

export default function CTASection({
  image = 'https://images.pexels.com/photos/338504/pexels-photo-338504.jpeg?auto=compress&cs=tinysrgb&w=1600',
  eyebrow = 'Reserve Your Room',
  title,
  subtitle,
  btnLabel = 'Book Now',
  btnPath = '/booking',
  secondBtnLabel,
  secondBtnPath,
}: CTASectionProps) {
  return (
    <section className="relative overflow-hidden">
      <motion.div
        initial={{ scale: 1.08 }}
        whileInView={{ scale: 1 }}
        viewport={{ once: true }}
        transition={{ duration: 1.4, ease: 'easeOut' }}
        className="absolute inset-0"
      >
        <img src={image} alt="CTA" className="w-full h-full object-cover" loading="lazy" />
        <div className="absolute inset-0 bg-black/65" />
      </motion.div>

      <div className="relative z-10 text-center py-28 px-4">
        <AnimatedSection>
          {eyebrow && (
            <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-5">
              {eyebrow}
            </p>
          )}
          <h2 className="font-serif text-4xl md:text-5xl lg:text-6xl text-white font-light mb-6 leading-tight">
            {title}
          </h2>
          <div className="w-14 h-[1px] bg-gold mx-auto mb-6" />
          {subtitle && (
            <p className="text-white/70 text-sm max-w-lg mx-auto leading-relaxed mb-10">
              {subtitle}
            </p>
          )}
          <div className="flex flex-wrap items-center justify-center gap-4">
            <Link to={btnPath} className="btn-primary">
              {btnLabel}
            </Link>
            {secondBtnLabel && secondBtnPath && (
              <Link to={secondBtnPath} className="btn-outline border-white text-white hover:bg-white hover:text-dark">
                {secondBtnLabel}
              </Link>
            )}
          </div>
        </AnimatedSection>
      </div>
    </section>
  );
}
