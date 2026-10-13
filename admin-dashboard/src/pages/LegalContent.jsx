import { useEffect, useState } from 'react'
import { FileText, Plus, Trash2, Send } from 'lucide-react'
import { SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'
const url=buildApiUrl('/settings/legal-content.php')
const empty={intro:'',sections:[]}
const titles={terms:'Terms & Conditions',privacy:'Privacy Policy'}
export default function LegalContent(){
 const [page,setPage]=useState('terms'),[content,setContent]=useState(empty),[busy,setBusy]=useState(false),[loading,setLoading]=useState(true),[error,setError]=useState(''),[message,setMessage]=useState(''),[isCustom,setIsCustom]=useState(false)
 useEffect(()=>{let active=true;setLoading(true);setError('');setMessage('');
  apiFetch(`${url}?page=${page}`).then(readJsonResponse).then(result=>{if(!active)return;setIsCustom(Boolean(result.data?.content));setContent(result.data?.content||empty)}).catch(e=>{if(active)setError(e.message||'Could not load content.')}).finally(()=>{if(active)setLoading(false)})
  return()=>{active=false}
 },[page])
 const updateSection=(index,key,value)=>setContent(prev=>({...prev,sections:prev.sections.map((item,i)=>i===index?{...item,[key]:value}:item)}))
 async function publish(){setBusy(true);setError('');setMessage('');try{
  const response=await apiFetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({page,content})});await readJsonResponse(response);setIsCustom(true);setMessage(`${titles[page]} published successfully.`)
 }catch(e){setError(e.message||'Publishing failed.')}finally{setBusy(false)}}
 return <div className="space-y-6">
  <SectionCard><div className="mb-5 flex items-start gap-3"><div className="rounded-xl bg-blue-50 p-3 text-blue-600"><FileText className="h-5 w-5"/></div><div><h2 className="text-lg font-semibold">Terms & Privacy</h2><p className="mt-1 text-sm text-text-secondary">Edit the legal pages shown on the public website.</p></div></div>
   <div className="flex flex-wrap gap-2">{Object.entries(titles).map(([key,title])=><button key={key} onClick={()=>setPage(key)} className={`rounded-lg px-4 py-2 text-sm font-semibold ${page===key?'bg-blue-600 text-white':'border border-border bg-white text-text-secondary'}`}>{title}</button>)}</div>
  </SectionCard>
  {error&&<p role="alert" className="rounded-xl bg-red-50 p-4 text-sm text-red-700">{error}</p>}{message&&<p role="status" className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{message}</p>}
  {loading?<SectionCard>Loading content...</SectionCard>:<SectionCard><div className="mb-5"><h3 className="text-lg font-semibold">{titles[page]}</h3><p className="mt-1 text-sm text-text-secondary">{isCustom?'Published content loaded.':'The public website is using its existing default text. To replace it, enter the complete approved content below.'}</p></div>
   <label className="block text-sm font-semibold">Introduction<textarea value={content.intro} maxLength={4000} rows={3} onChange={e=>setContent(p=>({...p,intro:e.target.value}))} className="mt-2 w-full rounded-xl border border-border p-3 text-sm"/></label>
   <div className="mt-5 space-y-4">{content.sections.map((section,index)=><div key={index} className="rounded-xl border border-border p-4"><div className="mb-3 flex justify-between"><p className="text-sm font-semibold">Section {index+1}</p><button aria-label="Remove section" onClick={()=>setContent(p=>({...p,sections:p.sections.filter((_,i)=>i!==index)}))} className="text-red-600"><Trash2 className="h-4 w-4"/></button></div><label className="block text-sm font-semibold">Heading<input value={section.title} maxLength={200} onChange={e=>updateSection(index,'title',e.target.value)} className="mt-2 w-full rounded-lg border border-border p-3"/></label><label className="mt-3 block text-sm font-semibold">Content<textarea value={section.body} maxLength={10000} rows={5} onChange={e=>updateSection(index,'body',e.target.value)} className="mt-2 w-full rounded-lg border border-border p-3 text-sm"/></label></div>)}</div>
   <div className="mt-5 flex flex-wrap items-center justify-between gap-3"><Button variant="outline" disabled={busy||content.sections.length>=40} onClick={()=>setContent(p=>({...p,sections:[...p.sections,{title:'',body:''}]}))}><Plus className="h-4 w-4"/> Add Section</Button><Button disabled={busy||content.sections.length===0} onClick={publish}><Send className="h-4 w-4"/> {busy?'Publishing...':'Publish'}</Button></div>
  </SectionCard>}
 </div>
}
