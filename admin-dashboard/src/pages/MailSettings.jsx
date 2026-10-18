import { useEffect, useState } from 'react'
import { AlertCircle, Building2, CheckCircle2, Cloud, Edit3, Users, ExternalLink, KeyRound, Link2, Loader2, LockKeyhole, Mail, Plus, Save, Send, Server, Settings2, ShieldCheck, Trash2, X } from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button, buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { Input, Label } from '@/components/ui/input'
import { fetchSharedSmtp, updateSharedSmtp, fetchMailSettings, saveMailProvider, saveMailRoutes, saveNotificationRouting, testMailProvider } from '@/services/mailSettingsApi'
import { deleteBusinessLink, fetchBusinessLinks, saveBusinessLink, toggleBusinessLink } from '@/services/businessLinksApi'

const emptyGraphAccount = {
  app_password: '',
  tenant_id: '',
  client_id: '',
  client_secret: '',
  email_address: '',
  test_destination: '',
}

const emptyBusinessLink = { id: 0, title: '', description: '', portal_url: 'https://', category: 'other', is_enabled: true, is_system: false }

function Toast({ toast, close }) {
  if (!toast.message) return null
  const error = toast.type === 'error'
  const Icon = error ? AlertCircle : CheckCircle2
  return (
    <div className={`fixed right-4 top-4 z-[80] flex w-[calc(100%-2rem)] max-w-md items-start gap-3 rounded-xl border bg-white p-4 shadow-2xl ${error ? 'border-red-200 text-red-700' : 'border-emerald-200 text-emerald-700'}`}>
      <Icon className="mt-0.5 h-5 w-5 shrink-0" />
      <span className="flex-1 text-sm font-medium">{toast.message}</span>
      <button type="button" onClick={close}><X className="h-4 w-4" /></button>
    </div>
  )
}

