import { useGuestAgePolicy } from '../../services/guestAgePolicy';
import { useId } from 'react';
import { partyError, type GuestParty } from '../../services/guestOccupancy';
export default function GuestPartyFields({ value, onChange, allowZeroAdults=false, disabled=false, compact=false }: { value: GuestParty; onChange: (party: GuestParty) => void; allowZeroAdults?: boolean; disabled?: boolean; compact?: boolean }) {
  const { maxChildAge, loading, error: policyError, retry } = useGuestAgePolicy();
  const id=useId(); const error=maxChildAge === null ? '' : partyError(value,allowZeroAdults,maxChildAge);
  const field='w-full border border-border bg-surface px-3 py-2 text-sm text-dark focus:outline-none focus:ring-1 focus:ring-gold';
  const countField=compact ? 'w-[3.25rem] shrink-0 border border-border bg-surface px-2 py-1.5 text-sm text-dark focus:outline-none focus:ring-1 focus:ring-gold' : field;
  const countLabel=compact ? 'inline-flex items-center gap-2 whitespace-nowrap text-xs text-muted' : 'text-xs text-muted';
  const change=(adults:number,children:number) => onChange({adults,children,children_ages:[]});
  return <fieldset disabled={disabled} className="space-y-3 min-w-0" aria-busy={loading}><legend className="sr-only">Adults and children</legend>
    {loading && <p role="status" className="text-xs text-muted">Loading guest age details...</p>}
    {policyError && <p role="alert" className="text-xs text-red-600">{policyError} <button type="button" onClick={retry} className="underline">Retry</button></p>}
    <div className={compact ? 'flex flex-wrap items-center gap-x-4 gap-y-2' : 'grid grid-cols-2 gap-3'}>
      <label htmlFor={id+'adults'} className={countLabel}><span>Adults{maxChildAge !== null ? ` (${maxChildAge + 1}+)` : ''}</span><select id={id+'adults'} className={countField} disabled={maxChildAge === null} value={value.adults} onChange={e=>change(Number(e.target.value),value.children)}>{Array.from({length:21-(allowZeroAdults?0:1)},(_,i)=>i+(allowZeroAdults?0:1)).map(n=><option key={n} value={n}>{n}</option>)}</select></label>
      <label htmlFor={id+'children'} className={countLabel}><span>Children{maxChildAge !== null ? ` (0–${maxChildAge})` : ''}</span><select id={id+'children'} className={countField} disabled={maxChildAge === null} value={value.children} onChange={e=>change(value.adults,Number(e.target.value))}>{Array.from({length:20},(_,i)=>i).map(n=><option key={n} value={n}>{n}</option>)}</select></label>
    </div>
    {error && <p role="status" className="text-xs text-red-600">{error}</p>}
  </fieldset>;
}
