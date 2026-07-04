export type GalleryCategory = 'all' | 'rooms' | 'pool' | 'garden' | 'exterior' | 'facilities';

export interface GalleryImage {
  id: string;
  src: string;
  alt: string;
  category: GalleryCategory;
  width: 'normal' | 'wide';
}

export const galleryImages: GalleryImage[] = [
  {
    id: '1',
    src: 'https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Outdoor Swimming Pool',
    category: 'pool',
    width: 'wide',
  },
  {
    id: '2',
    src: 'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Standard Room Interior',
    category: 'rooms',
    width: 'normal',
  },
  {
    id: '3',
    src: 'https://images.pexels.com/photos/1457842/pexels-photo-1457842.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Deluxe Room',
    category: 'rooms',
    width: 'normal',
  },
  {
    id: '4',
    src: 'https://images.pexels.com/photos/1366919/pexels-photo-1366919.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Tropical Garden',
    category: 'garden',
    width: 'wide',
  },
  {
    id: '5',
    src: 'https://images.pexels.com/photos/2096983/pexels-photo-2096983.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Family Room',
    category: 'rooms',
    width: 'normal',
  },
  {
    id: '6',
    src: 'https://images.pexels.com/photos/1838554/pexels-photo-1838554.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Garden Suite',
    category: 'rooms',
    width: 'normal',
  },
  {
    id: '7',
    src: 'https://images.pexels.com/photos/2029722/pexels-photo-2029722.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Outdoor Terrace',
    category: 'exterior',
    width: 'wide',
  },
  {
    id: '8',
    src: 'https://images.pexels.com/photos/237371/pexels-photo-237371.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Hotel Exterior',
    category: 'exterior',
    width: 'normal',
  },
  {
    id: '9',
    src: 'https://images.pexels.com/photos/189296/pexels-photo-189296.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Pool Terrace',
    category: 'pool',
    width: 'normal',
  },
  {
    id: '10',
    src: 'https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Bathroom Facilities',
    category: 'facilities',
    width: 'normal',
  },
  {
    id: '11',
    src: 'https://images.pexels.com/photos/164595/pexels-photo-164595.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Bedroom Detail',
    category: 'rooms',
    width: 'normal',
  },
  {
    id: '12',
    src: 'https://images.pexels.com/photos/258154/pexels-photo-258154.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Garden Pathway',
    category: 'garden',
    width: 'wide',
  },
  {
    id: '13',
    src: 'https://images.pexels.com/photos/1743229/pexels-photo-1743229.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Room Detail',
    category: 'rooms',
    width: 'normal',
  },
  {
    id: '14',
    src: 'https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Guest Room',
    category: 'rooms',
    width: 'normal',
  },
  {
    id: '15',
    src: 'https://images.pexels.com/photos/1743231/pexels-photo-1743231.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Pool at Dusk',
    category: 'pool',
    width: 'wide',
  },
  {
    id: '16',
    src: 'https://images.pexels.com/photos/338504/pexels-photo-338504.jpeg?auto=compress&cs=tinysrgb&w=1200',
    alt: 'Garden Seating',
    category: 'garden',
    width: 'normal',
  },
];
