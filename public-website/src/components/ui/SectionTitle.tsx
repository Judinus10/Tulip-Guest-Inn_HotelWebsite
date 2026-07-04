import AnimatedSection from './AnimatedSection';

interface SectionTitleProps {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  align?: 'center' | 'left';
  light?: boolean;
}

export default function SectionTitle({
  eyebrow,
  title,
  subtitle,
  align = 'center',
  light = false,
}: SectionTitleProps) {
  const isCenter = align === 'center';

  return (
    <AnimatedSection className={`mb-14 ${isCenter ? 'text-center' : 'text-left'}`}>
      {eyebrow && (
        <p
          className={`text-[10px] tracking-[0.35em] uppercase font-medium mb-4 ${
            light ? 'text-gold' : 'text-gold'
          }`}
        >
          {eyebrow}
        </p>
      )}
      <div className={`flex items-center gap-4 mb-5 ${isCenter ? 'justify-center' : ''}`}>
        {!isCenter && <span className="w-10 h-[1px] bg-gold shrink-0" />}
        <h2
          className={`font-serif font-light leading-[1.1] ${
            light ? 'text-white' : 'text-dark'
          } text-4xl md:text-5xl`}
        >
          {title}
        </h2>
      </div>
      {isCenter && <div className="w-12 h-[1px] bg-gold mx-auto mb-5" />}
      {subtitle && (
        <p
          className={`text-sm leading-relaxed max-w-xl ${isCenter ? 'mx-auto' : ''} ${
            light ? 'text-gray-300' : 'text-gray-500'
          }`}
        >
          {subtitle}
        </p>
      )}
    </AnimatedSection>
  );
}
