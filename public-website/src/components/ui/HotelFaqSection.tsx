import { useEffect, useState } from 'react';
import { requestJson } from '../../services/apiClient';
type FAQ = { question: string; answer: string };
export default function HotelFaqSection() {
 const [faqs,setFaqs]=useState<FAQ[]>([]);
 const [openIndex,setOpenIndex]=useState<number|null>(null);
 useEffect(()=>{
  let active=true;
  requestJson<FAQ[]>('/settings/faqs.php').then(data=>{if(active&&Array.isArray(data))setFaqs(data)}).catch(()=>{});
  return ()=>{active=false};
 },[]);
 if(!faqs.length)return null;
 return <section className="section-padding bg-surface" aria-labelledby="hotel-faq-heading"><div className="container-custom">
  <h2 id="hotel-faq-heading" className="mb-8 font-serif text-3xl text-dark">Frequently Asked Questions</h2>
  <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-2">{faqs.slice(0,8).map((faq,i)=><div key={`${i}-${faq.question}`} className="rounded-lg border border-border bg-surface p-5">
   <h3><button type="button" aria-expanded={openIndex===i} aria-controls={`faq-answer-${i}`} onClick={()=>setOpenIndex(current=>current===i?null:i)} className="flex w-full items-center justify-between gap-4 text-left font-semibold text-dark">
    <span>{faq.question}</span><span aria-hidden="true" className="shrink-0 text-gold">{openIndex===i?'−':'+'}</span>
   </button></h3>
   {openIndex===i&&<p id={`faq-answer-${i}`} className="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-muted">{faq.answer}</p>}
  </div>)}</div>
 </div></section>;
}
