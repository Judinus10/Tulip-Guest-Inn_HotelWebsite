import { useEffect, useRef, useState } from 'react'
import { Send } from 'lucide-react'
import { SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'
import { notifyError, notifySuccess } from '@/utils/notifications'
import LegalRichTextEditor from '@/components/ui/LegalRichTextEditor'

const url = buildApiUrl('/settings/legal-content.php')
const titles = { terms: 'Terms & Conditions', privacy: 'Privacy Policy', refund: 'Refund Policy' }
const escapeHtml = text => String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
function legacyHtml(content) {
  if (!content) return ''
  return `<p>${escapeHtml(content.intro).replace(/\n/g, '<br>')}</p>` +
    (content.sections || []).map(s => `<h2>${escapeHtml(s.title)}</h2><p>${escapeHtml(s.body).replace(/\n/g, '<br>')}</p>`).join('')
}

export default function LegalContent({ page = 'terms', onDirtyChange }) {
  const [html, setHtml] = useState('')
  const [savedHtml, setSavedHtml] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(false)
  const [retry, setRetry] = useState(0)
  const [isCustom, setIsCustom] = useState(false)
  const mounted = useRef(true)
  const publishing = useRef(false)
  const dirty = !loading && !loadError && html !== savedHtml

  useEffect(() => { mounted.current = true; return () => { mounted.current = false } }, [])
  useEffect(() => { onDirtyChange?.(page, dirty) }, [page, dirty, onDirtyChange])
  useEffect(() => {
    if (!dirty) return undefined
    const warn = event => { event.preventDefault(); event.returnValue = '' }
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [dirty])
  useEffect(() => {
    let active = true
    setLoading(true); setLoadError(false)
    apiFetch(`${url}?page=${page}`, { cache: 'no-store' }).then(readJsonResponse).then(result => {
      if (!active) return
      const data = result.data?.content
      const content = data?.html ?? legacyHtml(data)
      setIsCustom(Boolean(data)); setHtml(content); setSavedHtml(content)
    }).catch(e => {
      if (active) { setLoadError(true); notifyError(e, { duration: 6000 }) }
    }).finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [page, retry])

  async function publish() {
    if (publishing.current || loading || loadError) return
    if (!html.replace(/<[^>]+>/g, '').replace(/&nbsp;/gi, ' ').trim()) {
      notifyError({ message: 'Enter page content before publishing.' }, { duration: 6000 }); return
    }
    const submittedHtml = html
    publishing.current = true; setBusy(true)
    try {
      const response = await apiFetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ page, content: { html: submittedHtml } }) })
      await readJsonResponse(response)
      if (mounted.current) {
        setIsCustom(true); setSavedHtml(submittedHtml)
        notifySuccess(`${titles[page]} published successfully.`, { duration: 4000 })
      }
    } catch (e) { if (mounted.current) notifyError(e, { duration: 6000 }) }
    finally { publishing.current = false; if (mounted.current) setBusy(false) }
  }

  return <div className="space-y-6">
    {loading ? <SectionCard>Loading content...</SectionCard> : loadError ? <SectionCard>
      <p className="mb-3 text-sm text-text-secondary">This policy could not be loaded.</p>
      <Button variant="outline" onClick={() => setRetry(value => value + 1)}>Retry</Button>
    </SectionCard> : <SectionCard>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-text-secondary">{isCustom ? 'Editing the currently published page.' : 'No custom content published yet. The public page is using its existing default text. Enter the complete replacement before publishing.'}</p>
        {dirty && <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Unsaved changes</span>}
      </div>
      <LegalRichTextEditor key={page} value={html} onChange={setHtml} />
      <div className="mt-5 flex justify-end"><Button disabled={busy} onClick={publish}><Send className="h-4 w-4" />{busy ? 'Publishing...' : 'Publish'}</Button></div>
    </SectionCard>}
  </div>
}
