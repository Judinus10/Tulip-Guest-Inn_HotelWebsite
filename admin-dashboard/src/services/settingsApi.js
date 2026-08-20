import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const API_BASE_URL = buildApiUrl('/settings')

export async function fetchContactSettings() {
  const response = await apiFetch(`${API_BASE_URL}/get-contact.php`, { method: 'GET' })
  const payload = await readJsonResponse(response)
  return payload.data || {}
}

export async function saveContactSettings(settings) {
  const response = await apiFetch(`${API_BASE_URL}/update-contact.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(settings),
  })
  const payload = await readJsonResponse(response)
  return payload.data || {}
}

export async function fetchPropertyContent() {
  const response = await apiFetch(`${API_BASE_URL}/get-property-content-admin.php`, { method: 'GET' })
  const payload = await readJsonResponse(response)
  return payload.data || { nearby_places: [] }
}

export async function savePropertyContent(content) {
  const response = await apiFetch(`${API_BASE_URL}/update-property-content.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(content),
  })
  const payload = await readJsonResponse(response)
  return payload.data || { nearby_places: [] }
}
