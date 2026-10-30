import { useGuestAgePolicy } from '../../services/guestAgePolicy';
import { useId } from 'react';
import { partyError, type GuestParty } from '../../services/guestOccupancy';
export default function GuestPartyFields({ value, onChange, allowZeroAdults=false, disabled=false, compact=false }: { value: GuestParty; onChange: (party: GuestParty) => void; allowZeroAdults?: boolean; disabled?: boolean; compact?: boolean }) {
  const { maxChildAge, loading, error: policyError, retry } = useGuestAgePolicy();
  const id=useId(); const error=maxChildAge === null ? '' : partyError(value,allowZeroAdults,maxChildAge);
  const field='w-full border border-border bg-surface px-3 py-2 text-sm text-dark focus:outline-none focus:ring-1 focus:ring-gold';
  const countField=compact ? 'w-[3.25rem] shrink-0 border border-border bg-surface px-2 py-1.5 text-sm text-dark focus:outline-none focus:ring-1 focus:ring-gold' : field;
  const countLabel=compact ? 'inline-flex items-center gap-2 whitespace-nowrap text-xs text-muted' : 'text-xs text-muted';
  const change=(adults:number,children:number) => onChange({adults,children,children_ages:Array.from({length:children},(_,i)=>value.children_ages[i]??'')});
  return <fieldset disabled={disabled} className="space-y-3 min-w-0" aria-busy={loading}><legend className="sr-only">Adults, children and ages at check-in</legend>
    {loading && <p role="status" className="text-xs text-muted">Loading guest age details...</p>}
    {policyError && <p role="alert" className="text-xs text-red-600">{policyError} <button type="button" onClick={retry} className="underline">Retry</button></p>}
    <div className={compact ? 'flex flex-wrap items-center gap-x-4 gap-y-2' : 'grid grid-cols-2 gap-3'}>
      <label htmlFor={id+'adults'} className={countLabel}><span>Adults{maxChildAge !== null ? ` (${maxChildAge + 1}+)` : ''}</span><select id={id+'adults'} className={countField} disabled={maxChildAge === null} value={value.adults} onChange={e=>change(Number(e.target.value),value.children)}>{Array.from({length:21-(allowZeroAdults?0:1)},(_,i)=>i+(allowZeroAdults?0:1)).map(n=><option key={n} value={n}>{n}</option>)}</select></label>
      <label htmlFor={id+'children'} className={countLabel}><span>Children{maxChildAge !== null ? ` (0–${maxChildAge})` : ''}</span><select id={id+'children'} className={countField} disabled={maxChildAge === null} value={value.children} onChange={e=>change(value.adults,Number(e.target.value))}>{Array.from({length:20},(_,i)=>i).map(n=><option key={n} value={n}>{n}</option>)}</select></label>
    </div>
    {value.children>0 && <div className="rounded border border-border bg-background p-3"><p className="mb-2 text-xs text-muted">Child ages at check-in. Guests aged {maxChildAge === null ? '—' : maxChildAge + 1}+ must be included as adults.</p><div className="grid grid-cols-2 gap-2 sm:grid-cols-3">{Array.from({length:value.children},(_,i)=><label key={i} className="text-xs text-muted" htmlFor={id+'age'+i}>Child {i+1}<select id={id+'age'+i} className={field} disabled={maxChildAge === null} value={value.children_ages[i]??''} onChange={e=>onChange({...value,children_ages:Array.from({length:value.children},(_,j)=>j===i?(e.target.value===''?'':Number(e.target.value)):value.children_ages[j]??'')})}><option value="">Select age</option>{value.children_ages[i] !== '' && value.children_ages[i] !== undefined && maxChildAge !== null && Number(value.children_ages[i]) > maxChildAge && <option value={value.children_ages[i]} disabled>{value.children_ages[i]} — select adult</option>}{Array.from({length:maxChildAge === null ? 0 : maxChildAge + 1},(_,n)=><option key={n} value={n}>{n===0?'Under 1':n}</option>)}</select></label>)}</div></div>}
    {error && <p role="status" className="text-xs text-red-600">{error}</p>}
  </fieldset>;
}
