import { useEffect, useState } from 'react';
import { requestJson } from '../../services/apiClient';
type FAQ = { question: string; answer: string };
export default function HotelFaqSection() {
 const [faqs,setFaqs]=useState<FAQ[]>([]);
 useEffect(()=>{
  let active=true;
  requestJson<FAQ[]>('/settings/faqs.php').then(data=>{if(active&&Array.isArray(data))setFaqs(data)}).catch(()=>{});
  return ()=>{active=false};
 },[]);
 if(!faqs.length)return null;
 return <section className="section-padding bg-white" aria-labelledby="hotel-faq-heading"><div className="container-custom">
  <h2 id="hotel-faq-heading" className="mb-8 font-serif text-3xl text-dark">Frequently Asked Questions</h2>
  <div className="mx-auto max-w-4xl space-y-3">{faqs.map((faq,i)=><details key={`${i}-${faq.question}`} className="group rounded-lg border border-border bg-white p-5">
   <summary className="cursor-pointer list-none font-semibold text-dark">{faq.question}<span aria-hidden="true" className="float-right text-gold">+</span></summary>
   <p className="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-gray-600">{faq.answer}</p>
  </details>)}</div>
 </div></section>;
}
