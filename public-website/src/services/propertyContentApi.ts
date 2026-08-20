import { requestJson } from './apiClient';

export interface NearbyPlace {
  id: number;
  name: string;
  distance: number;
  distance_unit: 'm' | 'km';
}

interface PropertyContent {
  nearby_places?: NearbyPlace[];
}

export async function fetchPropertyContent(): Promise<{ nearby_places: NearbyPlace[] }> {
  try {
    const payload = await requestJson<PropertyContent>('/settings/get-property-content.php');
    return { nearby_places: Array.isArray(payload.nearby_places) ? payload.nearby_places : [] };
  } catch (error) {
    console.warn('Unable to load nearby places.', error);
    return { nearby_places: [] };
  }
}
