import { useEffect, useMemo, useState } from 'react'
import {
  AlertCircle,
  CheckCircle2,
  Facebook,
  Globe2,
  Loader2,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  RotateCcw,
  Save,
} from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Input, Label, Textarea } from '@/components/ui/input'
import { fetchContactSettings, saveContactSettings } from '@/services/settingsApi'

const defaultSettings = {
  business_name: 'Tulip Guest Inn',
  address: 'Tulip Guest Inn, Sri Lanka',
  phone: '+94 77 123 4567',
  reception_contact_number: '+94 21 222 4567',
  whatsapp_reservation_number: '+94 77 123 4567',
  email: 'reservations@jebalguesthouse.com',
  business_hours: 'Daily · 7:00 AM – 10:00 PM',
  facebook_link: '',
  instagram_link: '',
  map_embed_url: '',
}

function normalizeSettings(data = {}) {
  return {
    ...defaultSettings,
    ...Object.fromEntries(
      Object.entries(data || {}).map(([key, value]) => [key, String(value ?? '').trim()])
    ),
  }
}

function Toast({ message, type = 'success', onClose }) {
  if (!message) return null

  const isError = type === 'error'
  const tone = isError ? 'border-red-200 text-red-700' : 'border-emerald-200 text-emerald-700'
  const Icon = isError ? AlertCircle : CheckCircle2

  return (
    <div className={`fixed right-4 top-4 z-50 flex max-w-sm items-center gap-3 rounded-xl border bg-white px-4 py-3 text-sm font-medium shadow-lg shadow-slate-200/70 ${tone}`}>
      <Icon className="h-5 w-5" />
      <span>{message}</span>
      <button
        type="button"
        onClick={onClose}
        className="ml-2 rounded-md px-2 py-1 text-xs text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
      >
        Close
      </button>
    </div>
  )
}

function FormSection({ icon: Icon, title, description, children }) {
  return (
    <SectionCard>
      <div className="mb-6 flex items-start gap-3">
        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-600">
          <Icon className="h-5 w-5" />
        </div>
        <div>
          <h2 className="text-lg font-semibold text-text-primary">{title}</h2>
          {description && <p className="mt-1 text-sm text-text-secondary">{description}</p>}
        </div>
      </div>
      <div className="space-y-5">{children}</div>
    </SectionCard>
  )
}

function FieldWithIcon({ icon: Icon, children, align = 'center' }) {
  const topClass = align === 'top' ? 'top-3' : 'top-1/2 -translate-y-1/2'
  return (
    <div className="relative">
      <Icon className={`pointer-events-none absolute left-3 h-4 w-4 text-slate-400 ${topClass}`} />
      {children}
    </div>
  )
}

