export interface Offer {
  id: string;
  name: string;
  description: string;
  discount: string;
  validity: string;
  image: string;
  details: string[];
  bookingScope?: 'single' | 'multi' | 'both';
  minimumNights?: number;
  minimumRooms?: number;
  minimumGuests?: number;
}

// Offers are loaded from the backend. Keep this export only for legacy imports;
// never add fallback promotional content here.
export const offers: Offer[] = [];
