export interface Offer {
  id: string;
  name: string;
  description: string;
  discount: string;
  validity: string;
  image: string;
  details: string[];
}

export const offers: Offer[] = [
  {
    id: '1',
    name: 'Weekend Escape',
    description: 'Enjoy a two-night stay with a 15% discount and complimentary late check-out.',
    discount: '15% Off',
    validity: 'Friday to Sunday stays',
    image:
      'https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=800',
    details: ['15% room rate discount', 'Late check-out until 2 PM', 'Complimentary welcome drink', 'Valid Fri–Sun stays'],
  },
  {
    id: '2',
    name: 'Family Package',
    description: 'A special package for families — extra space, complimentary extra bed and exclusive rates.',
    discount: '20% Off',
    validity: 'Any day of the week',
    image:
      'https://images.pexels.com/photos/2096983/pexels-photo-2096983.jpeg?auto=compress&cs=tinysrgb&w=800',
    details: ['20% room rate discount', 'Complimentary extra bed', 'Early check-in from 10 AM', 'Pool access included'],
  },
  {
    id: '3',
    name: 'Long Stay Discount',
    description: 'The longer you stay, the more you save. 7+ night stays receive our best available rate.',
    discount: '25% Off',
    validity: 'Minimum 7-night stay',
    image:
      'https://images.pexels.com/photos/1838554/pexels-photo-1838554.jpeg?auto=compress&cs=tinysrgb&w=800',
    details: ['25% room rate discount', 'Free airport transfer', 'Dedicated concierge service', 'Flexible check-in / out'],
  },
];
