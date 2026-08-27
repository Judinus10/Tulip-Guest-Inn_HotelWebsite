import { useEffect, useMemo, useState } from 'react'
import { AlertCircle, Building2, CheckCircle2, Cloud, Edit3, ExternalLink, KeyRound, Link2, Loader2, LockKeyhole, Mail, Plus, Save, Send, Server, Settings2, ShieldCheck, Trash2, X } from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button, buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { Input, Label } from '@/components/ui/input'
import { deleteMailAccount, fetchMailSettings, saveMailAccount, saveMailProvider, saveMailRoutes, testMailAccount, testMailProvider, toggleMailAccount } from '@/services/mailSettingsApi'
import { deleteBusinessLink, fetchBusinessLinks, saveBusinessLink, toggleBusinessLink } from '@/services/businessLinksApi'

const emptyAccount = {
  id: 0,
  account_name: '',
  provider: 'custom',
  email_address: '',
  smtp_username: '',
  password: '',
  from_name: 'Tulip Guest Inn',
  smtp_host: 'smtp.office365.com',
  smtp_port: 587,
  smtp_encryption: 'tls',
  functions: [],
}

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
        <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"><div className="flex-1 space-y-2"><Label>Diagnostics Test Destination</Label><Input type="email" value={form.test_destination} onChange={(e) => update('test_destination', e.target.value)} placeholder="e.g. validation@tulipguestinn.com" /></div><Button type="button" disabled={busy} className="bg-emerald-600 hover:bg-emerald-700" onClick={() => onTest('microsoft_graph', form.test_destination)}><Send className="h-4 w-4" />Test Connection</Button></div>
      </div>
    </div>
  )
}

function ConnectionDiagnostics({ value, onChange, onTest, placeholder = 'e.g. validation@tulipguestinn.com' }) {
  return (
    <div className="border-t bg-white p-5">
      <div className="flex items-start gap-3"><div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><ShieldCheck className="h-5 w-5" /></div><div><h3 className="text-lg font-semibold text-slate-900">Connection Diagnostics</h3><p className="mt-1 text-sm text-slate-500">Send a diagnostics validation email to ensure host credentials and secure handshake connections are fully verified.</p></div></div>
      <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"><div className="flex-1 space-y-2"><Label>Diagnostics Test Destination</Label><Input type="email" value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} /></div><Button type="button" className="bg-emerald-600 hover:bg-emerald-700" onClick={onTest}><Send className="h-4 w-4" />Test Connection</Button></div>
    </div>
  )
}

