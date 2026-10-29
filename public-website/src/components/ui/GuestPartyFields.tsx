import { useId } from 'react';
import { partyError, type GuestParty } from '../../services/guestOccupancy';
export default function GuestPartyFields({ value, onChange, allowZeroAdults=false, disabled=false }: { value: GuestParty; onChange: (party: GuestParty) => void; allowZeroAdults?: boolean; disabled?: boolean }) {
  const id=useId(); const error=partyError(value,allowZeroAdults);
  const field='w-full border border-border bg-surface px-3 py-2 text-sm text-dark focus:outline-none focus:ring-1 focus:ring-gold';
  const change=(adults:number,children:number) => onChange({adults,children,children_ages:Array.from({length:children},(_,i)=>value.children_ages[i]??'')});
  return <fieldset disabled={disabled} className="space-y-3 min-w-0"><legend className="sr-only">Adults, children and ages at check-in</legend>
    <div className="grid grid-cols-2 gap-3">
      <label htmlFor={id+'adults'} className="text-xs text-muted">Adults (18+)<select id={id+'adults'} className={field} value={value.adults} onChange={e=>change(Number(e.target.value),value.children)}>{Array.from({length:21-(allowZeroAdults?0:1)},(_,i)=>i+(allowZeroAdults?0:1)).map(n=><option key={n} value={n}>{n}</option>)}</select></label>
      <label htmlFor={id+'children'} className="text-xs text-muted">Children (0–17)<select id={id+'children'} className={field} value={value.children} onChange={e=>change(value.adults,Number(e.target.value))}>{Array.from({length:20},(_,i)=>i).map(n=><option key={n} value={n}>{n}</option>)}</select></label>
    </div>
    {value.children>0 && <div className="rounded border border-border bg-background p-3"><p className="mb-2 text-xs text-muted">Child ages at check-in. Older children may use adult places under the room policy.</p><div className="grid grid-cols-2 gap-2 sm:grid-cols-3">{Array.from({length:value.children},(_,i)=><label key={i} className="text-xs text-muted" htmlFor={id+'age'+i}>Child {i+1}<select id={id+'age'+i} className={field} value={value.children_ages[i]??''} onChange={e=>onChange({...value,children_ages:Array.from({length:value.children},(_,j)=>j===i?(e.target.value===''?'':Number(e.target.value)):value.children_ages[j]??'')})}><option value="">Select age</option>{Array.from({length:18},(_,n)=><option key={n} value={n}>{n===0?'Under 1':n}</option>)}</select></label>)}</div></div>}
    {error && <p role="status" className="text-xs text-red-600">{error}</p>}
  </fieldset>;
}