function StatusBadge({ account }) {
  const connected = account.connection_status === 'connected' && account.is_enabled
  const failed = account.connection_status === 'failed'
  return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${connected ? 'bg-emerald-50 text-emerald-700' : failed ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'}`}>{connected ? 'Connected' : failed ? 'Test failed' : 'Test required'}</span>
}

function ConnectionState({ config }) {
  const connected = config?.connection_status === 'connected' && config?.is_active
  const failed = config?.connection_status === 'failed'
  return <span className={`font-semibold ${connected ? 'text-emerald-600' : failed ? 'text-red-600' : 'text-amber-600'}`}>{connected ? 'Connected and active' : failed ? 'Connection failed' : 'Not Connected'}</span>
}

function MicrosoftGraphPanel({ config, busy, onSave, onTest }) {
  const [form, setForm] = useState(emptyGraphAccount)
  const update = (key, value) => setForm((current) => ({ ...current, [key]: value }))
  useEffect(() => setForm((current) => ({ ...current, tenant_id: config?.tenant_id || '', client_id: config?.oauth_client_id || '', email_address: config?.sender_email || '', app_password: '', client_secret: '' })), [config])
  const save = () => onSave({ provider: 'microsoft_graph', sender_email: form.email_address, sender_name: 'Tulip Guest Inn', smtp_username: form.email_address, password: form.app_password, tenant_id: form.tenant_id, oauth_client_id: form.client_id, client_secret: form.client_secret })

  return (
    <div className="rounded-2xl border border-violet-200 bg-white shadow-sm">
      <div className="border-b border-violet-100 px-5 py-5">
        <div className="flex items-start gap-3">
          <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700"><Settings2 className="h-5 w-5" /></div>
          <div><h2 className="text-xl font-semibold text-slate-900">Outlook Exchange Configuration</h2></div>
        </div>
        <div className="mt-4 flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /><p><strong>Outlook Exchange note:</strong> Secure connection uses Office 365 Exchange services. Enter your App Password or Client Credentials below.</p></div>
      </div>

      <div className="grid gap-5 p-5 md:grid-cols-2">
        <div className="space-y-2 md:col-span-2"><Label>App Password</Label><Input type="password" autoComplete="new-password" value={form.app_password} onChange={(e) => update('app_password', e.target.value)} placeholder="Exchange account password" /></div>
        <div className="space-y-2"><Label>Microsoft Tenant ID</Label><Input value={form.tenant_id} onChange={(e) => update('tenant_id', e.target.value)} placeholder="Microsoft Entra tenant UUID" /></div>
        <div className="space-y-2"><Label>Azure Application Client ID</Label><Input value={form.client_id} onChange={(e) => update('client_id', e.target.value)} placeholder="Azure App UUID" /></div>
        <div className="space-y-2"><Label>Azure Client Secret</Label><div className="relative"><LockKeyhole className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" /><Input className="pl-9" type="password" autoComplete="new-password" value={form.client_secret} onChange={(e) => update('client_secret', e.target.value)} placeholder="Enter Azure client secret" /></div></div>
        <div className="space-y-2"><Label>Outlook Sender Address</Label><Input type="email" value={form.email_address} onChange={(e) => update('email_address', e.target.value)} placeholder="e.g. support@tulipguestinn.com" /></div>
      </div>

      <div className="flex flex-col gap-3 border-t bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="text-sm text-slate-500">Connection Status: <ConnectionState config={config} /></div>
        <Button type="button" disabled={busy} onClick={save}>{busy && <Loader2 className="h-4 w-4 animate-spin" />}Authorize Microsoft Account</Button>
      </div>

      <div className="border-t bg-white p-5">
        <div className="flex items-start gap-3"><div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><ShieldCheck className="h-5 w-5" /></div><div><h3 className="text-lg font-semibold text-slate-900">Connection Diagnostics</h3><p className="mt-1 text-sm text-slate-500">Send a diagnostics validation email to ensure host credentials and secure handshake connections are fully verified.</p></div></div>
        <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"><div className="flex-1 space-y-2"><Label>Diagnostics Test Destination</Label><Input type="email" value={form.test_destination} onChange={(e) => update('test_destination', e.target.value)} placeholder="e.g. validation@tulipguestinn.com" /></div><Button type="button" disabled={busy} className="bg-emerald-600 hover:bg-emerald-700" onClick={() => onTest('microsoft_graph', form.test_destination)}>{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}{busy ? 'Sending...' : 'Test Connection'}</Button></div>
      </div>
    </div>
  )
}

function ConnectionDiagnostics({ value, onChange, onTest, busy, placeholder = 'e.g. validation@tulipguestinn.com' }) {
  return (
    <div className="border-t bg-white p-5">
      <div className="flex items-start gap-3"><div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><ShieldCheck className="h-5 w-5" /></div><div><h3 className="text-lg font-semibold text-slate-900">Connection Diagnostics</h3><p className="mt-1 text-sm text-slate-500">Send a diagnostics validation email to ensure host credentials and secure handshake connections are fully verified.</p></div></div>
      <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"><div className="flex-1 space-y-2"><Label>Diagnostics Test Destination</Label><Input type="email" value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} /></div><Button type="button" disabled={busy} className="bg-emerald-600 hover:bg-emerald-700" onClick={onTest}>{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}{busy ? 'Sending...' : 'Test Connection'}</Button></div>
    </div>
  )
}

function SmtpConfigurationPanel({ config, busy, onAction }) {
  const [form, setForm] = useState(null)
  const [destinations, setDestinations] = useState({})
  const [dirty, setDirty] = useState(false)
  useEffect(() => {
    if (!config) return
    setForm({ ...config, senders: Object.fromEntries(['booking', 'contact', 'admin'].map(role => [role, { ...config.senders?.[role], password: '' }])) })
    setDirty(false)
  }, [config])
  if (!form) return <SectionCard>Loading SMTP settings…</SectionCard>
  const update = (key, value) => { setForm(current => ({ ...current, [key]: value })); setDirty(true) }
  const updateSender = (role, key, value) => { setForm(current => ({ ...current, senders: { ...current.senders, [role]: { ...current.senders[role], [key]: value } } })); setDirty(true) }
  const ready = ['booking', 'contact', 'admin'].every(role => form.senders[role].connection_status === 'connected')
  return <div className="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm">
    <div className="border-b p-5"><h2 className="text-xl font-semibold text-slate-900">SMTP Server Configuration</h2><p className="mt-2 text-sm text-slate-500">One server, three sender mailboxes. Each mailbox uses its email address as the SMTP username.</p></div>
    <div className="grid gap-5 p-5 md:grid-cols-2">
      <div className="space-y-2 md:col-span-2"><Label>SMTP Host</Label><Input value={form.smtp_host} onChange={e => update('smtp_host', e.target.value)} placeholder="mail.tulipguestinn.com" /></div>
      <div className="space-y-2"><Label>Port</Label><Input type="number" min="1" max="65535" value={form.smtp_port} onChange={e => update('smtp_port', Number(e.target.value))} /></div>
      <div className="space-y-2"><Label>Encryption</Label><select className="h-10 w-full rounded-lg border px-3" value={form.smtp_encryption} onChange={e => update('smtp_encryption', e.target.value)}><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select></div>
      <div className="space-y-2 md:col-span-2"><Label>Sender Name</Label><Input value={form.sender_name} onChange={e => update('sender_name', e.target.value)} /></div>
    </div>
    <div className="space-y-4 px-5 pb-5">{[['booking', 'Booking emails', 'Bookings and payment messages'], ['contact', 'Contact emails', 'Contact enquiries and customer confirmations'], ['admin', 'Admin / system emails', 'System alerts, security emails and stay reminders']].map(([role, title, description]) => {
      const sender = form.senders[role]
      return <div key={role} className="rounded-xl border border-slate-200 p-4">
        <div className="flex flex-wrap items-center justify-between gap-2"><h3 className="font-semibold text-slate-900">{title}</h3><StatusBadge account={{ connection_status: dirty ? 'untested' : sender.connection_status, is_enabled: true }} /></div>
        <p className="mt-1 text-xs text-slate-500">{description}</p>
        <div className="mt-4 grid gap-4 md:grid-cols-2"><div className="space-y-2"><Label>Sender Email</Label><Input type="email" value={sender.email || ''} onChange={e => updateSender(role, 'email', e.target.value)} /></div><div className="space-y-2"><Label>Mailbox Password</Label><Input type="password" autoComplete="new-password" value={sender.password} onChange={e => updateSender(role, 'password', e.target.value)} placeholder={sender.has_password ? 'Leave blank to keep saved password' : 'Mailbox or app password'} /></div></div>
        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"><div className="flex-1 space-y-2"><Label>Test Destination</Label><Input type="email" value={destinations[role] || ''} placeholder="recipient@example.com" onChange={e => setDestinations(current => ({ ...current, [role]: e.target.value }))} /></div><Button type="button" variant="outline" disabled={busy || dirty || !destinations[role]} onClick={() => onAction({ action: 'test', role, recipient_email: destinations[role] })}><Send className="h-4 w-4" />Test Connection</Button></div>
      </div>
    })}</div>
    <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-slate-50 p-5"><p className="text-xs text-slate-500">Save changes before testing. The current active settings stay in use until you activate these verified settings.</p><div className="flex flex-wrap gap-3"><Button disabled={busy} onClick={() => onAction({ ...form, action: 'save' })}><Save className="h-4 w-4" />Save SMTP Settings</Button><Button variant="outline" disabled={busy || dirty || !ready} onClick={() => onAction({ action: 'activate' })}>Activate SMTP</Button></div></div>
  </div>
}

function GoogleConfigurationPanel({ config, busy, onSave, onTest }) {
  const [form, setForm] = useState({ app_password: '', client_id: '', client_secret: '', email_address: '', test_destination: '' })
  const update = (key, value) => setForm((current) => ({ ...current, [key]: value }))
  useEffect(() => setForm((current) => ({ ...current, client_id: config?.oauth_client_id || '', email_address: config?.sender_email || '', app_password: '', client_secret: '' })), [config])
  const save = () => onSave({ provider: 'google', sender_email: form.email_address, sender_name: 'Tulip Guest Inn', smtp_username: form.email_address, password: form.app_password, oauth_client_id: form.client_id, client_secret: form.client_secret })
  return (
    <div className="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm">
      <div className="border-b border-violet-100 px-5 py-5"><div className="flex items-center gap-3"><div className="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600"><Mail className="h-5 w-5" /></div><h2 className="text-xl font-semibold text-slate-900">Google Mailbox Configuration</h2></div><div className="mt-4 flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /><p><strong>Google Mailbox note:</strong> Connect using a Gmail App Password or Google OAuth client credentials.</p></div></div>
      <div className="grid gap-5 p-5 md:grid-cols-2">
        <div className="space-y-2 md:col-span-2"><Label>Gmail App Password</Label><Input type="password" autoComplete="new-password" value={form.app_password} onChange={(e) => update('app_password', e.target.value)} placeholder="Google application password" /></div>
        <div className="space-y-2"><Label>Google OAuth Client ID</Label><Input value={form.client_id} onChange={(e) => update('client_id', e.target.value)} placeholder="Google OAuth client ID" /></div>
        <div className="space-y-2"><Label>Google Client Secret</Label><Input type="password" autoComplete="new-password" value={form.client_secret} onChange={(e) => update('client_secret', e.target.value)} placeholder="Google client secret" /></div>
        <div className="space-y-2 md:col-span-2"><Label>Gmail Sender Address</Label><Input type="email" value={form.email_address} onChange={(e) => update('email_address', e.target.value)} placeholder="e.g. bookings@gmail.com" /></div>
      </div>
      <div className="flex flex-col gap-3 border-t bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div className="text-sm text-slate-500">Connection Status: <ConnectionState config={config} /></div><Button type="button" disabled={busy} onClick={save}>Authorize Google Account</Button></div>
      <ConnectionDiagnostics value={form.test_destination} onChange={(value) => update('test_destination', value)} onTest={() => onTest('google', form.test_destination)} busy={busy} />
    </div>
  )
}

function BusinessLinksModal({ links, busy, onClose, onSave, onToggle, onDelete }) {
  const [editing, setEditing] = useState(null)
  const update = (key, value) => setEditing((current) => ({ ...current, [key]: value }))
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 px-3 py-5 backdrop-blur-sm" onMouseDown={onClose}>
      <div className="max-h-full w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
        <div className="sticky top-0 z-10 flex items-start justify-between border-b bg-white px-5 py-4"><div><h2 className="text-xl font-semibold text-slate-900">Manage Business Pages</h2><p className="mt-1 text-sm text-slate-500">Save HTTPS shortcuts only. External usernames and passwords must stay with the external service.</p></div><button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><X className="h-5 w-5" /></button></div>
        <div className="space-y-5 p-5">
          <div className="flex justify-end"><Button onClick={() => setEditing(emptyBusinessLink)}><Plus className="h-4 w-4" />Add Business Page</Button></div>
          {editing && <form className="grid gap-4 rounded-xl border border-blue-200 bg-blue-50/40 p-4 md:grid-cols-2" onSubmit={async (event) => { event.preventDefault(); const saved = await onSave(editing); if (saved) setEditing(null) }}>
            <div className="space-y-2"><Label>Page name</Label><Input value={editing.title} onChange={(e) => update('title', e.target.value)} placeholder="Google Business Profile" /></div>
            <div className="space-y-2"><Label>Category</Label><select value={editing.category} onChange={(e) => update('category', e.target.value)} className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"><option value="business">Business</option><option value="booking">Booking</option><option value="email">Email</option><option value="analytics">Analytics</option><option value="hosting">Hosting</option><option value="other">Other</option></select></div>
            <div className="space-y-2 md:col-span-2"><Label>HTTPS address</Label><Input type="url" value={editing.portal_url} onChange={(e) => update('portal_url', e.target.value)} placeholder="https://example.com/" /></div>
            <div className="space-y-2 md:col-span-2"><Label>Description</Label><Input value={editing.description} onChange={(e) => update('description', e.target.value)} placeholder="Explain what the client can manage here." /></div>
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={editing.is_enabled} onChange={(e) => update('is_enabled', e.target.checked)} />Show in Business Pages list</label>
            <div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setEditing(null)}>Cancel</Button><Button type="submit" disabled={busy}><Save className="h-4 w-4" />Save Page</Button></div>
          </form>}
          <div className="space-y-3">{links.map((link) => <div key={link.id} className="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center"><div className="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><Link2 className="h-5 w-5" /></div><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h3 className="font-semibold text-slate-900">{link.title}</h3><span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${link.is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{link.is_enabled ? 'Visible' : 'Hidden'}</span>{link.is_system && <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">Built in</span>}</div><p className="truncate text-sm text-slate-500">{link.portal_url}</p></div><div className="flex flex-wrap gap-2"><Button variant="outline" onClick={() => setEditing({ ...link })}><Edit3 className="h-4 w-4" />Edit</Button><Button variant="outline" disabled={busy} onClick={() => onToggle(link)}>{link.is_enabled ? 'Hide' : 'Show'}</Button>{!link.is_system && <Button variant="outline" disabled={busy} className="text-red-600" onClick={() => onDelete(link)}><Trash2 className="h-4 w-4" />Delete</Button>}</div></div>)}</div>
        </div>
      </div>
    </div>
  )
}

