import type { Offer } from '../data/offers';
import { requestJson } from './apiClient';
import { normalizeOffer, type BackendOffer } from './normalizers';

export async function getPublicOffers(): Promise<Offer[]> {
  const rows = await requestJson<BackendOffer[]>('/offers/public-list.php');
  return Array.isArray(rows) ? rows.map(normalizeOffer).filter((offer) => offer.image !== '') : [];
}
