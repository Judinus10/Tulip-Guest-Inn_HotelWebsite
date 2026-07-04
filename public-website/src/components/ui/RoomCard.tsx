import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Users, BedDouble, Maximize } from 'lucide-react';
import type { Room } from '../../data/rooms';

interface RoomCardProps {
  room: Room;
  index?: number;
}

export default function RoomCard({ room, index = 0 }: RoomCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration: 0.6, delay: index * 0.1, ease: [0.25, 0.4, 0.25, 1] }}
      className="group bg-white border border-border shadow-luxury hover:shadow-luxury-lg transition-shadow duration-500"
    >
      {/* Image */}
      <div className="relative h-64 overflow-hidden">
        <img
          src={room.image}
          alt={room.name}
          className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
          loading="lazy"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
        <div className="absolute top-4 left-4">
          <span className="bg-gold text-white text-[9px] tracking-[0.18em] uppercase px-3 py-1.5">
            {room.category}
          </span>
        </div>
        <div className="absolute top-4 right-4">
          <span className="bg-white/95 text-dark text-xs font-medium px-3 py-1.5">
            From {room.currency ? `${room.currency} ` : '$'}{room.price}<span className="text-gray-400 text-[10px]">/night</span>
          </span>
        </div>
      </div>

      {/* Content */}
      <div className="p-7">
        <h3 className="font-serif text-2xl text-dark font-light mb-2">{room.name}</h3>
        <div className="w-8 h-[1px] bg-gold mb-4" />
        <p className="text-sm text-gray-500 leading-relaxed mb-6">{room.description}</p>

        {/* Room Details */}
        <div className="flex items-center gap-6 mb-7 pt-4 border-t border-border">
          <div className="flex items-center gap-1.5 text-gray-500">
            <Users size={14} className="text-gold" />
            <span className="text-xs">{room.guests} Guests</span>
          </div>
          <div className="flex items-center gap-1.5 text-gray-500">
            <BedDouble size={14} className="text-gold" />
            <span className="text-xs">{room.beds}</span>
          </div>
          <div className="flex items-center gap-1.5 text-gray-500">
            <Maximize size={14} className="text-gold" />
            <span className="text-xs">{room.size} m²</span>
          </div>
        </div>

        {/* Buttons */}
        <div className="flex gap-3">
          <Link
            to={`/rooms/${room.slug}`}
            className="btn-outline flex-1 justify-center text-[9px] py-2.5 px-4"
          >
            View Room
          </Link>
          <Link
            to={`/booking?room=${room.slug}`}
            className="btn-primary flex-1 justify-center text-[9px] py-2.5 px-4"
          >
            Book Now
          </Link>
        </div>
      </div>
    </motion.div>
  );
}
