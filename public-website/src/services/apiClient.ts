import { getApiBaseUrl } from './config';

export type ApiResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  rooms?: T;
  room?: T;
};

export async function requestJson<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${getApiBaseUrl()}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });

  const payload = (await response.json()) as ApiResponse<T>;

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || 'Unable to load data from server.');
  }

  return (payload.data ?? payload.rooms ?? payload.room ?? payload) as T;
}
