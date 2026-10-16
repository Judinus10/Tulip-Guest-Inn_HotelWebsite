import { useEffect, useState } from 'react';
import { requestJson } from '../services/apiClient';

export type HotelPolicies = Record<'check_in'|'check_out'|'cancellation'|'children'|'extra_beds'|'minimum_age'|'pets'|'smoking'|'payments', string>;
export const defaultHotelPolicies: HotelPolicies = {
  check_in:'Available 24 hours', check_out:'From 06:00 to 12:00',
  cancellation:'Cancellation and prepayment conditions depend on your selected room and booking terms.',
  children:'Children of all ages are welcome.', extra_beds:'Cots and extra beds are not available.',
  minimum_age:'No minimum age restriction.', pets:'Pets are not allowed.',
  smoking:'Smoking is not allowed.',
  payments:'Payment options are shown during checkout. Online payments are subject to availability.',
};
let cached: HotelPolicies | null = null;
export function useHotelPolicies() {
  const [policies,setPolicies] = useState<HotelPolicies>(cached || defaultHotelPolicies);
  useEffect(()=>{
    let active=true;
    requestJson<Partial<HotelPolicies>>('/settings/policies.php')
      .then(data=>{if(active){cached={...defaultHotelPolicies,...data};setPolicies(cached);}})
      .catch(()=>{});
    return ()=>{active=false;};
  },[]);
  return policies;
}
