export interface Attraction {
  id: string;
  name: string;
  description: string;
  longDescription: string;
  distance: string;
  duration: string;
  image: string;
  category: string;
}

export const attractions: Attraction[] = [
  {
    id: '1',
    name: 'Point Pedro Beach',
    description: 'The northernmost tip of Sri Lanka — a sweeping beach of natural beauty and historical significance.',
    longDescription:
      'Point Pedro Beach marks the very northernmost point of Sri Lanka. With its expansive sandy shores, crashing waves and lighthouse in the distance, this beach offers a profound sense of place. It is a wonderful spot for sunrise walks and photography.',
    distance: '1.5 km',
    duration: '5 min drive',
    image:
      'https://images.pexels.com/photos/1032650/pexels-photo-1032650.jpeg?auto=compress&cs=tinysrgb&w=1200',
    category: 'Beach',
  },
  {
    id: '2',
    name: 'Point Pedro Lighthouse',
    description: 'A historic colonial-era lighthouse standing at the northernmost tip of Sri Lanka.',
    longDescription:
      'The Point Pedro Lighthouse is one of the most iconic landmarks in the Jaffna Peninsula. Built during the colonial period, this slender white lighthouse has guided sailors for over a century. A visit at sunset is particularly memorable.',
    distance: '2 km',
    duration: '7 min drive',
    image:
      'https://images.pexels.com/photos/1486785/pexels-photo-1486785.jpeg?auto=compress&cs=tinysrgb&w=1200',
    category: 'Landmark',
  },
  {
    id: '3',
    name: 'Casuarina Beach',
    description: 'A pristine, palm-fringed beach known for its crystal-clear waters and tranquil atmosphere.',
    longDescription:
      'Casuarina Beach is widely considered one of the most beautiful beaches in the Jaffna Peninsula. Named after the casuarina trees lining its shores, the beach is perfect for swimming, sunbathing and peaceful afternoon strolls.',
    distance: '8 km',
    duration: '15 min drive',
    image:
      'https://images.pexels.com/photos/1174732/pexels-photo-1174732.jpeg?auto=compress&cs=tinysrgb&w=1200',
    category: 'Beach',
  },
  {
    id: '4',
    name: 'Keerimalai Hot Springs',
    description: 'Sacred natural springs with therapeutic properties — a revered site for pilgrims and visitors alike.',
    longDescription:
      'The Keerimalai Hot Springs are natural mineral springs that flow directly into the sea. Believed to have healing properties, the springs are a popular bathing site for both locals and visitors. The adjacent Nagadeepa ferry point is also located here.',
    distance: '12 km',
    duration: '20 min drive',
    image:
      'https://images.pexels.com/photos/2387873/pexels-photo-2387873.jpeg?auto=compress&cs=tinysrgb&w=1200',
    category: 'Nature',
  },
  {
    id: '5',
    name: 'Naguleswaram Temple',
    description: 'One of the five sacred Shiva temples of Sri Lanka — an ancient and deeply venerated site.',
    longDescription:
      'The Naguleswaram Temple at Keerimalai is one of the Pancha Ishwarams — the five ancient Shiva temples of Sri Lanka. A place of immense spiritual significance, the temple draws pilgrims from across the island and the world.',
    distance: '12 km',
    duration: '20 min drive',
    image:
      'https://images.pexels.com/photos/2387418/pexels-photo-2387418.jpeg?auto=compress&cs=tinysrgb&w=1200',
    category: 'Temple',
  },
  {
    id: '6',
    name: 'Vallipuram Temple',
    description: 'An ancient temple of great historical importance on the coast of the Jaffna Peninsula.',
    longDescription:
      'The Vallipuram Alvar Temple is a renowned Hindu temple located on the northern coast of the Jaffna Peninsula. Its ancient gold tablet — one of the oldest archaeological finds in Sri Lanka — makes it a site of both spiritual and historical interest.',
    distance: '7 km',
    duration: '12 min drive',
    image:
      'https://images.pexels.com/photos/1538177/pexels-photo-1538177.jpeg?auto=compress&cs=tinysrgb&w=1200',
    category: 'Temple',
  },
];
