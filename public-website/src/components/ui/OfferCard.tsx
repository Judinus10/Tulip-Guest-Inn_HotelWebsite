import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import type { Offer } from '../../data/offers';
import fallbackOfferImage from '../../assets/images/home/home-luxury-experience.jpg';

interface OfferCardProps {
  offer: Offer;
  index?: number;
}

export default function OfferCard({ offer, index = 0 }: OfferCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration: 0.6, delay: index * 0.1 }}
      className="group relative overflow-hidden bg-white border border-border shadow-luxury hover:shadow-luxury-lg transition-shadow duration-500"
    >
      {/* Image */}
      <div className="relative h-52 overflow-hidden">
        <img
          src={offer.image || fallbackOfferImage}
          alt={offer.name}
          className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
          loading="lazy"
          onError={(event) => {
            if (event.currentTarget.src !== fallbackOfferImage) {
              event.currentTarget.src = fallbackOfferImage;
            }
          }}
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
        <div className="absolute top-4 right-4 bg-gold text-white text-xs font-medium px-3 py-1.5 tracking-wide">
          {offer.discount}
        </div>
      </div>

      {/* Content */}
      <div className="p-7">
        <p className="text-[9px] tracking-[0.25em] uppercase text-gold font-medium mb-2">
          {offer.validity}
        </p>
        <h3 className="font-serif text-2xl font-light text-dark mb-2">{offer.name}</h3>
        <div className="w-8 h-[1px] bg-gold mb-4" />
        <p className="text-sm text-gray-500 leading-relaxed mb-5">{offer.description}</p>

        <ul className="space-y-1.5 mb-6">
          {offer.details.map((detail) => (
            <li key={detail} className="flex items-center gap-2 text-xs text-gray-500">
              <span className="w-1 h-1 rounded-full bg-gold shrink-0" />
              {detail}
            </li>
          ))}
        </ul>

        <Link
          to={offer.bookingScope === 'multi' ? '/multi-room-booking' : '/rooms'}
          className="btn-outline w-full justify-center text-[9px] py-2.5"
        >
          Claim Offer
        </Link>
      </div>
    </motion.div>
  );
}
