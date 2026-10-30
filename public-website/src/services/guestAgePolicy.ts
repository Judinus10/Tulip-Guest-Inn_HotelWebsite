import { useEffect, useSyncExternalStore } from 'react';
import { requestJson } from './apiClient';

type GuestAgePolicy = { maxChildAge: number | null; loading: boolean; error: string };
let snapshot: GuestAgePolicy = { maxChildAge: null, loading: true, error: '' };
let pending: Promise<void> | null = null;
const listeners = new Set<() => void>();
const subscribe = (listener: () => void) => { listeners.add(listener); return () => { listeners.delete(listener); }; };
const getSnapshot = () => snapshot;
function publish(value: GuestAgePolicy) { snapshot = value; listeners.forEach(listener => listener()); }
export function loadGuestAgePolicy(retry = false): Promise<void> {
  if (pending) return pending;
  if (!retry && (!snapshot.loading || snapshot.maxChildAge !== null)) return Promise.resolve();
  publish({ maxChildAge: null, loading: true, error: '' });
  pending = requestJson<{ max_child_age: number }>('/rooms/public-occupancy.php', { cache: 'no-store' })
    .then(data => {
      if (!Number.isInteger(data.max_child_age) || data.max_child_age < 0 || data.max_child_age > 17) throw new Error('Invalid guest age policy.');
      publish({ maxChildAge: data.max_child_age, loading: false, error: '' });
    }).catch(() => {
      publish({ maxChildAge: null, loading: false, error: 'Guest age details could not be loaded. Please retry before booking.' });
    }).finally(() => { pending = null; });
  return pending;
}
export function useGuestAgePolicy() {
  const policy = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
  useEffect(() => { void loadGuestAgePolicy(); }, []);
  return { ...policy, retry: () => { void loadGuestAgePolicy(true); } };
}
