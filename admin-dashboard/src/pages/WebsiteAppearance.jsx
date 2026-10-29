import { useEffect, useState } from 'react'
import { Palette, RotateCcw, Save, Eye, AlertCircle, CheckCircle2, RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'
import { defaultThemeColors, themeColorFields, isThemeColor, contrastRatio, readableText, shade } from '@/services/themeColors'
import './WebsiteAppearance.css'

const endpoint = buildApiUrl('/settings/theme.php')
export default function WebsiteAppearance() {
  const [saved, setSaved] = useState(defaultThemeColors)
  const [defaults, setDefaults] = useState(defaultThemeColors)
  const [draft, setDraft] = useState(defaultThemeColors)
  const [loading, setLoading] = useState(true)
  const [loaded, setLoaded] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [attempt, setAttempt] = useState(0)
  useEffect(() => {
    let active = true
    setLoading(true); setLoaded(false); setError('')
    apiFetch(endpoint, { method: 'GET', cache: 'no-store' }).then(readJsonResponse).then(result => {
      if (!active) return
      const data = result.data
      if (!themeColorFields.every(({ key }) => isThemeColor(data?.colors?.[key]) && isThemeColor(data?.defaults?.[key]))) throw new Error('Appearance settings could not be read safely. Please retry.')
      setSaved(data.colors); setDraft(data.colors); setDefaults(data.defaults); setLoaded(true)
    }).catch(err => { if (active) setError(err.message || 'Could not load appearance settings.') })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [attempt])
  const invalid = themeColorFields.filter(({ key }) => !isThemeColor(draft[key]))
  const changed = themeColorFields.some(({ key }) => draft[key] !== saved[key])
  const colors = Object.fromEntries(themeColorFields.map(({ key }) => [key, isThemeColor(draft[key]) ? draft[key].trim() : saved[key]]))
  const checks = [
    { label: 'Main text on cards', ratio: contrastRatio(colors.text, colors.surface) },
    { label: 'Main text on page background', ratio: contrastRatio(colors.text, colors.background) },
    { label: 'Secondary text on cards', ratio: contrastRatio(colors.muted, colors.surface) },
    { label: 'Accent links on cards', ratio: contrastRatio(colors.primary, colors.surface) },
  ]
  const update = (key, value) => { setDraft(current => ({ ...current, [key]: value })); setMessage('') }
  const save = async event => {
    event.preventDefault()
    if (!loaded || saving || invalid.length || !changed) return
    setSaving(true); setError(''); setMessage('')
    try {
      const response = await apiFetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ colors }) })
      const result = await readJsonResponse(response)
      setSaved(result.data.colors); setDraft(result.data.colors)
      setMessage('Colours saved. Public website integration will be added in Step 2.')
    } catch (err) { setError(err.message || 'Could not save appearance settings.') }
    finally { setSaving(false) }
  }
  return <div className="appearance-panel">
    <div className="appearance-heading"><span className="appearance-heading-icon"><Palette size={23} /></span><div><h2>Website Appearance</h2><p>Choose the website palette and review it before saving.</p></div><span className="appearance-step">Step 1 of 2</span></div>
    <div className="appearance-stage-note"><Eye size={17} /><p>These controls save your palette. Public pages will start using it after Step 2 is installed.</p></div>
    {error && <div role="alert" className="appearance-error">{error}{!loaded && !loading && <Button variant="outline" onClick={() => setAttempt(value => value + 1)}><RefreshCw size={15} />Retry</Button>}</div>}
    {message && <p role="status" className="appearance-success">{message}</p>}
    {loading ? <p role="status" className="appearance-loading">Loading appearance settings…</p> : loaded && <form onSubmit={save}>
      <div className="appearance-layout">
        <div className="appearance-colours"><div className="appearance-section-heading"><h3>Colour palette</h3><span>{changed ? 'Unsaved changes' : 'Saved palette'}</span></div>
          <div className="appearance-colour-grid">{themeColorFields.map(({ key, label, description }) => <div className="appearance-colour-field" key={key}>
            <label htmlFor={`theme-${key}`}>{label}</label><p>{description}</p>
            <div className={`appearance-colour-input ${!isThemeColor(draft[key]) ? 'is-invalid' : ''}`}>
              <input type="color" value={colors[key]} disabled={saving} aria-label={`${label} picker`} onChange={event => update(key, event.target.value.toUpperCase())} />
              <input id={`theme-${key}`} type="text" maxLength={7} spellCheck="false" autoComplete="off" disabled={saving} value={draft[key]} aria-invalid={!isThemeColor(draft[key])} onChange={event => update(key, event.target.value.toUpperCase())} />
              <span aria-hidden="true">HEX</span>
            </div>{!isThemeColor(draft[key]) && <span className="appearance-field-error">Use # followed by six HEX digits.</span>}
          </div>)}</div>
        </div>
        <div className="appearance-preview-column"><div className="appearance-section-heading"><h3>Live preview</h3><span>Sample layout</span></div>
          <div className="appearance-site-preview" style={{ background: colors.background, color: colors.text, borderColor: colors.border }}>
            <div className="appearance-preview-nav" style={{ background: colors.surface, borderColor: colors.border }}><strong>TULIP <small>GUEST INN</small></strong><span style={{ color: colors.primary }}>Home · Rooms · Contact</span></div>
            <div className="appearance-preview-hero" style={{ background: colors.secondary, color: readableText(colors.secondary) }}><span style={{ color: readableText(colors.secondary) }}>YOUR NEXT GETAWAY</span><h4>Feel at home.<br />Stay at Tulip.</h4><p>Comfort, hospitality and a peaceful stay.</p><span className="appearance-sample-button" style={{ background: colors.primary, color: readableText(colors.primary) }}>Book your stay</span></div>
            <div className="appearance-preview-body"><div className="appearance-preview-card" style={{ background: colors.surface, borderColor: colors.border }}><span className="appearance-preview-eyebrow" style={{ color: colors.primary }}>ROOM PREVIEW</span><h4>A comfortable stay</h4><p style={{ color: colors.muted }}>Supporting text, room information and a clean card layout.</p><div className="appearance-preview-input" style={{ borderColor: colors.border, color: colors.muted }}>Check-in date</div><span style={{ color: colors.primary, textDecoration: 'underline' }}>Explore room details →</span></div></div>
            <div className="appearance-preview-footer" style={{ background: colors.footer, color: readableText(colors.footer) }}><strong>Tulip Guest Inn</strong><p>Contact · Policies · Guest information</p></div>
          </div>
          <div className="appearance-hover-preview"><span>Generated hover shade</span><span style={{ background: shade(colors.primary, -0.15) }} /><code>{shade(colors.primary, -0.15)}</code></div>
          <div className="appearance-contrast"><h3>Readability check</h3><p>For ordinary text, aim for a contrast ratio of at least 4.5:1. This checks these colour pairs only.</p>{checks.map(check => <div key={check.label}>{check.ratio >= 4.5 ? <CheckCircle2 size={15} className="appearance-pass" /> : <AlertCircle size={15} className="appearance-warning" />}<span>{check.label}</span><strong>{check.ratio.toFixed(2)}:1</strong></div>)}</div>
        </div>
      </div>
      <div className="appearance-actions"><p>Reset Changes restores your last saved palette. Restore Default Theme loads the original Tulip colours into the preview.</p><div>
        <Button type="button" variant="outline" disabled={saving || !changed} onClick={() => { setDraft(saved); setMessage(''); setError('') }}><RotateCcw size={16} />Reset Changes</Button>
        <Button type="button" variant="outline" disabled={saving} onClick={() => { setDraft(defaults); setMessage('Default colours loaded into preview. Click Save Settings to store them.') }}><Palette size={16} />Restore Default Theme</Button>
        <Button type="submit" disabled={saving || !changed || invalid.length > 0}><Save size={16} />{saving ? 'Saving…' : 'Save Settings'}</Button>
      </div></div>
    </form>}
  </div>
}
