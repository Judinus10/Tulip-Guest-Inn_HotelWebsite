export interface Facility {
  id: string;
  name: string;
  description: string;
  longDescription: string;
  icon: string;
  image: string;
}

export const facilities: Facility[] = [
  {
    id: '1',
    name: 'Swimming Pool',
    icon: 'Waves',
    description: 'Unwind in our serene outdoor pool surrounded by tropical gardens.',
    longDescription:
      'Our outdoor swimming pool is a peaceful oasis designed for complete relaxation. Surrounded by lush tropical greenery and comfortable sun loungers, it is the perfect place to cool off and unwind in privacy.',
    image:
      'https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '2',
    name: 'Free WiFi',
    icon: 'Wifi',
    description: 'High-speed wireless internet throughout the entire property.',
    longDescription:
      'Stay connected with complimentary high-speed WiFi available in all rooms and throughout the property. Whether you are working remotely or sharing your holiday moments, our reliable connection keeps you linked.',
    image:
      'https://images.pexels.com/photos/1181244/pexels-photo-1181244.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '3',
    name: 'Free Parking',
    icon: 'Car',
    description: 'Secure on-site parking available for all guests at no additional charge.',
    longDescription:
      'Enjoy the convenience of complimentary on-site parking. Our secure car park accommodates guest vehicles with ease, giving you complete peace of mind throughout your stay.',
    image:
      'https://images.pexels.com/photos/1004409/pexels-photo-1004409.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '4',
    name: 'Air Conditioning',
    icon: 'Wind',
    description: 'Individual climate control in every room for year-round comfort.',
    longDescription:
      'Every room is equipped with an individual air conditioning unit, ensuring you can set your ideal temperature for a perfectly comfortable night\'s sleep, whatever the season.',
    image:
      'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '5',
    name: 'Private Bathroom',
    icon: 'Bath',
    description: 'En-suite bathrooms with premium toiletries and quality fittings.',
    longDescription:
      'Each room features a private en-suite bathroom finished with quality fittings, a rain shower or bath, and a curated selection of premium toiletries for a hotel-grade bathroom experience.',
    image:
      'https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '6',
    name: 'Flat Screen TV',
    icon: 'Monitor',
    description: 'Large flat-screen televisions with satellite channels in all rooms.',
    longDescription:
      'Enjoy a wide selection of satellite channels on large flat-screen TVs in every room. Unwind with your favourite shows or explore international channels from the comfort of your bed.',
    image:
      'https://images.pexels.com/photos/1457842/pexels-photo-1457842.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '7',
    name: 'Peaceful Garden',
    icon: 'Leaf',
    description: 'Lush tropical gardens designed for quiet walks and contemplation.',
    longDescription:
      'Our beautifully maintained tropical garden is a quiet sanctuary where guests can take a morning walk, read a book in the shade or simply listen to birdsong. The gardens feature a variety of tropical plants and a shaded seating area.',
    image:
      'https://images.pexels.com/photos/1366919/pexels-photo-1366919.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '8',
    name: 'Daily Housekeeping',
    icon: 'Sparkles',
    description: 'Attentive daily housekeeping service to keep your room immaculate.',
    longDescription:
      'Our dedicated housekeeping team ensures your room is kept immaculate throughout your stay. Daily servicing, fresh linens and careful attention to detail reflect our commitment to genuine hospitality.',
    image:
      'https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '9',
    name: '24-Hour Reception',
    icon: 'Clock',
    description: 'Our friendly team is available around the clock to assist you.',
    longDescription:
      'Our welcoming reception team is available 24 hours a day, 7 days a week. Whether you need local recommendations, transport arrangements or any assistance, we are always on hand to help.',
    image:
      'https://images.pexels.com/photos/2096983/pexels-photo-2096983.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '10',
    name: 'Outdoor Seating',
    icon: 'Sun',
    description: 'Shaded outdoor seating areas overlooking the garden and pool.',
    longDescription:
      'Enjoy the tropical air from our thoughtfully designed outdoor seating areas. Whether you prefer a shaded terrace or a poolside lounger, our outdoor spaces invite you to slow down and breathe.',
    image:
      'https://images.pexels.com/photos/2029722/pexels-photo-2029722.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
];
