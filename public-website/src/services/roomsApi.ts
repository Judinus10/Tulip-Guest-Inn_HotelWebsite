import type { Room } from '../data/rooms';
import { requestJson } from './apiClient';
import { normalizeRoom, type BackendRoom } from './normalizers';

export async function fetchPublicRooms(params: Record<string, string> = {}): Promise<Room[]> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value) search.set(key, value);
  });

  const suffix = search.toString() ? `?${search.toString()}` : '';
  const rooms = await requestJson<BackendRoom[]>(`/rooms/public-list.php${suffix}`);
  return Array.isArray(rooms) ? rooms.map(normalizeRoom) : [];
}

export async function fetchPublicRoom(idOrSlug: string): Promise<Room> {
  const room = await requestJson<BackendRoom>(`/rooms/detail.php?slug=${encodeURIComponent(idOrSlug)}`);
  return normalizeRoom(room);
}

export async function getPublicRooms(): Promise<Room[]> {
  return fetchPublicRooms();
}
