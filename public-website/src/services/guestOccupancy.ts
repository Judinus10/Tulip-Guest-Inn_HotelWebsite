export type GuestParty = { adults: number; children: number; children_ages: Array<number | string> };
export type OccupancyRoom = { id: string | number; guests?: number; capacity?: number; max_guests?: number; max_adults?: number; max_children?: number; max_total_occupants?: number; max_child_age?: number; count_children_toward_capacity?: boolean };
export function partyError(p: GuestParty, allowZeroAdults = false, maxChildAge: number | null = null): string {
  if (maxChildAge === null || !Number.isInteger(maxChildAge) || maxChildAge < 0 || maxChildAge > 17) return 'Guest age policy is unavailable. Please wait or retry.';
  if (!Number.isInteger(p.adults) || p.adults < (allowZeroAdults ? 0 : 1)) return 'At least one adult is required.';
  if (!Number.isInteger(p.children) || p.children < 0 || p.adults + p.children > 20) return 'Choose up to 20 people in total.';
  if (p.children_ages.length !== p.children || p.children_ages.some(a => !((typeof a === 'number' && Number.isInteger(a)) || (typeof a === 'string' && /^(0|[1-9][0-9]*)$/.test(a))) || !Number.isInteger(Number(a)) || Number(a) < 0 || Number(a) > maxChildAge)) return `Select an age from 0 to ${maxChildAge} for every child. Guests aged ${maxChildAge + 1}+ must be included as adults.`;
  return '';
}
export function partyTotal(p: GuestParty): number { return p.adults + p.children; }
export function readParty(params: URLSearchParams): GuestParty {
  let ages: Array<number|string> = [];
  try { const value = JSON.parse(params.get('children_ages') || '[]'); if (Array.isArray(value)) ages = value; } catch { /* Report missing ages in the selector. */ }
  return { adults: Number(params.get('adults') ?? params.get('guests') ?? 2), children: Number(params.get('children') ?? ages.length), children_ages: ages };
}
export function writeParty(params: URLSearchParams, p: GuestParty): URLSearchParams {
  params.set('adults', String(p.adults)); params.set('children', String(p.children)); params.set('children_ages', JSON.stringify(p.children_ages)); params.set('guests', String(partyTotal(p))); return params;
}
export function partyRequest(p: GuestParty) { return { adults: p.adults, children: p.children, children_ages: p.children_ages.map(Number), guests: partyTotal(p) }; }
export function occupancy(room: OccupancyRoom, p: GuestParty) {
  const capacity = Number(room.max_guests ?? room.guests ?? room.capacity ?? 0);
  const childAge = room.max_child_age;
  const validAge = typeof childAge === 'number' && Number.isInteger(childAge) && childAge >= 0 && childAge <= 17;
  const childPlaces = p.children_ages.filter(age => Number(age) <= (childAge ?? -1)).length;
  const adultPlaces = p.adults + p.children - childPlaces;
  const maxAdults = Number(room.max_adults ?? capacity), maxChildren = Number(room.max_children ?? 0), total = Number(room.max_total_occupants ?? capacity);
  const counted = room.count_children_toward_capacity === false ? adultPlaces : partyTotal(p);
  return { fits: validAge && !partyError(p, true, childAge ?? null) && adultPlaces <= maxAdults && childPlaces <= maxChildren && partyTotal(p) <= total && counted <= capacity, adultPlaces, childPlaces, maxAdults, maxChildren, total, childAge };
}
export function roomOccupancyLabel(room: OccupancyRoom): string {
  const cap = Number(room.max_guests ?? room.guests ?? room.capacity ?? 0);
  return `Up to ${room.max_adults ?? cap} adult places · ${room.max_children ?? 0} child places (ages 0–${room.max_child_age ?? 'unavailable'}) · ${room.max_total_occupants ?? cap} people total`;
}
export type PartyAllocation<R> = { room: R; party: GuestParty };
/** Exact search with memoization and symmetry pruning; never substitutes child places for adult places. */
export function allocateParty<R extends OccupancyRoom>(rooms: R[], p: GuestParty, exactRooms?: number): PartyAllocation<R>[] {
  if (!rooms.length || partyError(p, false, rooms[0].max_child_age ?? null)) return [];
  const people: Array<number | null> = [...Array.from({ length: p.adults }, () => null), ...p.children_ages.map(Number)];
  const empty = (): GuestParty => ({ adults: 0, children: 0, children_ages: [] });
  const alone = (person: number|null): GuestParty => person === null ? { adults: 1, children: 0, children_ages: [] } : { adults: 0, children: 1, children_ages: [person] };
  const eligible = (person: number|null) => rooms.filter(r => occupancy(r, alone(person)).fits).length;
  people.sort((a,b) => eligible(a)-eligible(b) || Number(b ?? 18)-Number(a ?? 18));
  if (people.some(person => !eligible(person))) return [];
  const min = exactRooms ?? 1, max = exactRooms ?? Math.min(rooms.length, people.length);
  for (let wanted = min; wanted <= max; wanted++) {
    if (wanted < 1 || wanted > rooms.length || wanted > people.length) continue;
    const assigned = rooms.map(empty); const failed = new Set<string>();
    function visit(index: number): boolean {
      const used = assigned.filter(a => partyTotal(a) > 0).length;
      if (used > wanted || used + people.length-index < wanted) return false;
      if (index === people.length) return used === wanted;
      const key = `${index}|${assigned.map(a => `${a.adults}:${a.children_ages.join(',')}`).join('|')}`;
      if (failed.has(key)) return false;
      const symmetric = new Set<string>();
      for (let i = 0; i < rooms.length; i++) {
        const prior = assigned[i], room = rooms[i];
        if (!partyTotal(prior) && used === wanted) continue;
        const signature = JSON.stringify([room.max_guests ?? room.guests ?? room.capacity, room.max_adults, room.max_children, room.max_total_occupants, room.max_child_age, room.count_children_toward_capacity, prior]);
        if (symmetric.has(signature)) continue; symmetric.add(signature);
        const person = people[index];
        const next: GuestParty = person === null ? { ...prior, adults: prior.adults+1 } : { ...prior, children: prior.children+1, children_ages: [...prior.children_ages, person] };
        if (!occupancy(room,next).fits) continue;
        assigned[i] = next; if (visit(index+1)) return true; assigned[i] = prior;
      }
      failed.add(key); return false;
    }
    if (visit(0)) return rooms.flatMap((room,i) => partyTotal(assigned[i]) ? [{ room, party: assigned[i] }] : []);
  }
  return [];
}
