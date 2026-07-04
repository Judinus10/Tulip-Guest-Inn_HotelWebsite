export interface Room {
  id: string;
  name: string;
  slug: string;
  category: 'standard' | 'deluxe' | 'family' | 'suite';
  description: string;
  longDescription: string;
  price: number;
  currency?: string;
  guests: number;
  beds: string;
  bathrooms: number;
  size: number;
  image: string;
  images: string[];
  amenities: string[];
  featured: boolean;
}

export const rooms: Room[] = [
  {
    id: '1',
    slug: 'standard-room',
    name: 'Standard Room',
    category: 'standard',
    description: 'A thoughtfully appointed room with clean lines, natural light and every essential comfort.',
    longDescription:
      'Our Standard Room offers a serene retreat with carefully curated furnishings, crisp white linens and warm timber accents. Enjoy garden or courtyard views from your private space with all the comforts you need for a restful stay.',
    price: 80,
    guests: 2,
    beds: 'King Bed',
    bathrooms: 1,
    size: 22,
    image:
      'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg?auto=compress&cs=tinysrgb&w=1200',
    images: [
      'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg?auto=compress&cs=tinysrgb&w=1200',
      'https://images.pexels.com/photos/164595/pexels-photo-164595.jpeg?auto=compress&cs=tinysrgb&w=1200',
      'https://images.pexels.com/photos/1743229/pexels-photo-1743229.jpeg?auto=compress&cs=tinysrgb&w=1200',
    ],
    amenities: [
      'Air Conditioning',
      'Free WiFi',
      'Private Bathroom',
      'Flat Screen TV',
      'Daily Housekeeping',
      'Tea & Coffee',
      'Safe',
      'Wardrobe',
    ],
    featured: true,
  },
  {
    id: '2',
    slug: 'deluxe-room',
    name: 'Deluxe Room',
    category: 'deluxe',
    description: 'Spacious elegance with upgraded finishes, premium linens and a touch of boutique luxury.',
    longDescription:
      "The Deluxe Room elevates your stay with a generous layout, premium furnishings and carefully chosen artworks that reflect Northern Sri Lanka's heritage. A private balcony invites you to take in the garden views with your morning tea.",
    price: 120,
    guests: 2,
    beds: 'King Bed',
    bathrooms: 1,
    size: 32,
    image:
      'https://images.pexels.com/photos/1457842/pexels-photo-1457842.jpeg?auto=compress&cs=tinysrgb&w=1200',
    images: [
      'https://images.pexels.com/photos/1457842/pexels-photo-1457842.jpeg?auto=compress&cs=tinysrgb&w=1200',
      'https://images.pexels.com/photos/237371/pexels-photo-237371.jpeg?auto=compress&cs=tinysrgb&w=1200',
      'https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1200',
    ],
    amenities: [
      'Air Conditioning',
      'Free WiFi',
      'Private Bathroom',
      'Flat Screen TV',
      'Daily Housekeeping',
      'Tea & Coffee',
      'Safe',
      'Wardrobe',
      'Private Balcony',
      'Premium Toiletries',
      'Minibar',
    ],
    featured: true,
  },
  {
    id: '3',
    slug: 'family-room',
    name: 'Family Room',
    category: 'family',
    description: 'Designed for families — generous space, two beds and a warm, welcoming atmosphere.',
    longDescription:
      'Our Family Room is thoughtfully designed for families travelling together. Two comfortable beds, a large open-plan space and a private bathroom ensure everyone has room to relax. Located near the pool, it is perfect for a family holiday.',
    price: 160,
    guests: 4,
    beds: 'King + Twin Beds',
    bathrooms: 1,
    size: 45,
    image:
      'https://images.pexels.com/photos/2096983/pexels-photo-2096983.jpeg?auto=compress&cs=tinysrgb&w=1200',
    images: [
      'https://images.pexels.com/photos/2096983/pexels-photo-2096983.jpeg?auto=compress&cs=tinysrgb&w=1200',
      'https://images.pexels.com/photos/1743231/pexels-photo-1743231.jpeg?auto=compress&cs=tinysrgb&w=1200',
      'https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg?auto=compress&cs=tinysrgb&w=1200',
    ],
    amenities: [
      'Air Conditioning',
      'Free WiFi',
      'Private Bathroom',
      'Flat Screen TV',
      'Daily Housekeeping',
      'Tea & Coffee',
      'Safe',
      'Wardrobe',
      'Extra Beds Available',
      'Garden View',
      'Interconnecting Option',
    ],
    featured: true,
  },
  {
    id: '4',
    slug: 'garden-suite',
    name: 'Garden Suite',
    category: 'suite',
    description: 'Our finest accommodation — a private sanctuary with garden terrace and premium furnishings.',
    longDescription:
      'The Garden Suite is the pinnacle of our boutique experience. A generous private terrace opens to lush tropical gardens, while the suite interior combines contemporary design with warm Sri Lankan textures. Perfect for honeymooners and discerning travellers.',
    price: 220,
    guests: 2,
    beds: 'King Bed',
    bathrooms: 2,
    size: 65,
    image:
      'https://images.pexels.com/photos/1838554/pexels-photo-1838554.jpeg?auto=compress&cs=tinysrgb&w=1200',
    images: [
      'https://images.pexels.com/photos/1838554/pexels-photo-1838554.jpeg?auto=compress&cs=tinysrgb&w=1200',
      'https://images.pexels.com/photos/189296/pexels-photo-189296.jpeg?auto=compress&cs=tinysrgb&w=1200',
      'https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1200',
    ],
    amenities: [
      'Air Conditioning',
      'Free WiFi',
      'Two Bathrooms',
      'Flat Screen TV',
      'Daily Housekeeping',
      'Tea & Coffee',
      'In-Room Safe',
      'Walk-in Wardrobe',
      'Private Garden Terrace',
      'Premium Toiletries',
      'Minibar',
      'Turndown Service',
      'Pool Access',
    ],
    featured: false,
  },
];
