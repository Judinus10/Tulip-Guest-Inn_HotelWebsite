import { motion } from 'framer-motion';
import { Waves, Wifi, Car, Wind, Bath, Monitor, Leaf, Sparkles, Clock, Sun } from 'lucide-react';
import type { Facility } from '../../data/facilities';

const iconMap: Record<string, React.ComponentType<{ size?: number; className?: string }>> = {
  Waves,
  Wifi,
  Car,
  Wind,
  Bath,
  Monitor,
  Leaf,
  Sparkles,
  Clock,
  Sun,
};

interface FacilityCardProps {
  facility: Facility;
  index?: number;
}

export default function FacilityCard({ facility, index = 0 }: FacilityCardProps) {
  const Icon = iconMap[facility.icon] ?? Leaf;

  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration: 0.6, delay: index * 0.07 }}
      className="group p-8 bg-white border border-border hover:border-gold transition-all duration-400 hover:shadow-luxury text-center"
    >
      <div className="w-14 h-14 border border-gold/30 flex items-center justify-center mx-auto mb-5 group-hover:bg-gold group-hover:border-gold transition-all duration-300">
        <Icon size={22} className="text-gold group-hover:text-white transition-colors duration-300" />
      </div>
      <h3 className="font-serif text-xl font-light text-dark mb-3">{facility.name}</h3>
      <div className="w-8 h-[1px] bg-gold mx-auto mb-4" />
      <p className="text-sm text-gray-500 leading-relaxed">{facility.description}</p>
    </motion.div>
  );
}
