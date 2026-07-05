import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const API_BASE_URL = buildApiUrl('/experience')

export async function fetchExperienceItems() {
  const response = await apiFetch(`${API_BASE_URL}/list.php`, { method: 'GET' })
  const payload = await readJsonResponse(response)
  return payload.data || []
}

export async function createExperienceItem(data) {
  const formData = new FormData()

  formData.append('title', data.title)
  formData.append('category', data.category)
  formData.append('location', data.location || '')
  formData.append('distance', data.distance || '')
  formData.append('duration', data.duration || '')
  formData.append('description', data.description)
  formData.append('status', data.status)
  formData.append('sort_order', String(data.sort_order || 1))

  if (data.image_file) {
    formData.append('image', data.image_file)
  }

  const response = await apiFetch(`${API_BASE_URL}/create.php`, {
    method: 'POST',
    body: formData,
  })

  return readJsonResponse(response)
}

export async function updateExperienceItem(data) {
  const formData = new FormData()

  formData.append('id', String(data.id))
  formData.append('title', data.title)
  formData.append('category', data.category)
  formData.append('location', data.location || '')
  formData.append('distance', data.distance || '')
  formData.append('duration', data.duration || '')
  formData.append('description', data.description)
  formData.append('status', data.status)
  formData.append('sort_order', String(data.sort_order || 1))

  if (data.image_file) {
    formData.append('image', data.image_file)
  }

  const response = await apiFetch(`${API_BASE_URL}/update.php`, {
    method: 'POST',
    body: formData,
  })

  return readJsonResponse(response)
}

export async function deleteExperienceItem(id) {
  const response = await apiFetch(`${API_BASE_URL}/delete.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })

  return readJsonResponse(response)
}