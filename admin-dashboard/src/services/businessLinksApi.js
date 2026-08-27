import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const base = buildApiUrl('/business-links')

async function post(path, body) {
  const response = await apiFetch(`${base}/${path}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), timeoutMs: 20000 })
  return readJsonResponse(response, 'business_links')
}

export async function fetchBusinessLinks() {
  const response = await apiFetch(`${base}/list.php`)
  const payload = await readJsonResponse(response, 'business_links')
  return payload.data || []
}

export const saveBusinessLink = (link) => post('save.php', link)
export const toggleBusinessLink = (id, enabled) => post('toggle.php', { id, enabled })
export const deleteBusinessLink = (id) => post('delete.php', { id })
