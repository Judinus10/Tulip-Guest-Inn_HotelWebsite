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
    name: 'Free WiFi',
    icon: 'Wifi',
    description: 'High-speed wireless internet throughout the entire property.',
    longDescription:
      'High-speed wireless internet is available throughout the entire property at no extra charge.',
    image:
      'https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '2',
    name: 'Free Parking',
    icon: 'Car',
    description: 'Secure on-site parking, available to all guests at no extra charge.',
    longDescription:
      'Secure on-site parking is available to all guests at no extra charge.',
    image:
      'https://images.pexels.com/photos/1181244/pexels-photo-1181244.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '3',
    name: 'Air Conditioning',
    icon: 'Wind',
    description: 'Individual climate control in every room for year-round comfort.',
    longDescription:
      'Individual climate control is provided in every room for year-round comfort.',
    image:
      'https://images.pexels.com/photos/1004409/pexels-photo-1004409.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '4',
    name: 'Peaceful Garden',
    icon: 'Leaf',
    description: 'A spacious garden shaded by tall coconut trees, with seating for quiet moments.',
    longDescription:
      'A spacious garden shaded by tall coconut trees offers comfortable seating for quiet moments.',
    image:
      'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '5',
    name: 'Clean, Fresh Family Rooms',
    icon: 'BedDouble',
    description: 'Spotless family rooms for up to three guests, each with an en suite bathroom.',
    longDescription:
      'Comfortable family rooms sleep up to three guests and each includes an en suite bathroom. Every room is prepared spotless on arrival, most include a flat screen TV, and housekeeping is available during your stay on request.',
    image:
      'https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '6',
    name: '24 Hour Host',
    icon: 'Clock',
    description: 'Our host is on hand around the clock to help, day or night.',
    longDescription:
      'Our host is on hand around the clock to help with anything you need, day or night.',
    image:
      'https://images.pexels.com/photos/1457842/pexels-photo-1457842.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '7',
    name: 'Bicycle Rental',
    icon: 'Bike',
    description: 'Explore Point Pedro, the beach and the lighthouse at your own pace.',
    longDescription:
      'Explore Point Pedro, the beach and the lighthouse at your own pace with a rental bicycle.',
    image:
      'https://images.pexels.com/photos/1366919/pexels-photo-1366919.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '8',
    name: 'Tuktuk & Transfers',
    icon: 'Car',
    description: 'Tuktuk, driver and airport transfer arrangements whenever you need them.',
    longDescription:
      'We are happy to arrange a tuktuk, a driver or an airport transfer whenever you need one.',
    image:
      'https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '9',
    name: 'Shared Kitchen',
    icon: 'CookingPot',
    description: 'A shared kitchen is available for guests who prefer to prepare their own meals.',
    longDescription:
      'A shared kitchen is available for guests who prefer to prepare their own meals.',
    image:
      'https://images.pexels.com/photos/2096983/pexels-photo-2096983.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
  {
    id: '10',
    name: 'Laundry & Ironing',
    icon: 'Sparkles',
    description: 'Laundry service on request, with an iron available whenever you need one.',
    longDescription:
      'Laundry service is available on request for a small charge, with an iron available whenever you need one.',
    image:
      'https://images.pexels.com/photos/2029722/pexels-photo-2029722.jpeg?auto=compress&cs=tinysrgb&w=1200',
  },
];
