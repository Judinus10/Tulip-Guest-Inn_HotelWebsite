const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '');

export function getApiBaseUrl(): string {
  if (!apiBaseUrl) {
    throw new Error('Missing VITE_API_BASE_URL in public-website .env file.');
  }

  return apiBaseUrl;
}
