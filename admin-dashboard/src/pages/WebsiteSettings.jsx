import WebsiteBookingEmails from '@/pages/WebsiteBookingEmails'
import WebsiteAppearance from '@/pages/WebsiteAppearance'
import { useEffect, useMemo, useState } from 'react'
import {
  AlertCircle,
  CheckCircle2,
  Clock3,
  Facebook,
  Globe2,
  Loader2,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  Plus,
  RotateCcw,
  Save,
  Images,
  FileText,
  HelpCircle,
  Palette,
  Trash2,
} from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Input, Label, Textarea } from '@/components/ui/input'
import TimeWheelPicker from '@/components/ui/TimeWheelPicker'
import HeroContent from '@/pages/HeroContent'
import LegalContent from '@/pages/LegalContent'
import WebsiteFaqs from '@/pages/WebsiteFaqs'
import { fetchContactSettings, fetchPropertyContent, saveContactSettings, savePropertyContent } from '@/services/settingsApi'

const defaultSettings = {
  business_name: 'Tulip Guest Inn',
  opening_year: '2018',
  guest_rating: '4.5',
  address: 'Tulip Guest Inn, Sri Lanka',
  phone: '+94 77 123 4567',
  reception_contact_number: '+94 21 222 4567',
  whatsapp_reservation_number: '+94 77 123 4567',
  email: 'reservations@tulipguestinn.com',
  check_in_mode: '24_hours',
  stay_time_mode: 'custom',
  check_in_custom: 'Available 24 hours',
  check_out_custom: 'From 06:00 to 12:00',
  check_in_time: '13:00',
  check_out_from: '06:00',
  check_out_until: '12:00',
  business_hours: 'Daily · 7:00 AM – 10:00 PM',
  business_hours_mode: '24_7',
  business_hours_schedule: JSON.stringify(Object.fromEntries(
    ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'].map((day) => [day, { enabled: true, all_day: false, open: '08:00', close: '18:00' }])
  )),
  social_links: '',
  facebook_link: '',
  instagram_link: '',
  map_embed_url: '',
}

