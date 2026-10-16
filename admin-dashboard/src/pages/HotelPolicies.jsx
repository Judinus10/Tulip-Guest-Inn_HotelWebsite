import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, Send, ClipboardList, Loader2 } from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const url = buildApiUrl('/settings/policies.php')
const fields = [
  ['cancellation','Cancellation & prepayment','State the actual booking conditions; do not promise free cancellation unless offered.'],
  ['children','Children','Child age and occupancy policy'],
  ['extra_beds','Cots & extra beds','Availability and restrictions'],
  ['minimum_age','Minimum check-in age','Age restriction or no restriction'],
  ['pets','Pets','Pet policy'],
  ['smoking','Smoking','Smoking policy'],
  ['payments','Payment methods','Clarify payment options for direct bookings'],
]
export default function HotelPolicies({ embedded = false }) {
 const [draft,setDraft]=useState(null),[saved,setSaved]=useState(null)
 const [busy,setBusy]=useState(false),[error,setError]=useState(''),[message,setMessage]=useState('')
 async function refresh() {
  const response=await apiFetch(`${url}?admin=1`)
  const payload=await readJsonResponse(response)
  setDraft(payload.data.published);setSaved(payload.data.published)
 }
 useEffect(()=>{refresh().catch(e=>setError(e.message))},[])
 async function action(type) {
  setBusy(true);setError('');setMessage('')
  try {
   const response=await apiFetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'publish_now',policies:draft})})
   await readJsonResponse(response);await refresh()
   setMessage('Changes published successfully.')
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
    <div className="mb-6 flex items-start gap-3"><div className="rounded-xl bg-blue-50 p-3 text-blue-600"><ClipboardList className="h-5 w-5"/></div><div><h2 className="text-lg font-semibold">Property Rules</h2><p className="mt-1 text-sm text-text-secondary">Changes appear on the public website when you click Publish.</p></div></div>
    <div className="grid gap-5 md:grid-cols-2">
     {fields.map(([key,label,hint])=><label key={key} className={`block space-y-2 ${['cancellation','payments'].includes(key)?'md:col-span-2':''}`}>
      <span className="text-sm font-semibold text-text-primary">{label}</span>
      <textarea rows={['cancellation','payments'].includes(key)?3:2} maxLength={2000} value={draft[key]||''} onChange={e=>setDraft(p=>({...p,[key]:e.target.value}))} className="w-full resize-y rounded-xl border border-border bg-white px-4 py-3 text-sm outline-none focus:border-blue-500"/>
      <span className="block text-xs text-text-secondary">{hint}</span>
     </label>)}
    </div>
   </SectionCard>

   <div className="flex justify-end rounded-2xl border border-border bg-white p-4">
    <Button disabled={busy} onClick={()=>action('publish_now')}><Send className="h-4 w-4"/> {busy?'Publishing...':'Publish'}</Button>
   </div>
  </>}
 </div>
}
