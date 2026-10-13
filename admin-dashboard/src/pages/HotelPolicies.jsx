import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, Save, Send, RotateCcw, ClipboardList, Loader2 } from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const url = buildApiUrl('/settings/policies.php')
const fields = [
  ['check_in','Check-in','Example: Available 24 hours'],
  ['check_out','Check-out','Example: From 06:00 to 12:00'],
  ['cancellation','Cancellation & prepayment','State the actual booking conditions; do not promise free cancellation unless offered.'],
  ['children','Children','Child age and occupancy policy'],
  ['extra_beds','Cots & extra beds','Availability and restrictions'],
  ['minimum_age','Minimum check-in age','Age restriction or no restriction'],
  ['pets','Pets','Pet policy'],
  ['smoking','Smoking','Smoking policy'],
  ['payments','Payment methods','Clarify payment options for direct bookings'],
]
export default function HotelPolicies({ embedded = false }) {
 const [draft,setDraft]=useState(null),[saved,setSaved]=useState(null),[published,setPublished]=useState(null)
 const [busy,setBusy]=useState(false),[error,setError]=useState(''),[message,setMessage]=useState('')
 const [view,setView]=useState('draft')
 const dirty=JSON.stringify(draft)!==JSON.stringify(saved)
 async function refresh() {
  const response=await apiFetch(`${url}?admin=1`)
  const payload=await readJsonResponse(response)
  setDraft(payload.data.draft);setSaved(payload.data.draft);setPublished(payload.data.published)
 }
 useEffect(()=>{refresh().catch(e=>setError(e.message))},[])
 async function action(type) {
  setBusy(true);setError('');setMessage('')
  try {
   const response=await apiFetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:type,policies:draft})})
   await readJsonResponse(response);await refresh()
   setMessage(type==='publish'?'Policies published.':type==='draft'?'Draft saved.':'Draft discarded.')
  } catch(e){setError(e.message||'Could not save policies.')} finally{setBusy(false)}
 }
 return <div className="space-y-6">
  {!embedded && (<PageHeader title="Hotel Policies" description="Manage property rules shown on the About page and during booking.">
   <Link to="/website-settings" className="inline-flex items-center gap-2 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium"><ArrowLeft className="h-4 w-4"/> Website Settings</Link>
  </PageHeader>)}
  {error&&<p role="alert" className="rounded-xl bg-red-50 p-4 text-sm text-red-700">{error}</p>}
  {message&&<p role="status" className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{message}</p>}
  {!draft?<SectionCard><span className="flex items-center gap-2"><Loader2 className="h-4 w-4 animate-spin"/> Loading policies...</span></SectionCard>:<>
   <SectionCard>
    <div className="mb-6 flex items-start gap-3"><div className="rounded-xl bg-blue-50 p-3 text-blue-600"><ClipboardList className="h-5 w-5"/></div><div><h2 className="text-lg font-semibold">Property Rules</h2><p className="mt-1 text-sm text-text-secondary">Changes remain private until you publish the saved draft.</p></div></div>
    <div className="grid gap-5 md:grid-cols-2">
     {fields.map(([key,label,hint])=><label key={key} className={`block space-y-2 ${['cancellation','payments'].includes(key)?'md:col-span-2':''}`}>
      <span className="text-sm font-semibold text-text-primary">{label}</span>
      <textarea rows={['cancellation','payments'].includes(key)?3:2} maxLength={2000} value={draft[key]||''} onChange={e=>setDraft(p=>({...p,[key]:e.target.value}))} className="w-full resize-y rounded-xl border border-border bg-white px-4 py-3 text-sm outline-none focus:border-blue-500"/>
      <span className="block text-xs text-text-secondary">{hint}</span>
     </label>)}
    </div>
   </SectionCard>
   <SectionCard>
    <div className="mb-4 flex items-center justify-between gap-3"><div><h2 className="text-lg font-semibold">Policy Preview</h2><p className="text-sm text-text-secondary">Preview the rules visitors will read.</p></div><div className="flex rounded-lg border p-1">{['draft','published'].map(type=><button key={type} onClick={()=>setView(type)} className={`rounded-md px-3 py-1.5 text-sm capitalize ${view===type?'bg-blue-50 font-semibold text-blue-700':'text-text-secondary'}`}>{type}</button>)}</div></div>
    <div className="grid gap-4 md:grid-cols-2">{fields.map(([key,label])=><div key={key} className="rounded-xl border border-border p-4"><p className="text-sm font-semibold">{label}</p><p className="mt-2 whitespace-pre-wrap text-sm text-text-secondary">{(view==='draft'?draft:published)?.[key]||'Not specified'}</p></div>)}</div>
   </SectionCard>
   <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-white p-4"><p className="text-sm text-text-secondary">{dirty?'Unsaved edits — save before publishing.':'Draft is saved.'}</p><div className="flex flex-wrap gap-2">
    <Button variant="outline" disabled={busy} onClick={()=>action('discard')}><RotateCcw className="h-4 w-4"/> Discard Draft</Button>
    <Button variant="outline" disabled={busy||!dirty} onClick={()=>action('draft')}><Save className="h-4 w-4"/> Save Draft</Button>
    <Button disabled={busy||dirty} onClick={()=>action('publish')}><Send className="h-4 w-4"/> Publish Draft</Button>
   </div></div>
  </>}
 </div>
}