function SmtpConfigurationPanel({ config, busy, onSave, onTest }) {
  const [form, setForm] = useState({ host: '', port: 587, encryption: 'tls', username: '', password: '', sender_email: '', sender_name: 'Tulip Guest Inn', test_destination: '' })
  const update = (key, value) => setForm((current) => ({ ...current, [key]: value }))
  useEffect(() => setForm((current) => ({ ...current, host: config?.smtp_host || '', port: config?.smtp_port || 587, encryption: config?.smtp_encryption || 'tls', username: config?.smtp_username || '', sender_email: config?.sender_email || '', sender_name: config?.sender_name || 'Tulip Guest Inn', password: '' })), [config])
  const save = () => onSave({ provider: 'server', sender_email: form.sender_email, sender_name: form.sender_name, smtp_host: form.host, smtp_port: form.port, smtp_encryption: form.encryption, smtp_username: form.username, password: form.password })
  return (
    <div className="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm">
      <div className="border-b border-violet-100 px-5 py-5"><div className="flex items-center gap-3"><div className="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-700"><Server className="h-5 w-5" /></div><h2 className="text-xl font-semibold text-slate-900">SMTP Server Configuration</h2></div><div className="mt-4 flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /><p><strong>SMTP Server note:</strong> Enter the outgoing mail server credentials supplied by your hosting or email provider.</p></div></div>
      <div className="grid gap-5 p-5 md:grid-cols-2">
        <div className="space-y-2 md:col-span-2"><Label>SMTP Host</Label><Input value={form.host} onChange={(e) => update('host', e.target.value)} placeholder="e.g. mail.tulipguestinn.com" /></div>
        <div className="space-y-2"><Label>Port</Label><Input type="number" value={form.port} onChange={(e) => update('port', Number(e.target.value))} /></div>
        <div className="space-y-2"><Label>Encryption</Label><select value={form.encryption} onChange={(e) => update('encryption', e.target.value)} className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select></div>
        <div className="space-y-2"><Label>SMTP Username</Label><Input value={form.username} onChange={(e) => update('username', e.target.value)} placeholder="Full mailbox address" /></div>
        <div className="space-y-2"><Label>SMTP Password</Label><Input type="password" autoComplete="new-password" value={form.password} onChange={(e) => update('password', e.target.value)} placeholder="Mailbox or app password" /></div>
        <div className="space-y-2"><Label>Sender Address</Label><Input type="email" value={form.sender_email} onChange={(e) => update('sender_email', e.target.value)} placeholder="info@tulipguestinn.com" /></div>
        <div className="space-y-2"><Label>Sender Name</Label><Input value={form.sender_name} onChange={(e) => update('sender_name', e.target.value)} /></div>
      </div>
      <div className="flex flex-col gap-3 border-t bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div className="text-sm text-slate-500">Connection Status: <ConnectionState config={config} /></div><Button type="button" disabled={busy} onClick={save}><Save className="h-4 w-4" />Save SMTP Settings</Button></div>
      <ConnectionDiagnostics value={form.test_destination} onChange={(value) => update('test_destination', value)} onTest={() => onTest('server', form.test_destination)} />
    </div>
  )
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
      <ConnectionDiagnostics value={form.test_destination} onChange={(value) => update('test_destination', value)} onTest={() => onTest('google', form.test_destination)} />
    </div>
  )
}

