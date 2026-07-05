import type { Attraction } from '../data/attractions';
import { requestJson } from './apiClient';
import { normalizeAttraction, type BackendExperienceItem } from './normalizers';

export async function getPublicAttractions(): Promise<Attraction[]> {
  const rows = await requestJson<BackendExperienceItem[]>('/experience/public-list.php');
  return Array.isArray(rows) ? rows.map(normalizeAttraction).filter((item) => item.image !== '') : [];
}
