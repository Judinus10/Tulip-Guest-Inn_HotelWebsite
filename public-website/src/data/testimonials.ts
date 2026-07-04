export interface Testimonial {
  id: string;
  name: string;
  country: string;
  rating: number;
  quote: string;
  avatar: string;
}

export const testimonials: Testimonial[] = [
  {
    id: '1',
    name: 'James Hartley',
    country: 'United Kingdom',
    rating: 5,
    quote:
      'An absolutely beautiful stay. The rooms were immaculate, the pool area is stunning and the staff made us feel genuinely welcomed. The garden is lush and peaceful — perfect for morning walks.',
    avatar:
      'https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg?auto=compress&cs=tinysrgb&w=200',
  },
  {
    id: '2',
    name: 'Priya Nair',
    country: 'India',
    rating: 5,
    quote:
      'Tulip Guest Inn exceeded every expectation. The attention to detail is remarkable — from the crisp linens to the personalised service. A true boutique experience in the heart of Point Pedro.',
    avatar:
      'https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&cs=tinysrgb&w=200',
  },
  {
    id: '3',
    name: 'Michael Chen',
    country: 'Australia',
    rating: 5,
    quote:
      'We brought the whole family and everyone loved it. The family room is spacious and comfortable, and the pool kept the kids happy all day. The surrounding area is fascinating to explore.',
    avatar:
      'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?auto=compress&cs=tinysrgb&w=200',
  },
  {
    id: '4',
    name: 'Sophie Laurent',
    country: 'France',
    rating: 5,
    quote:
      'Magnifique! The ambience is calm and elegant, the garden is enchanting and the staff are warm and attentive. I will absolutely return on my next visit to Sri Lanka.',
    avatar:
      'https://images.pexels.com/photos/774909/pexels-photo-774909.jpeg?auto=compress&cs=tinysrgb&w=200',
  },
  {
    id: '5',
    name: 'Arjun Perera',
    country: 'Sri Lanka',
    rating: 5,
    quote:
      'As a local traveller, I was impressed by the quality and attention to hospitality here. The property is beautifully maintained and the rooms are truly premium. Highly recommended for a relaxing escape.',
    avatar:
      'https://images.pexels.com/photos/1681010/pexels-photo-1681010.jpeg?auto=compress&cs=tinysrgb&w=200',
  },
];