function AccountModal({ account, functions, busy, onClose, onSave }) {
  const [form, setForm] = useState({ ...emptyAccount, ...account, password: '' })
  const update = (key, value) => setForm((current) => ({ ...current, [key]: value }))
  const toggleFunction = (key) => update('functions', form.functions.includes(key) ? form.functions.filter((item) => item !== key) : [...form.functions, key])

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 px-3 py-5 backdrop-blur-sm" onMouseDown={onClose}>
      <form className="max-h-full w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl" onSubmit={(event) => { event.preventDefault(); onSave(form) }} onMouseDown={(event) => event.stopPropagation()}>
        <div className="sticky top-0 z-10 flex items-start justify-between border-b bg-white px-5 py-4">
          <div><h2 className="text-xl font-semibold text-slate-900">{form.id ? 'Edit SMTP Account' : 'Add SMTP Account'}</h2><p className="mt-1 text-sm text-slate-500">Credentials are encrypted by the PHP backend and are never returned to this page.</p></div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><X className="h-5 w-5" /></button>
        </div>

        <div className="grid gap-5 p-5 sm:grid-cols-2">
          <div className="space-y-2"><Label>Account name</Label><Input value={form.account_name} onChange={(e) => update('account_name', e.target.value)} placeholder="Tulip Booking Mail" /></div>
          <div className="space-y-2"><Label>Provider</Label><select value={form.provider} onChange={(e) => update('provider', e.target.value)} className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"><option value="custom">Custom SMTP</option><option value="office365">Office 365 SMTP (legacy)</option></select></div>
          <div className="space-y-2"><Label>Sender email</Label><Input type="email" value={form.email_address} onChange={(e) => { update('email_address', e.target.value); if (!form.smtp_username) update('smtp_username', e.target.value) }} placeholder="bookings@tulipguestinn.com" /></div>
          <div className="space-y-2"><Label>Sender name</Label><Input value={form.from_name} onChange={(e) => update('from_name', e.target.value)} /></div>
          <div className="space-y-2"><Label>SMTP username</Label><Input value={form.smtp_username} onChange={(e) => update('smtp_username', e.target.value)} placeholder="Usually the full email address" /></div>
          <div className="space-y-2"><Label>{form.id ? 'New password (leave blank to keep current)' : 'Mailbox password'}</Label><Input type="password" autoComplete="new-password" value={form.password} onChange={(e) => update('password', e.target.value)} /></div>
          <div className="space-y-2"><Label>SMTP host</Label><Input value={form.smtp_host} disabled={form.provider === 'office365'} onChange={(e) => update('smtp_host', e.target.value)} /></div>
          <div className="grid grid-cols-2 gap-3"><div className="space-y-2"><Label>Port</Label><Input type="number" value={form.smtp_port} disabled={form.provider === 'office365'} onChange={(e) => update('smtp_port', Number(e.target.value))} /></div><div className="space-y-2"><Label>Encryption</Label><select value={form.smtp_encryption} disabled={form.provider === 'office365'} onChange={(e) => update('smtp_encryption', e.target.value)} className="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select></div></div>

          <div className="space-y-3 sm:col-span-2">
            <div><Label>Assigned mail functions</Label><p className="mt-1 text-xs text-slate-500">Selecting a function makes this account its sender. One function can have only one active sender.</p></div>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {functions.map((item) => <label key={item.key} className={`flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-3 text-sm ${form.functions.includes(item.key) ? 'border-blue-500 bg-blue-50 text-blue-800' : 'border-slate-200'}`}><input type="checkbox" checked={form.functions.includes(item.key)} onChange={() => toggleFunction(item.key)} />{item.label}</label>)}
            </div>
          </div>
        </div>

        <div className="sticky bottom-0 flex justify-end gap-3 border-t bg-white px-5 py-4"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" disabled={busy}>{busy && <Loader2 className="h-4 w-4 animate-spin" />}Save Account</Button></div>
      </form>
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
  const [activeTab, setActiveTab] = useState('mailbox')
  const [deliveryChannel, setDeliveryChannel] = useState('microsoft_graph')
  const [data, setData] = useState({ accounts: [], functions: [], routes: [], providers: [] })
  const [routes, setRoutes] = useState([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [editing, setEditing] = useState(null)
  const [businessLinks, setBusinessLinks] = useState([])
  const [selectedBusinessLink, setSelectedBusinessLink] = useState('')
  const [managingLinks, setManagingLinks] = useState(false)
  const [toast, setToast] = useState({ message: '', type: 'success' })
  const connectedAccounts = useMemo(() => data.accounts.filter((account) => account.is_enabled && account.connection_status === 'connected'), [data.accounts])

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    window.setTimeout(() => setToast({ message: '', type: 'success' }), 4500)
  }

  const load = async () => {
    setLoading(true)
    try {
      const [result, links] = await Promise.all([fetchMailSettings(), fetchBusinessLinks()])
      setData(result)
      setRoutes(result.routes || [])
      setBusinessLinks(links)
      setSelectedBusinessLink((current) => links.some((item) => String(item.id) === String(current) && item.is_enabled) ? current : String(links.find((item) => item.is_enabled)?.id || ''))
    } catch (error) { showToast(error.message || 'Mail settings could not be loaded.', 'error') }
    finally { setLoading(false) }
  }

  useEffect(() => { load() }, [])

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

  const saveAccount = async (form) => {
    setBusy(true)
    try {
      const payload = await saveMailAccount(form)
      setEditing(null)
      showToast(payload.message || 'Mail account saved. Test it before use.')
      await load()
    } catch (error) { showToast(error.message || 'Mail account could not be saved.', 'error') }
    finally { setBusy(false) }
  }

  const testAccount = async (account) => {
    const recipient = window.prompt('Send the connection test to:', account.email_address)
    if (recipient === null) return
    setBusy(true)
    try { const payload = await testMailAccount(account.id, recipient); showToast(payload.message); await load() }
    catch (error) { showToast(error.message || 'Connection test failed.', 'error'); await load() }
    finally { setBusy(false) }
  }

  const removeAccount = async (account) => {
    if (!window.confirm(`Delete ${account.email_address}? Its assigned functions will have no database sender until another account is selected.`)) return
    setBusy(true)
    try { const payload = await deleteMailAccount(account.id); showToast(payload.message); await load() }
    catch (error) { showToast(error.message || 'Account could not be deleted.', 'error') }
    finally { setBusy(false) }
  }

  const toggleAccount = async (account) => {
    setBusy(true)
    try { const payload = await toggleMailAccount(account.id, !account.is_enabled); showToast(payload.message); await load() }
    catch (error) { showToast(error.message || 'Account status could not be changed.', 'error') }
    finally { setBusy(false) }
  }

  const updateRoute = (key, field, value) => setRoutes((current) => current.map((route) => route.function_key === key ? { ...route, [field]: value } : route))

  const saveRoutes = async () => {
    setBusy(true)
    try { const payload = await saveMailRoutes(routes); showToast(payload.message); await load() }
    catch (error) { showToast(error.message || 'Routing rules could not be saved.', 'error') }
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
      <PageHeader title="Settings Manager" description="Configure outgoing email delivery channels for Tulip Guest Inn." />

      <div className="grid gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
        <SectionCard>
          <div className="mb-5"><p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Delivery channels</p><h2 className="mt-1 text-lg font-semibold text-slate-900">Choose a mail service</h2></div>
          <div className="space-y-3">
            <button type="button" onClick={() => setDeliveryChannel('server')} className={`flex w-full items-start gap-3 rounded-xl border p-4 text-left transition ${deliveryChannel === 'server' ? 'border-violet-500 bg-violet-50' : 'border-slate-200 hover:border-slate-300'}`}><Server className="mt-0.5 h-5 w-5 text-slate-600" /><span><strong className="block text-sm text-slate-900">SMTP Server</strong><span className="mt-1 block text-xs text-slate-500">Custom mail server delivery</span></span></button>
            <button type="button" onClick={() => setDeliveryChannel('google')} className={`flex w-full items-start gap-3 rounded-xl border p-4 text-left transition ${deliveryChannel === 'google' ? 'border-violet-500 bg-violet-50' : 'border-slate-200 hover:border-slate-300'}`}><Mail className="mt-0.5 h-5 w-5 text-slate-600" /><span><strong className="block text-sm text-slate-900">Google Mailbox</strong><span className="mt-1 block text-xs text-slate-500">Connect via Gmail OAuth / App Pass</span></span></button>
            <button type="button" onClick={() => setDeliveryChannel('microsoft_graph')} className={`flex w-full items-start gap-3 rounded-xl border p-4 text-left transition ${deliveryChannel === 'microsoft_graph' ? 'border-violet-500 bg-violet-50' : 'border-slate-200 hover:border-slate-300'}`}><Cloud className={`mt-0.5 h-5 w-5 ${deliveryChannel === 'microsoft_graph' ? 'text-violet-600' : 'text-slate-500'}`} /><span className="flex-1"><span className="flex items-center justify-between gap-2"><strong className="block text-sm text-slate-900">Outlook Mailbox</strong><span className="h-2 w-2 rounded-full bg-violet-500" /></span><span className="mt-1 block text-xs text-slate-500">Connect via Office 365 Exchange</span></span></button>
          </div>
        </SectionCard>

        {deliveryChannel === 'server' && <SmtpConfigurationPanel config={providerConfig('server')} busy={busy} onSave={saveProvider} onTest={testProvider} />}
        {deliveryChannel === 'google' && <GoogleConfigurationPanel config={providerConfig('google')} busy={busy} onSave={saveProvider} onTest={testProvider} />}
        {deliveryChannel === 'microsoft_graph' && <MicrosoftGraphPanel config={providerConfig('microsoft_graph')} busy={busy} onSave={saveProvider} onTest={testProvider} />}
      </div>

      {editing && <AccountModal account={editing} functions={data.functions} busy={busy} onClose={() => setEditing(null)} onSave={saveAccount} />}
      {managingLinks && <BusinessLinksModal links={businessLinks} busy={busy} onClose={() => setManagingLinks(false)} onSave={saveLink} onToggle={toggleLink} onDelete={removeLink} />}
    </div>
  )
}
