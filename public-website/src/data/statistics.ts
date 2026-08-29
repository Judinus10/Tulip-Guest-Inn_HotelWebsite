export interface Statistic {
  id: string;
  value: number;
  suffix: string;
  label: string;
}

export const statistics: Statistic[] = [
  { id: '1', value: 2500, suffix: '+', label: 'Happy Guests' },
  { id: '2', value: 0, suffix: '', label: 'Luxury Rooms' },
  { id: '3', value: 8, suffix: '', label: 'Years of Service' },
  { id: '4', value: 4.5, suffix: '/5', label: 'Guest Rating' },
];