export default function WebsiteSettings() {
  const [settings, setSettings] = useState(defaultSettings)
  const [savedSettings, setSavedSettings] = useState(defaultSettings)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [toast, setToast] = useState({ message: '', type: 'success' })

  const hasChanges = useMemo(
    () => JSON.stringify(settings) !== JSON.stringify(savedSettings),
    [settings, savedSettings]
  )

  useEffect(() => {
    let active = true

    async function loadSettings() {
      setIsLoading(true)
      setLoadError('')
      try {
        const data = normalizeSettings(await fetchContactSettings())
        if (!active) return
        setSettings(data)
        setSavedSettings(data)
      } catch (error) {
        if (!active) return
        setLoadError(error.message || 'Could not load contact settings.')
      } finally {
        if (active) setIsLoading(false)
      }
    }

    loadSettings()

    return () => {
      active = false
    }
  }, [])

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    window.setTimeout(() => setToast({ message: '', type: 'success' }), 2500)
  }

  const updateSetting = (field, value) => {
    setSettings((current) => ({ ...current, [field]: value }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setIsSaving(true)

    try {
      const saved = normalizeSettings(await saveContactSettings(settings))
      setSettings(saved)
      setSavedSettings(saved)
      showToast('Contact settings saved to database.')
    } catch (error) {
      showToast(error.message || 'Could not save contact settings.', 'error')
    } finally {
      setIsSaving(false)
    }
  }

  const handleReset = () => {
    setSettings(savedSettings)
    showToast('Changes reset.')
  }

  return (
    <div>
      <Toast message={toast.message} type={toast.type} onClose={() => setToast({ message: '', type: 'success' })} />

      <PageHeader
        title="Website Contact Settings"
        description="Update the contact details shown on the public home page and contact page. These values are saved in the database."
      />

      {loadError && (
        <div className="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
          {loadError}
        </div>
      )}

      {isLoading ? (
        <SectionCard>
          <div className="flex items-center gap-3 text-sm text-text-secondary">
            <Loader2 className="h-5 w-5 animate-spin" />
            Loading contact settings...
          </div>
        </SectionCard>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid gap-6 xl:grid-cols-2">
            <FormSection
              icon={Phone}
              title="Public Contact Details"
              description="These are displayed on the public website. Stop using hardcoded numbers."
            >
              <div className="space-y-2">
                <Label htmlFor="business_name">Business Name</Label>
                <Input
                  id="business_name"
                  value={settings.business_name}
                  onChange={(event) => updateSetting('business_name', event.target.value)}
                  placeholder="Tulip Guest Inn"
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="address">Address</Label>
                <FieldWithIcon icon={MapPin} align="top">
                  <Textarea
                    id="address"
                    value={settings.address}
                    onChange={(event) => updateSetting('address', event.target.value)}
                    className="pl-9"
                    placeholder="Full guest house address"
                    rows={3}
                    required
                  />
                </FieldWithIcon>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="phone">Main Phone</Label>
                  <FieldWithIcon icon={Phone}>
                    <Input
                      id="phone"
                      value={settings.phone}
                      onChange={(event) => updateSetting('phone', event.target.value)}
                      className="pl-9"
                      placeholder="+94 77 123 4567"
                    />
                  </FieldWithIcon>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="reception_contact_number">Reception Number</Label>
                  <FieldWithIcon icon={Phone}>
                    <Input
                      id="reception_contact_number"
                      value={settings.reception_contact_number}
                      onChange={(event) => updateSetting('reception_contact_number', event.target.value)}
                      className="pl-9"
                      placeholder="+94 21 222 4567"
                    />
                  </FieldWithIcon>
                </div>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="whatsapp_reservation_number">WhatsApp Number</Label>
                  <FieldWithIcon icon={MessageCircle}>
                    <Input
                      id="whatsapp_reservation_number"
                      value={settings.whatsapp_reservation_number}
                      onChange={(event) => updateSetting('whatsapp_reservation_number', event.target.value)}
                      className="pl-9"
                      placeholder="+94 77 123 4567"
                    />
                  </FieldWithIcon>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email">Reservation Email</Label>
                  <FieldWithIcon icon={Mail}>
                    <Input
                      id="email"
                      type="email"
                      value={settings.email}
                      onChange={(event) => updateSetting('email', event.target.value)}
                      className="pl-9"
                      placeholder="reservations@jebalguesthouse.com"
                      required
                    />
                  </FieldWithIcon>
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="business_hours">Business Hours</Label>
                <Input
                  id="business_hours"
                  value={settings.business_hours}
                  onChange={(event) => updateSetting('business_hours', event.target.value)}
                  placeholder="Daily · 7:00 AM – 10:00 PM"
                />
              </div>
            </FormSection>

            <FormSection
              icon={Globe2}
              title="Map & Social Links"
              description="Optional links used by the public contact sections."
            >
              <div className="space-y-2">
                <Label htmlFor="map_embed_url">Google Map Embed URL</Label>
                <FieldWithIcon icon={MapPin} align="top">
                  <Textarea
                    id="map_embed_url"
                    value={settings.map_embed_url}
                    onChange={(event) => updateSetting('map_embed_url', event.target.value)}
                    className="pl-9"
                    placeholder="Paste Google Maps embed URL here"
                    rows={3}
                  />
                </FieldWithIcon>
                <p className="text-xs text-text-secondary">Use only the iframe src URL, not the full iframe code.</p>
              </div>

              <div className="space-y-2">
                <Label htmlFor="facebook_link">Facebook Link</Label>
                <FieldWithIcon icon={Facebook}>
                  <Input
                    id="facebook_link"
                    type="url"
                    value={settings.facebook_link}
                    onChange={(event) => updateSetting('facebook_link', event.target.value)}
                    className="pl-9"
                    placeholder="https://facebook.com/jebalguesthouse"
                  />
                </FieldWithIcon>
              </div>

              <div className="space-y-2">
                <Label htmlFor="instagram_link">Instagram Link</Label>
                <FieldWithIcon icon={Globe2}>
                  <Input
                    id="instagram_link"
                    type="url"
                    value={settings.instagram_link}
                    onChange={(event) => updateSetting('instagram_link', event.target.value)}
                    className="pl-9"
                    placeholder="https://instagram.com/jebalguesthouse"
                  />
                </FieldWithIcon>
              </div>
            </FormSection>
          </div>

          <div className="flex flex-col-reverse gap-3 rounded-2xl border border-border bg-white p-4 shadow-sm shadow-slate-200/60 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-text-secondary">
              {hasChanges ? 'You have unsaved contact changes.' : 'Contact settings are synced with the database.'}
            </p>
            <div className="flex flex-col gap-2 sm:flex-row">
              <Button type="button" variant="outline" onClick={handleReset} disabled={!hasChanges || isSaving}>
                <RotateCcw className="h-4 w-4" />
                Reset Changes
              </Button>
              <Button type="submit" disabled={isSaving}>
                {isSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                {isSaving ? 'Saving...' : 'Save Settings'}
              </Button>
            </div>
          </div>
        </form>
      )}
    </div>
  )
}