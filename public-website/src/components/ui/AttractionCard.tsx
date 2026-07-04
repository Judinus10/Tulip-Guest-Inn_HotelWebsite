import { motion } from 'framer-motion';
import { MapPin, Clock } from 'lucide-react';
import type { Attraction } from '../../data/attractions';

interface AttractionCardProps {
  attraction: Attraction;
  index?: number;
}

export default function AttractionCard({ attraction, index = 0 }: AttractionCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration: 0.6, delay: index * 0.1 }}
      className="group bg-white border border-border shadow-luxury hover:shadow-luxury-lg transition-all duration-500 overflow-hidden"
    >
      {/* Image */}
      <div className="relative h-52 overflow-hidden">
        <img
          src={attraction.image}
          alt={attraction.name}
          className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
          loading="lazy"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
        <div className="absolute bottom-4 left-4">
          <span className="bg-gold text-white text-[9px] tracking-[0.18em] uppercase px-3 py-1.5">
            {attraction.category}
          </span>
        </div>
      </div>

      {/* Content */}
      <div className="p-7">
        <h3 className="font-serif text-xl font-light text-dark mb-2">{attraction.name}</h3>
        <div className="w-8 h-[1px] bg-gold mb-4" />
        <p className="text-sm text-gray-500 leading-relaxed mb-5">{attraction.description}</p>
        <div className="flex items-center gap-5 pt-4 border-t border-border">
          <div className="flex items-center gap-1.5 text-gray-400">
            <MapPin size={13} className="text-gold" />
            <span className="text-xs">{attraction.distance}</span>
          </div>
          <div className="flex items-center gap-1.5 text-gray-400">
            <Clock size={13} className="text-gold" />
            <span className="text-xs">{attraction.duration}</span>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
