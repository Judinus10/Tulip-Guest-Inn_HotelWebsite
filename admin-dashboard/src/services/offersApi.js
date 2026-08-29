import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const API_BASE_URL = buildApiUrl('/offers')

function parseDiscountValue(value, fallback = 0) {
  const parsed = Number(value)
  if (Number.isFinite(parsed)) return parsed
  const match = String(fallback || '').match(/[0-9]+(?:\.[0-9]+)?/)
  return match ? Number(match[0]) : 0
}

function inferDiscountType(offer) {
  const value = String(offer.discount_type || '').toLowerCase()
  if (value === 'fixed' || value === 'percentage') return value
  return String(offer.discount_label || '').includes('%') ? 'percentage' : 'fixed'
}

export function normalizeOffer(offer = {}) {
  const discountType = inferDiscountType(offer)
  const discountValue = parseDiscountValue(offer.discount_value, offer.discount_label)

  return {
    id: Number(offer.id || 0),
    title: offer.title || '',
    description: offer.description || '',
    package_category: offer.package_category || offer.subtitle || offer.validity_label || 'General Package',
    discount_type: discountType,
    discount_value: discountValue,
    discount_label: offer.discount_label || (discountType === 'percentage' ? `${discountValue}% Off` : `LKR ${discountValue} Off`),
    validity_label: offer.validity_label || offer.package_category || offer.subtitle || '',
    subtitle: offer.subtitle || offer.package_category || offer.validity_label || '',
    image_path: offer.image_path || '',
    image_preview: offer.image_url || offer.image_path || '',
    image_file_name: '',
    details: Array.isArray(offer.details) ? offer.details : [],
    status: offer.status || 'active',
    start_date: offer.start_date || '',
    end_date: offer.end_date || '',
    sort_order: Number(offer.sort_order || 0),
    booking_scope: ['single', 'multi', 'both'].includes(offer.booking_scope) ? offer.booking_scope : 'both',
    minimum_nights: Math.max(1, Number(offer.minimum_nights || 1)),
    minimum_rooms: Math.max(1, Number(offer.minimum_rooms || 1)),
    minimum_guests: Math.max(1, Number(offer.minimum_guests || 1)),
    priority: Number(offer.priority || 0),
    automatic_apply: offer.automatic_apply === undefined ? true : Boolean(Number(offer.automatic_apply) || offer.automatic_apply === true),
    created_at: offer.created_at || '',
    updated_at: offer.updated_at || '',
  }
}

function appendOfferFormData(payload) {
  const formData = new FormData()

  Object.entries(payload || {}).forEach(([key, value]) => {
    if (key === 'image_file' && value instanceof File) {
      formData.append('image', value)
      return
    }

    if (['image_preview', 'image_file_name'].includes(key)) {
      return
    }

    if (key === 'details') {
      const cleanDetails = Array.isArray(value)
        ? value.map((item) => String(item).trim()).filter(Boolean)
        : String(value || '')
            .split(/\r?\n/)
            .map((item) => item.trim())
            .filter(Boolean)

      formData.append('details', JSON.stringify(cleanDetails))
      return
    }

    if (value !== undefined && value !== null) {
      formData.append(key, value)
    }
  })

  return formData
}

export async function fetchOffers() {
  const response = await apiFetch(`${API_BASE_URL}/list.php?_=${Date.now()}`, {
    method: 'GET',
  })

  const payload = await readJsonResponse(response)
  return (payload.data || []).map(normalizeOffer)
}

export async function createOffer(payload) {
  const response = await apiFetch(`${API_BASE_URL}/create.php`, {
    method: 'POST',
    body: appendOfferFormData(payload),
  })

  const result = await readJsonResponse(response)
  return normalizeOffer(result.data || {})
}

export async function updateOffer(payload) {
  const response = await apiFetch(`${API_BASE_URL}/update.php`, {
    method: 'POST',
    body: appendOfferFormData(payload),
  })

  const result = await readJsonResponse(response)
  return normalizeOffer(result.data || {})
}

export async function deleteOfferById(id) {
  const response = await apiFetch(`${API_BASE_URL}/delete.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })

  return readJsonResponse(response)
}
