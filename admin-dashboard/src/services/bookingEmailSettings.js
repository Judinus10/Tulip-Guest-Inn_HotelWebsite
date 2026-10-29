import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const url = buildApiUrl('/settings/booking-emails.php')
export async function fetchBookingEmailSettings() {
  const response = await apiFetch(url, { method: 'GET', cache: 'no-store' })
  return (await readJsonResponse(response)).data
}
export async function saveBookingEmailSettings(type, content, version) {
  const response = await apiFetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ type, content, version }) })
  return (await readJsonResponse(response)).data
}

export async function previewBookingEmail(type, content, scenario = 'single', signal) {
  const response = await apiFetch(buildApiUrl('/settings/booking-email-preview.php'), {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ type, content, scenario }), signal,
  })
  return (await readJsonResponse(response)).data
}

export async function sendBookingEmailTest(type, content, scenario, recipient) {
  const response = await apiFetch(buildApiUrl('/settings/booking-email-test.php'), {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ type, content, scenario, recipient }),
  })
  return (await readJsonResponse(response)).data
}
