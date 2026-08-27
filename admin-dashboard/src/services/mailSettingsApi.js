import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const base = buildApiUrl('/mail-settings')

async function post(path, body) {
  const response = await apiFetch(`${base}/${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    timeoutMs: 25000,
  })
  return readJsonResponse(response, 'mail_settings')
}

export async function fetchMailSettings() {
  const response = await apiFetch(`${base}/list.php`)
  const payload = await readJsonResponse(response, 'mail_settings')
  return payload.data || { accounts: [], functions: [], routes: [] }
}

export const saveMailAccount = (account) => post('save-account.php', account)
export const testMailAccount = (id, recipientEmail) => post('test-account.php', { id, recipient_email: recipientEmail })
export const deleteMailAccount = (id) => post('delete-account.php', { id })
export const toggleMailAccount = (id, enabled) => post('toggle-account.php', { id, enabled })
export const saveMailRoutes = (routes) => post('save-routes.php', { routes })
export const saveMailProvider = (provider) => post('save-provider.php', provider)
export const testMailProvider = (provider, recipientEmail) => post('test-provider.php', { provider, recipient_email: recipientEmail })
