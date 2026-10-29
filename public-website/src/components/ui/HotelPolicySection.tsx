import { useHotelPolicies } from '../../hooks/useHotelPolicies';
const labels: [string,string][] = [
 ['check_in','Check-in'],['check_out','Check-out'],['cancellation','Cancellation & prepayment'],
 ['children','Children'],['extra_beds','Cots & extra beds'],['minimum_age','Minimum age'],
 ['pets','Pets'],['smoking','Smoking'],['payments','Payment methods'],
];
export default function HotelPolicySection({compact=false}:{compact?:boolean}) {
 const policies=useHotelPolicies();
 const visible=compact?labels.filter(([key])=>['check_in','check_out','cancellation','payments'].includes(key)):labels;
 return <section aria-labelledby={compact?'booking-policies':'hotel-policies'} className={compact?'border border-border p-5':'section-padding bg-surface'}>
  <div className={compact?'':'container-custom'}>
   <h2 id={compact?'booking-policies':'hotel-policies'} className={compact?'mb-4 font-serif text-xl text-dark':'mb-8 font-serif text-3xl text-dark'}>{compact?'Important booking policies':'House Rules & Hotel Policies'}</h2>
   <dl className={compact?'space-y-4':'grid gap-5 md:grid-cols-2'}>
    {visible.map(([key,label])=><div key={key} className={compact?'border-b border-border pb-3 last:border-0':'border border-border p-5'}>
     <dt className="text-xs font-semibold uppercase tracking-wider text-dark">{label}</dt>
     <dd className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-muted">{policies[key as keyof typeof policies]}</dd>
    </div>)}
   </dl>
  </div>
 </section>;
}
