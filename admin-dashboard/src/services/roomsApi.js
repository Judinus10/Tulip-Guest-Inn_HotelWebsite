import { apiFetch, buildApiUrl, readJsonResponse } from './apiClient'

function normalize(payload) {
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.rooms)) return payload.rooms
  return []
}

export async function listRooms() {
  const response = await apiFetch(buildApiUrl('/rooms/list.php'))
  const payload = await readJsonResponse(response)
  return normalize(payload)
}

export async function createRoom(formData) {
  const response = await apiFetch(buildApiUrl('/rooms/create.php'), {
    method: 'POST',
    body: formData,
  })
  return readJsonResponse(response)
}

export async function updateRoom(formData) {
  const response = await apiFetch(buildApiUrl('/rooms/update.php'), {
    method: 'POST',
    body: formData,
  })
  return readJsonResponse(response)
}

export async function deleteRoomById(id) {
  const response = await apiFetch(buildApiUrl('/rooms/delete.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })
  return readJsonResponse(response)
}

export async function deleteRoomImage(id) {
  const response = await apiFetch(buildApiUrl('/rooms/delete-image.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })
  return readJsonResponse(response)
}

export async function listAmenities() {
  const response = await apiFetch(buildApiUrl('/amenities/list.php'))
  const payload = await readJsonResponse(response)
  return Array.isArray(payload.data) ? payload.data : []
}

export async function createAmenity(name) {
  const response = await apiFetch(buildApiUrl('/amenities/create.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name }),
  })
  const payload = await readJsonResponse(response)
  return Array.isArray(payload.data) ? payload.data : []
}

export async function updateAmenity(id, name) {
  const response = await apiFetch(buildApiUrl('/amenities/update.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, name }),
  })
  const payload = await readJsonResponse(response)
  return Array.isArray(payload.data) ? payload.data : []
}

export async function deleteAmenity(id) {
  const response = await apiFetch(buildApiUrl('/amenities/delete.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })
  const payload = await readJsonResponse(response)
  return Array.isArray(payload.data) ? payload.data : []
}