export default function MailSettings() {
  const [activeTab, setActiveTab] = useState('delivery')
  const [deliveryChannel, setDeliveryChannel] = useState(null)
  const [data, setData] = useState({ accounts: [], functions: [], routes: [], providers: [] })
  const [sharedSmtp, setSharedSmtp] = useState(null)
  const [routes, setRoutes] = useState([])
  const [notificationMode, setNotificationMode] = useState('default')
  const [notificationRecipients, setNotificationRecipients] = useState([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [businessLinks, setBusinessLinks] = useState([])
  const [selectedBusinessLink, setSelectedBusinessLink] = useState('')
  const [managingLinks, setManagingLinks] = useState(false)
  const [toast, setToast] = useState({ message: '', type: 'success' })

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    window.setTimeout(() => setToast(current => current.message === message ? { message: '', type: 'success' } : current), 9000)
  }

  const load = async () => {
    setLoading(true)
    try {
      const [result, links, smtp] = await Promise.all([fetchMailSettings(), fetchBusinessLinks(), fetchSharedSmtp()])
      setSharedSmtp(smtp)
      setData(result)
      setDeliveryChannel(current => current || result.providers?.find(item => item.is_active && item.connection_status === 'connected')?.provider || 'server')
      setRoutes(result.routes || [])
      setNotificationMode(result.notification_mode || 'default')
      const grouped = {}
      for (const row of result.notification_recipients || []) {
        if (!grouped[row.email_address]) grouped[row.email_address] = []
        grouped[row.email_address].push(row.function_key)
      }
      setNotificationRecipients(Object.entries(grouped).map(([email_address, functions]) => ({ email_address, functions })))
      setBusinessLinks(links)
      setSelectedBusinessLink((current) => links.some((item) => String(item.id) === String(current) && item.is_enabled) ? current : String(links.find((item) => item.is_enabled)?.id || ''))
    } catch (error) { showToast(error.message || 'Mail settings could not be loaded.', 'error') }
    finally { setLoading(false) }
  }

  useEffect(() => { load() }, [])

  const smtpAction = async (body) => {
    setBusy(true)
    try { const result = await updateSharedSmtp(body); showToast(result.message); await load() }
    catch (error) { showToast(error.message || 'SMTP request failed.', 'error'); if (body.action === 'test') await load() }
    finally { setBusy(false) }
  }
  const activeProvider = data.providers.find(item => item.is_active && item.connection_status === 'connected')?.provider
  const providerConfig = (provider) => data.providers?.find((item) => item.provider === provider)

  const saveProvider = async (form) => {
    setBusy(true)
    try { const payload = await saveMailProvider(form); showToast(payload.message || 'Mail settings saved.'); await load() }
    catch (error) { showToast(error.message || 'Mail settings could not be saved.', 'error') }
    finally { setBusy(false) }
  }

  const testProvider = async (provider, recipient) => {
    setBusy(true)
    try { const payload = await testMailProvider(provider, recipient); showToast(payload.message || 'Connection successful.'); await load() }
    catch (error) { showToast(error.message || 'Connection test failed.', 'error'); await load() }
    finally { setBusy(false) }
  }

  const updateRoute = (key, field, value) => setRoutes((current) => current.map((route) => route.function_key === key ? { ...route, [field]: value } : route))

  const saveRoutes = async () => {
    setBusy(true)
    try { const payload = await saveMailRoutes(routes.map(route => ({ ...route, sender_account_id: null }))); showToast(payload.message); await load(); return true }
    catch (error) { showToast(error.message || 'Routing rules could not be saved.', 'error'); return false }
    finally { setBusy(false) }
  }

  const saveNotificationAssignments = async () => {
    setBusy(true)
    try {
      const payload = await saveNotificationRouting(notificationMode, notificationRecipients.filter((item) => item.email_address.trim() || item.functions.length))
      showToast(payload.message || 'Notification routing saved.')
      await load()
    } catch (error) { showToast(error.message || 'Notification routing could not be saved.', 'error') }
    finally { setBusy(false) }
  }

  const saveLink = async (link) => {
    setBusy(true)
    try { const payload = await saveBusinessLink(link); showToast(payload.message); await load(); return true }
    catch (error) { showToast(error.message || 'Business page could not be saved.', 'error'); return false }
    finally { setBusy(false) }
  }

  const toggleLink = async (link) => {
    setBusy(true)
    try { const payload = await toggleBusinessLink(link.id, !link.is_enabled); showToast(payload.message); await load() }
    catch (error) { showToast(error.message || 'Business page status could not be changed.', 'error') }
    finally { setBusy(false) }
  }

  const removeLink = async (link) => {
    if (!window.confirm(`Delete ${link.title}?`)) return
    setBusy(true)
    try { const payload = await deleteBusinessLink(link.id); showToast(payload.message); await load() }
    catch (error) { showToast(error.message || 'Business page could not be deleted.', 'error') }
    finally { setBusy(false) }
  }

  const activeBusinessLinks = businessLinks.filter((link) => link.is_enabled)
  const selectedLink = activeBusinessLinks.find((link) => String(link.id) === String(selectedBusinessLink))

  return (
    <div>
      <Toast toast={toast} close={() => setToast({ message: '', type: 'success' })} />
      <PageHeader title="Mail Integrations" description="Configure email delivery channels and choose who receives hotel notifications." />

      <div className="mb-6 rounded-2xl border border-border bg-white p-2 shadow-sm shadow-slate-200/60">
        <div role="tablist" aria-label="Mail integration settings" className="flex gap-1 overflow-x-auto">
          {[
            { id: 'delivery', label: 'Delivery Channels', icon: Server },
            { id: 'routing', label: 'Email Notification Routing', icon: Users },
          ].map(({ id, label, icon: Icon }) => (
            <button key={id} type="button" role="tab" aria-selected={activeTab === id}
              onClick={() => setActiveTab(id)}
              className={`flex shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition ${
                activeTab === id ? 'bg-blue-600 text-white shadow-sm' : 'text-text-secondary hover:bg-slate-100 hover:text-text-primary'
              }`}>
              <Icon className="h-4 w-4" /> {label}
            </button>
          ))}
        </div>
      </div>

      {activeTab === 'delivery' && <div className="grid gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
        <SectionCard>
          <div className="mb-5"><p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Delivery channels</p><h2 className="mt-1 text-lg font-semibold text-slate-900">Choose a mail service</h2></div>
          <div className="space-y-3">
            {[['server', 'SMTP Server', 'Custom mail server delivery', Server], ['google', 'Google Mailbox', 'Connect via Gmail', Mail], ['microsoft_graph', 'Outlook Mailbox', 'Connect via Office 365 Exchange', Cloud]].map(([id, title, description, Icon]) => <button key={id} type="button" onClick={() => setDeliveryChannel(id)} className={`flex w-full items-start gap-3 rounded-xl border p-4 text-left transition ${deliveryChannel === id ? 'border-violet-500 bg-violet-50' : 'border-slate-200 hover:border-slate-300'}`}><Icon className="mt-0.5 h-5 w-5 text-slate-600" /><span className="flex-1"><span className="flex flex-wrap items-center justify-between gap-2"><strong className="text-sm text-slate-900">{title}</strong>{activeProvider === id && <span className="flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700"><span className="h-2 w-2 rounded-full bg-emerald-500" />Active</span>}</span><span className="mt-1 block text-xs text-slate-500">{description}</span></span></button>)}
          </div>
        </SectionCard>

        {deliveryChannel === 'server' && <SmtpConfigurationPanel config={sharedSmtp} busy={busy} onAction={smtpAction} />}
        {deliveryChannel === 'google' && <GoogleConfigurationPanel config={providerConfig('google')} busy={busy} onSave={saveProvider} onTest={testProvider} />}
        {deliveryChannel === 'microsoft_graph' && <MicrosoftGraphPanel config={providerConfig('microsoft_graph')} busy={busy} onSave={saveProvider} onTest={testProvider} />}
      </div>}

      {activeTab === 'routing' && <SectionCard>
        <div className="space-y-4 p-1">
          <div><h2 className="text-lg font-semibold text-slate-900">Email Notification Routing</h2><p className="mt-1 text-sm text-slate-500">The active delivery channel determines the sender. SMTP uses its booking, contact and admin mailboxes. Recipient addresses below determine who receives hotel notifications. Guest confirmations go to the guest's booking email.</p></div>
          <div className="flex flex-wrap gap-5 text-sm">
            <label className="flex items-center gap-2"><input type="radio" name="notificationMode" checked={notificationMode === 'default'} onChange={() => setNotificationMode('default')} />Default: one email per function</label>
            <label className="flex items-center gap-2"><input type="radio" name="notificationMode" checked={notificationMode === 'custom'} onChange={() => setNotificationMode('custom')} />Custom: multiple functions per email</label>
          </div>
          {notificationMode === 'default' ? (
            <div className="grid gap-3 md:grid-cols-3">
              {['booking', 'contact', 'admin_alert'].map((key) => {
                const current = routes.find((r) => r.function_key === key)
                return <label key={key} className="space-y-2 text-sm font-medium text-slate-700"><span>{key === 'admin_alert' ? 'Admin notifications' : key === 'booking' ? 'Booking notifications' : 'Contact notifications'}</span><Input type="email" value={current?.hotel_recipient_email || ''} onChange={(event) => updateRoute(key, 'hotel_recipient_email', event.target.value)} placeholder="recipient@example.com" /></label>
              })}
              <p className="text-xs text-slate-500 md:col-span-3">Payment notifications continue using the existing payment route. Guest confirmations are always sent to the guest address.</p>
            </div>
          ) : (
            <div className="space-y-3">
              {notificationRecipients.map((entry, index) => (
                <div key={index} className="rounded-xl border border-slate-200 p-4">
                  <div className="flex gap-2"><Input type="email" value={entry.email_address} placeholder="recipient@example.com" onChange={(event) => setNotificationRecipients((current) => current.map((row, i) => i === index ? { ...row, email_address: event.target.value } : row))} /><Button type="button" variant="outline" onClick={() => setNotificationRecipients((current) => current.filter((_, i) => i !== index))}><Trash2 className="h-4 w-4" /></Button></div>
                  <div className="mt-3 flex flex-wrap gap-4">{[['booking','Booking'],['contact','Contact'],['admin_alert','Admin'],['payment','Payment']].map(([key,label]) => <label key={key} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={entry.functions.includes(key)} onChange={(event) => setNotificationRecipients((current) => current.map((row, i) => i === index ? { ...row, functions: event.target.checked ? [...row.functions, key] : row.functions.filter((fn) => fn !== key) } : row))} />{label}</label>)}</div>
                </div>
              ))}
              <Button type="button" variant="outline" onClick={() => setNotificationRecipients((current) => [...current, { email_address: '', functions: [] }])}><Plus className="h-4 w-4" />Add Recipient</Button>
            </div>
          )}
          <div className="flex justify-end"><Button type="button" disabled={busy || loading} onClick={async () => { if (await saveRoutes()) await saveNotificationAssignments() }}><Save className="h-4 w-4" />Save Notification Recipients</Button></div>
        </div>
      </SectionCard>}

      {managingLinks && <BusinessLinksModal links={businessLinks} busy={busy} onClose={() => setManagingLinks(false)} onSave={saveLink} onToggle={toggleLink} onDelete={removeLink} />}
    </div>
  )
}
