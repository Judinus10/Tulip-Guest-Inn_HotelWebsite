import { useEffect, useRef, useState } from 'react';
import { useInView } from 'framer-motion';
import type { Statistic } from '../../data/statistics';

interface StatCounterProps {
  stat: Statistic;
}

export default function StatCounter({ stat }: StatCounterProps) {
  const ref = useRef(null);
  const isInView = useInView(ref, { once: true, margin: '-80px' });
  const [count, setCount] = useState(0);

  useEffect(() => {
    if (!isInView) return;

    const duration = 2000;
    const start = Date.now();
    const target = stat.value;

    const tick = () => {
      const elapsed = Date.now() - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = target * eased;
      setCount(target < 10 ? Math.round(current * 10) / 10 : Math.floor(current));

      if (progress < 1) requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
  }, [isInView, stat.value]);

  const display = stat.value < 10 ? count.toFixed(1) : count.toLocaleString();

  return (
    <div ref={ref} className="text-center">
      <p className="font-serif text-5xl lg:text-6xl font-light text-white mb-2">
        {display}
        <span className="text-gold">{stat.suffix}</span>
      </p>
      <div className="w-8 h-[1px] bg-gold/40 mx-auto mb-3" />
      <p className="text-xs tracking-[0.2em] uppercase text-gray-400 font-medium">{stat.label}</p>
    </div>
  );
}
