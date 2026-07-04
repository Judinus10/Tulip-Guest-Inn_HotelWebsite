import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const API_BASE_URL = buildApiUrl('/gallery')

export async function fetchGallery() {
  const response = await apiFetch(`${API_BASE_URL}/list.php`, { method: 'GET' })
  const payload = await readJsonResponse(response)
  return payload.data || { folders: [], images: [] }
}

export async function createGalleryFolder(data) {
  const response = await apiFetch(`${API_BASE_URL}/create-folder.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  return readJsonResponse(response)
}

export async function updateGalleryFolder(data) {
  const response = await apiFetch(`${API_BASE_URL}/update-folder.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  return readJsonResponse(response)
}

export async function deleteGalleryFolder(id) {
  const response = await apiFetch(`${API_BASE_URL}/delete-folder.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })
  return readJsonResponse(response)
}

export async function uploadGalleryImages({ folder_id, status, sort_order, images }) {
  const formData = new FormData()
  formData.append('folder_id', String(folder_id))
  formData.append('status', status)
  formData.append('sort_order', String(sort_order))

  images.forEach((image, index) => {
    formData.append('images[]', image.file)
    formData.append(`titles[${index}]`, image.title)
  })

  const response = await apiFetch(`${API_BASE_URL}/upload-images.php`, {
    method: 'POST',
    body: formData,
  })
  return readJsonResponse(response)
}

export async function updateGalleryImage(data) {
  const formData = new FormData()
  formData.append('id', String(data.id))
  formData.append('folder_id', String(data.folder_id))
  formData.append('title', data.title)
  formData.append('status', data.status)
  formData.append('sort_order', String(data.sort_order))
  if (data.image_file) formData.append('image', data.image_file)

  const response = await apiFetch(`${API_BASE_URL}/update-image.php`, {
    method: 'POST',
    body: formData,
  })
  return readJsonResponse(response)
}

export async function deleteGalleryImage(id) {
  const response = await apiFetch(`${API_BASE_URL}/delete-image.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })
  return readJsonResponse(response)
}