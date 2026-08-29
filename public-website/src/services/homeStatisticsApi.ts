import type { Statistic } from '../data/statistics';
import { requestJson } from './apiClient';

export async function getPublicHomeStatistics(): Promise<Statistic[]> {
  const statistics = await requestJson<Statistic[]>('/settings/get-home-statistics.php');
  return Array.isArray(statistics) ? statistics : [];
}
