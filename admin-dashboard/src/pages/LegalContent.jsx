import { useEffect, useState } from 'react'
import { FileText, Send } from 'lucide-react'
import { SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'
import LegalRichTextEditor from '@/components/ui/LegalRichTextEditor'

const url=buildApiUrl('/settings/legal-content.php')
const titles={terms:'Terms & Conditions',privacy:'Privacy Policy',refund:'Refund Policy'}
const escapeHtml=text=>String(text||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
function legacyHtml(content) {
 if(!content)return ''
 return `<p>${escapeHtml(content.intro).replace(/\n/g,'<br>')}</p>`+
  (content.sections||[]).map(s=>`<h2>${escapeHtml(s.title)}</h2><p>${escapeHtml(s.body).replace(/\n/g,'<br>')}</p>`).join('')
}
export default function LegalContent({page='terms'}){
 const [html,setHtml]=useState(''),[busy,setBusy]=useState(false),[loading,setLoading]=useState(true)
 const [error,setError]=useState(''),[message,setMessage]=useState(''),[isCustom,setIsCustom]=useState(false)
 useEffect(()=>{
  let active=true;setLoading(true);setError('');setMessage('')
  apiFetch(`${url}?page=${page}`).then(readJsonResponse).then(result=>{
   if(!active)return
   const data=result.data?.content
   setIsCustom(Boolean(data))
   setHtml(data?.html ?? legacyHtml(data))
  }).catch(e=>{if(active)setError(e.message||'Could not load content.')})
   .finally(()=>{if(active)setLoading(false)})
  return()=>{active=false}
 },[page])
 async function publish(){
  if(!html.replace(/<[^>]+>/g,'').trim()){setError('Enter page content before publishing.');return}
  setBusy(true);setError('');setMessage('')
  try{
   const response=await apiFetch(url,{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({page,content:{html}})})
   await readJsonResponse(response);setIsCustom(true);setMessage(`${titles[page]} published successfully.`)
  }catch(e){setError(e.message||'Publishing failed.')}finally{setBusy(false)}
 }
 return <div className="space-y-6">
  <SectionCard>
   <div className="flex items-start gap-3"><div className="rounded-xl bg-blue-50 p-3 text-blue-600"><FileText className="h-5 w-5"/></div>
    <div><h2 className="text-lg font-semibold">{titles[page]}</h2>
     <p className="mt-1 text-sm text-text-secondary">Format this page with headings, bold, italic and lists. Publish changes directly to the public website.</p></div>
   </div>
  </SectionCard>
  {error&&<p role="alert" className="rounded-xl bg-red-50 p-4 text-sm text-red-700">{error}</p>}
  {message&&<p role="status" className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{message}</p>}
  {loading?<SectionCard>Loading content...</SectionCard>:
   <SectionCard>
    <p className="mb-4 text-sm text-text-secondary">{isCustom?'Editing the currently published page.':'No custom content published yet. The public page is using its existing default text. Enter the complete replacement before publishing.'}</p>
    <LegalRichTextEditor key={page} value={html} onChange={setHtml}/>
    <div className="mt-5 flex justify-end"><Button disabled={busy} onClick={publish}><Send className="h-4 w-4"/>{busy?'Publishing...':'Publish'}</Button></div>
   </SectionCard>}
 </div>
}