const socialPlatforms = ['facebook', 'instagram', 'youtube', 'tiktok', 'x', 'linkedin', 'other']
function readSocialLinks(settings) {
  try {
    if (settings.social_links) {
      const items = JSON.parse(settings.social_links)
      return Array.isArray(items) ? items : []
    }
  } catch { return [] }
  return ['facebook', 'instagram'].filter(platform => settings[`${platform}_link`] && settings[`${platform}_link`] !== '#')
    .map(platform => ({ platform, url: settings[`${platform}_link`], label: '' }))
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
  const [activeTab, setActiveTab] = useState('contact')
  const [bookingEmailsOpened, setBookingEmailsOpened] = useState(false)
  const [settings, setSettings] = useState(defaultSettings)
  const [savedSettings, setSavedSettings] = useState(defaultSettings)
  const [propertyContent, setPropertyContent] = useState({ nearby_places: [] })
  const [savedPropertyContent, setSavedPropertyContent] = useState({ nearby_places: [] })
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [toast, setToast] = useState({ message: '', type: 'success' })

  const hasChanges = useMemo(
    () => JSON.stringify(settings) !== JSON.stringify(savedSettings)
      || JSON.stringify(propertyContent) !== JSON.stringify(savedPropertyContent),
    [settings, savedSettings, propertyContent, savedPropertyContent]
  )

  useEffect(() => {
    let active = true

    async function loadSettings() {
      setIsLoading(true)
      setLoadError('')
      try {
        const [contactData, contentData] = await Promise.all([fetchContactSettings(), fetchPropertyContent()])
        const data = normalizeSettings(contactData)
        if (!active) return
        setSettings(data)
        setSavedSettings(data)
        const nearby = { nearby_places: Array.isArray(contentData.nearby_places) ? contentData.nearby_places : [] }
        setPropertyContent(nearby)
        setSavedPropertyContent(nearby)
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

  const socialLinks = readSocialLinks(settings)
  const setSocialLinks = (items) => updateSetting('social_links', JSON.stringify(items))
  const updateSocialLink = (index, field, value) => setSocialLinks(socialLinks.map((item, i) => i === index ? { ...item, [field]: value } : item))

  const updateBusinessDay = (day, field, value) => {
    setSettings((current) => {
      const schedule = readWeeklySchedule(current.business_hours_schedule)
      schedule[day] = { ...schedule[day], [field]: value }
      return { ...current, business_hours_schedule: JSON.stringify(schedule) }
    })
  }

  const updateNearbyPlace = (index, field, value) => setPropertyContent((current) => ({
    nearby_places: current.nearby_places.map((place, itemIndex) => itemIndex === index ? { ...place, [field]: value } : place),
  }))

  const addNearbyPlace = () => setPropertyContent((current) => ({
    nearby_places: [...current.nearby_places, { name: '', distance: '', distance_unit: 'km' }],
  }))

  const removeNearbyPlace = (index) => setPropertyContent((current) => ({
    nearby_places: current.nearby_places.filter((_, itemIndex) => itemIndex !== index),
  }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    setIsSaving(true)

    try {
      const [contactData, contentData] = await Promise.all([
        saveContactSettings(settings),
        savePropertyContent(propertyContent),
      ])
      const saved = normalizeSettings(contactData)
      setSettings(saved)
      setSavedSettings(saved)
      const nearby = { nearby_places: Array.isArray(contentData.nearby_places) ? contentData.nearby_places : [] }
      setPropertyContent(nearby)
      setSavedPropertyContent(nearby)
      showToast('Contact settings saved to database.')
    } catch (error) {
      showToast(error.message || 'Could not save contact settings.', 'error')
    } finally {
      setIsSaving(false)
    }
  }

  const handleReset = () => {
    setSettings(savedSettings)
    setPropertyContent(savedPropertyContent)
    showToast('Changes reset.')
  }

  return (
    <div>
      <Toast message={toast.message} type={toast.type} onClose={() => setToast({ message: '', type: 'success' })} />

      <PageHeader
        title={activeTab === 'booking-emails' ? 'Email Templates' : 'Website Settings'}
        description={activeTab === 'booking-emails' ? 'Customize email wording and preview changes before saving.' : 'Manage website content, appearance, policies, FAQs and booking email wording.'}
      />

      <div className="mb-6 rounded-2xl border border-border bg-white p-2 shadow-sm shadow-slate-200/60">
        <div role="tablist" aria-label="Website content settings" className="flex gap-1 overflow-x-auto">
          {[
            { id: 'contact', label: 'Contact & Social', icon: Globe2 },
            { id: 'hero', label: 'Hero Media', icon: Images },
            { id: 'appearance', label: 'Appearance', icon: Palette },
            { id: 'booking-emails', label: 'Email Templates', icon: Mail },
            { id: 'terms', label: 'Terms & Conditions', icon: FileText },
            { id: 'privacy', label: 'Privacy Policy', icon: FileText },
            { id: 'refund', label: 'Refund Policy', icon: FileText },
            { id: 'faqs', label: 'FAQs', icon: HelpCircle },
          ].map(({ id, label, icon: Icon }) => (
            <button key={id} type="button" role="tab" aria-selected={activeTab === id}
              onClick={() => { setActiveTab(id); if (id === 'booking-emails') setBookingEmailsOpened(true) }}
              className={`flex shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition ${
                activeTab === id ? 'bg-blue-600 text-white shadow-sm' : 'text-text-secondary hover:bg-slate-100 hover:text-text-primary'
              }`}>
              <Icon className="h-4 w-4" /> {label}
            </button>
          ))}
        </div>
      </div>
      {activeTab === 'hero' && <HeroContent embedded />}
      {activeTab === 'appearance' && <WebsiteAppearance />}
      {bookingEmailsOpened && <div hidden={activeTab !== 'booking-emails'}><WebsiteBookingEmails /></div>}
      {activeTab === 'terms' && <LegalContent page="terms" />}
      {activeTab === 'privacy' && <LegalContent page="privacy" />}
      {activeTab === 'refund' && <LegalContent page="refund" />}
      {activeTab === 'faqs' && <WebsiteFaqs embedded />}
      <div hidden={activeTab !== 'contact'}>
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

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div className="space-y-2">
                <Label htmlFor="opening_year">Opening Year</Label>
                <FieldWithIcon icon={Clock3}>
                  <Input
                    id="opening_year"
                    type="number"
                    min="1900"
                    max={new Date().getFullYear()}
                    value={settings.opening_year}
                    onChange={(event) => updateSetting('opening_year', event.target.value)}
                    className="pl-9"
                    placeholder="2018"
                    required
                  />
                </FieldWithIcon>
                <p className="text-xs text-text-secondary">Used to calculate Years of Service automatically.</p>
              </div>

                <div className="space-y-2">
                  <Label htmlFor="guest_rating">Guest Rating (out of 5)</Label>
                  <Input id="guest_rating" type="number" min="0" max="5" step="0.1" required value={settings.guest_rating} onChange={event => updateSetting('guest_rating', event.target.value)} placeholder="4.5" />
                  <p className="text-xs text-text-secondary">Displayed as the Guest Rating on the homepage.</p>
                </div>
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
                <p className="text-xs text-text-secondary">Press Enter wherever the address should start on a new line.</p>
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
                      placeholder="reservations@tulipguestinn.com"
                      required
                    />
                  </FieldWithIcon>
                </div>
              </div>

              <div className="space-y-4 border-t border-border pt-5">
                <Label>Check-in & Check-out Times</Label>
                <div className="grid grid-cols-2 gap-3">
                  {[['standard', 'Standard Times'], ['custom', 'Custom Times']].map(([mode, title]) => <button key={mode} type="button" onClick={() => updateSetting('stay_time_mode', mode)} className={`rounded-xl border px-3 py-3 text-left text-sm font-medium ${((settings.stay_time_mode || 'custom') === mode) ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-border bg-white text-text-secondary'}`}>{title}</button>)}
                </div>
                <div className="grid grid-cols-2 gap-3">
                  {settings.stay_time_mode === 'standard' ? <>
                    <div className="space-y-2"><Label>Check-in</Label><TimeWheelPicker label="Check-in" value={settings.check_in_time} onChange={value => updateSetting('check_in_time', value)} /></div>
                    <div className="space-y-2"><Label>Check-out</Label><TimeWheelPicker label="Check-out" value={settings.check_out_until} onChange={value => updateSetting('check_out_until', value)} /></div>
                  </> : <>
                    <div className="space-y-2"><Label htmlFor="check_in_custom">Check-in</Label><Input id="check_in_custom" value={settings.check_in_custom} onChange={event => updateSetting('check_in_custom', event.target.value)} maxLength={255} /></div>
                    <div className="space-y-2"><Label htmlFor="check_out_custom">Check-out</Label><Input id="check_out_custom" value={settings.check_out_custom} onChange={event => updateSetting('check_out_custom', event.target.value)} maxLength={255} /></div>
                  </>}
                </div>
              </div>

              <div className="space-y-3 border-t border-border pt-5">
                <div>
                  <Label>Business Hours</Label>
                  <p className="mt-1 text-xs text-text-secondary">Keep the current 24/7 display or configure each day separately.</p>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  {[['24_7', 'Open 24/7'], ['custom', 'Custom Weekly Hours']].map(([value, label]) => (
                    <button key={value} type="button" onClick={() => updateSetting('business_hours_mode', value)} className={`rounded-xl border px-4 py-3 text-left text-sm font-medium transition ${settings.business_hours_mode === value ? 'border-primary-500 bg-blue-50 text-primary-700' : 'border-border bg-white text-text-secondary hover:border-slate-300'}`}>
                      <span className="flex items-center gap-2"><Clock3 className="h-4 w-4" />{label}</span>
                    </button>
                  ))}
                </div>
                {settings.business_hours_mode === 'custom' && (
                  <div className="space-y-2 rounded-xl border border-border bg-slate-50 p-3">
                    {weekDays.map((day) => {
                      const hours = readWeeklySchedule(settings.business_hours_schedule)[day]
                      return (
                        <div key={day} className="min-w-0 rounded-lg border border-border bg-white p-3">
                          <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                            <span className="text-sm font-medium capitalize text-text-primary">{day}</span>
                            <div className="flex flex-wrap items-center gap-4">
                              <label className="flex items-center gap-2 whitespace-nowrap text-xs text-text-secondary">
                                <input type="checkbox" checked={hours.enabled} onChange={(event) => updateBusinessDay(day, 'enabled', event.target.checked)} /> Open
                              </label>
                              <label className={`flex items-center gap-2 whitespace-nowrap text-xs text-text-secondary ${!hours.enabled ? 'opacity-40' : ''}`}>
                                <input type="checkbox" checked={hours.all_day} disabled={!hours.enabled} onChange={(event) => updateBusinessDay(day, 'all_day', event.target.checked)} /> 24 hrs
                              </label>
                            </div>
                          </div>
                          {hours.enabled && !hours.all_day && (
                            <div className="mt-3 grid min-w-0 grid-cols-2 gap-3">
                              <div className="min-w-0 space-y-1">
                                <label className="text-xs text-text-secondary" htmlFor={`${day}-opening`}>Opening</label>
                                <Input id={`${day}-opening`} className="w-full min-w-0" type="time" value={hours.open} onChange={(event) => updateBusinessDay(day, 'open', event.target.value)} aria-label={`${day} opening time`} />
                              </div>
                              <div className="min-w-0 space-y-1">
                                <label className="text-xs text-text-secondary" htmlFor={`${day}-closing`}>Closing</label>
                                <Input id={`${day}-closing`} className="w-full min-w-0" type="time" value={hours.close} onChange={(event) => updateBusinessDay(day, 'close', event.target.value)} aria-label={`${day} closing time`} />
                              </div>
                            </div>
                          )}
                        </div>
                      )
                    })}
                  </div>
                )}
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

              <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <h3 className="font-semibold">Social Media</h3>
                  <Button type="button" disabled={socialLinks.length >= 20} onClick={() => setSocialLinks([...socialLinks, { platform: 'youtube', url: '', label: '' }])}><Plus className="h-4 w-4" />Add Social Media</Button>
                </div>
                <p className="text-xs text-text-secondary">Only saved links appear on the website. Remove a row to hide its icon.</p>
                {socialLinks.map((item, index) => <div key={index} className="flex items-center gap-3 rounded-xl border border-border p-3">
                  <select aria-label={`Social platform ${index + 1}`} value={item.platform} onChange={e => updateSocialLink(index, 'platform', e.target.value)} className="w-28 min-w-0 shrink-0 rounded-lg border border-border bg-white p-3 text-sm sm:w-36">
                    {socialPlatforms.map(platform => <option key={platform} value={platform}>{({youtube:'YouTube',tiktok:'TikTok',x:'X',linkedin:'LinkedIn'})[platform] || platform.charAt(0).toUpperCase() + platform.slice(1)}</option>)}
                  </select>
                  {item.platform === 'other' && <Input className="min-w-0 flex-1" aria-label="Social media name" maxLength={60} value={item.label || ''} onChange={e => updateSocialLink(index, 'label', e.target.value)} placeholder="Name" />}
                  <Input className="min-w-0 flex-[2]" type="url" aria-label={`Social URL ${index + 1}`} maxLength={1000} value={item.url} onChange={e => updateSocialLink(index, 'url', e.target.value)} placeholder="https://..." />
                  <Button className="shrink-0" type="button" aria-label={`Remove social link ${index + 1}`} onClick={() => setSocialLinks(socialLinks.filter((_, i) => i !== index))}><Trash2 className="h-4 w-4 text-red-600" /></Button>
                </div>)}
              </div>

              <div className="border-t border-border pt-5">
                <div className="flex items-center justify-between gap-4">
                  <div>
                    <h3 className="font-semibold text-text-primary">Nearby Places</h3>
                    <p className="mt-1 text-xs text-text-secondary">Displayed on every public room-details page.</p>
                  </div>
                  <Button type="button" variant="outline" onClick={addNearbyPlace}>
                    <Plus className="h-4 w-4" /> Add Place
                  </Button>
                </div>
                <div className="mt-4 space-y-3">
                  {propertyContent.nearby_places.length === 0 && <p className="rounded-xl bg-slate-50 p-4 text-sm text-text-secondary">No nearby places added.</p>}
                  {propertyContent.nearby_places.map((place, index) => (
                    <div key={place.id || `nearby-${index}`} className="grid gap-3 rounded-xl border border-border p-3 sm:grid-cols-[1fr_120px_90px_auto]">
                      <Input value={place.name} onChange={(event) => updateNearbyPlace(index, 'name', event.target.value)} placeholder="Nearby beach" />
                      <Input type="number" min="0" step="0.01" value={place.distance} onChange={(event) => updateNearbyPlace(index, 'distance', event.target.value)} placeholder="Distance" />
                      <select value={place.distance_unit || 'km'} onChange={(event) => updateNearbyPlace(index, 'distance_unit', event.target.value)} className="h-10 rounded-lg border border-border bg-white px-3 text-sm text-text-primary">
                        <option value="km">km</option><option value="m">m</option>
                      </select>
                      <Button type="button" variant="outline" onClick={() => removeNearbyPlace(index)} aria-label={`Delete ${place.name || 'nearby place'}`}>
                        <Trash2 className="h-4 w-4 text-red-600" />
                      </Button>
                    </div>
                  ))}
                </div>
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
    </div>
  )
}

const weekDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

function readWeeklySchedule(value) {
  try {
    const parsed = typeof value === 'string' ? JSON.parse(value) : value
    return Object.fromEntries(weekDays.map((day) => [day, {
      enabled: Boolean(parsed?.[day]?.enabled),
      all_day: Boolean(parsed?.[day]?.all_day),
      open: parsed?.[day]?.open || '08:00',
      close: parsed?.[day]?.close || '18:00',
    }]))
  } catch {
    return JSON.parse(defaultSettings.business_hours_schedule)
  }
}
