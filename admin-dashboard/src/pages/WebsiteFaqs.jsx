import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, ArrowDown, ArrowUp, Plus, Save, Send, RotateCcw, Trash2, HelpCircle } from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const url=buildApiUrl('/settings/faqs.php')
const equal=(a,b)=>JSON.stringify(a)===JSON.stringify(b)
export default function WebsiteFaqs({ embedded = false }) {
 const [draft,setDraft]=useState(null),[saved,setSaved]=useState(null)
 const [busy,setBusy]=useState(false),[message,setMessage]=useState(''),[error,setError]=useState('')
 const dirty=!equal(draft,saved)
 async function refresh(){
  const response=await apiFetch(`${url}?admin=1`)
  const payload=await readJsonResponse(response)
  setDraft(payload.data.draft);setSaved(payload.data.draft)
 }
 useEffect(()=>{refresh().catch(e=>setError(e.message))},[])
 function edit(index,field,value){setDraft(old=>old.map((item,i)=>i===index?{...item,[field]:value}:item))}
 function move(index,delta){setDraft(old=>{const next=[...old],target=index+delta;if(target<0||target>=next.length)return old;[next[index],next[target]]=[next[target],next[index]];return next})}
 async function action(kind){
  setBusy(true);setError('');setMessage('')
  try{
   const response=await apiFetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:kind,faqs:draft})})
   await readJsonResponse(response);await refresh()
   setMessage(kind==='publish'?'FAQs published successfully.':kind==='draft'?'FAQ draft saved.':'Draft discarded.')
  }catch(e){setError(e.message||'Unable to save FAQs.')}finally{setBusy(false)}
 }
 return <div className="space-y-6">
  {!embedded && (<PageHeader title="Frequently Asked Questions" description="Manage guest questions and answers for the About page.">
   <Link to="/website-settings" className="inline-flex items-center gap-2 rounded-lg border border-border bg-white px-3 py-2 text-sm"><ArrowLeft className="h-4 w-4"/> Website Settings</Link>
  </PageHeader>)}
  {error&&<p role="alert" className="rounded-xl bg-red-50 p-4 text-sm text-red-700">{error}</p>}
  {message&&<p role="status" className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{message}</p>}
  {draft===null?<SectionCard>Loading FAQs...</SectionCard>:<>
   <SectionCard>
    <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
     <div className="flex items-center gap-3"><div className="rounded-xl bg-blue-50 p-3 text-blue-600"><HelpCircle className="h-5 w-5"/></div><div><h2 className="text-lg font-semibold">FAQ Entries</h2><p className="text-sm text-text-secondary">Up to 30 entries. Only published answers are public.</p></div></div>
     <Button disabled={busy||draft.length>=30} onClick={()=>setDraft(old=>[...old,{question:'',answer:''}])}><Plus className="h-4 w-4"/> Add FAQ</Button>
    </div>
    <div className="space-y-4">{draft.length===0&&<div className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-text-secondary">No FAQs yet. Add your first question.</div>}
     {draft.map((item,index)=><div key={index} className="rounded-xl border border-border p-4">
      <div className="mb-3 flex items-center justify-between"><span className="text-sm font-semibold">Question {index+1}</span><div className="flex items-center gap-2">
       <button aria-label="Move up" disabled={index===0} onClick={()=>move(index,-1)} className="rounded-lg border p-2 disabled:opacity-30"><ArrowUp className="h-4 w-4"/></button>
       <button aria-label="Move down" disabled={index===draft.length-1} onClick={()=>move(index,1)} className="rounded-lg border p-2 disabled:opacity-30"><ArrowDown className="h-4 w-4"/></button>
       <button aria-label="Delete FAQ" onClick={()=>setDraft(old=>old.filter((_,i)=>i!==index))} className="rounded-lg border border-red-100 p-2 text-red-600"><Trash2 className="h-4 w-4"/></button>
      </div></div>
      <label className="block text-sm font-medium">Question<input maxLength={250} value={item.question} onChange={e=>edit(index,'question',e.target.value)} className="mt-2 w-full rounded-lg border border-border px-3 py-2"/></label>
      <label className="mt-4 block text-sm font-medium">Answer<textarea maxLength={2000} rows={3} value={item.answer} onChange={e=>edit(index,'answer',e.target.value)} className="mt-2 w-full rounded-lg border border-border px-3 py-2"/></label>
     </div>)}
    </div>
   </SectionCard>

   <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-white p-4"><p className="text-sm text-text-secondary">{dirty?'Unsaved edits. Save before publishing.':'Draft saved.'}</p><div className="flex flex-wrap gap-2">
    <Button variant="outline" disabled={busy} onClick={()=>action('discard')}><RotateCcw className="h-4 w-4"/> Discard Draft</Button>
    <Button variant="outline" disabled={busy||!dirty} onClick={()=>action('draft')}><Save className="h-4 w-4"/> Save Draft</Button>
    <Button disabled={busy||dirty} onClick={()=>action('publish')}><Send className="h-4 w-4"/> Publish Draft</Button>
   </div></div>
  </>}
 </div>
}
