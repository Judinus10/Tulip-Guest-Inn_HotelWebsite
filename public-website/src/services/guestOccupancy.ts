export type GuestParty = { adults: number; children: number; children_ages: Array<number | string> };
export type OccupancyRoom = { id: string | number; guests?: number; capacity?: number; max_guests?: number; max_adults?: number; max_children?: number; max_total_occupants?: number; max_child_age?: number; count_children_toward_capacity?: boolean };
export function partyError(p: GuestParty, allowZeroAdults = false, maxChildAge: number | null = null): string {
  if (maxChildAge === null || !Number.isInteger(maxChildAge) || maxChildAge < 0 || maxChildAge > 17) return 'Guest age policy is unavailable. Please wait or retry.';
  if (!Number.isInteger(p.adults) || p.adults < (allowZeroAdults ? 0 : 1)) return 'At least one adult is required.';
  if (!Number.isInteger(p.children) || p.children < 0 || p.adults + p.children > 20) return 'Choose up to 20 people in total.';
  return '';
}
export function partyTotal(p: GuestParty): number { return p.adults + p.children; }
export function readParty(params: URLSearchParams): GuestParty {
  return { adults: Number(params.get('adults') ?? params.get('guests') ?? 2), children: Number(params.get('children') ?? 0), children_ages: [] };
}
export function writeParty(params: URLSearchParams, p: GuestParty): URLSearchParams {
  params.set('adults', String(p.adults)); params.set('children', String(p.children)); params.delete('children_ages'); params.set('guests', String(partyTotal(p))); return params;
}
export function partyRequest(p: GuestParty) { return { adults: p.adults, children: p.children, guests: partyTotal(p) }; }
export function occupancy(room: OccupancyRoom, p: GuestParty) {
  const capacity = Number(room.max_guests ?? room.guests ?? room.capacity ?? 0);
  const childAge = room.max_child_age;
  const validAge = typeof childAge === 'number' && Number.isInteger(childAge) && childAge >= 0 && childAge <= 17;
  const childPlaces = p.children;
  const adultPlaces = p.adults;
  const maxAdults = Number(room.max_adults ?? capacity), maxChildren = Number(room.max_children ?? 0), total = Number(room.max_total_occupants ?? capacity);
  const extraPlacesFit = room.count_children_toward_capacity !== false || childPlaces <= maxChildren;
  return { fits: validAge && !partyError(p, true, childAge ?? null) && adultPlaces <= maxAdults && extraPlacesFit && partyTotal(p) <= total, adultPlaces, childPlaces, maxAdults, maxChildren, total, childAge };
}
export function roomOccupancyLabel(room: OccupancyRoom): string {
  const cap = Number(room.max_guests ?? room.guests ?? room.capacity ?? 0);
  return `${room.max_total_occupants ?? cap} guests total · max ${room.max_adults ?? cap} adults · ${room.count_children_toward_capacity === false ? `up to ${room.max_children ?? 0} extra child places` : 'children use normal guest places'} (ages 0–${room.max_child_age ?? 'unavailable'})`;
}
/** Public occupancy summary; the adult/child policy is explained on room details. */
export function roomMaxOccupancy(room: OccupancyRoom): number {
  const capacity = Number(room.max_guests ?? room.guests ?? room.capacity ?? 0);
  const total = Number(room.max_total_occupants ?? capacity);
  return room.count_children_toward_capacity === false
    ? Math.min(total, Number(room.max_adults ?? capacity) + Number(room.max_children ?? 0))
    : total;
}
export type PartyAllocation<R> = { room: R; party: GuestParty };
/** Exact search with memoization and symmetry pruning; never substitutes child places for adult places. */
export function allocateParty<R extends OccupancyRoom>(rooms: R[], p: GuestParty, exactRooms?: number): PartyAllocation<R>[] {
  if (!rooms.length || partyError(p, false, rooms[0].max_child_age ?? null)) return [];
  const people: Array<number | null> = [...Array.from({ length: p.adults }, () => null), ...Array.from({ length: p.children }, () => 0)];
  const empty = (): GuestParty => ({ adults: 0, children: 0, children_ages: [] });
  const alone = (person: number|null): GuestParty => person === null ? { adults: 1, children: 0, children_ages: [] } : { adults: 0, children: 1, children_ages: [] };
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
      const key = `${index}|${assigned.map(a => `${a.adults}:${a.children}`).join('|')}`;
      if (failed.has(key)) return false;
      const symmetric = new Set<string>();
      for (let i = 0; i < rooms.length; i++) {
        const prior = assigned[i], room = rooms[i];
        if (!partyTotal(prior) && used === wanted) continue;
        const signature = JSON.stringify([room.max_guests ?? room.guests ?? room.capacity, room.max_adults, room.max_children, room.max_total_occupants, room.max_child_age, room.count_children_toward_capacity, prior]);
        if (symmetric.has(signature)) continue; symmetric.add(signature);
        const person = people[index];
        const next: GuestParty = person === null ? { ...prior, adults: prior.adults+1 } : { ...prior, children: prior.children+1, children_ages: [] };
        if (!occupancy(room,next).fits) continue;
        assigned[i] = next; if (visit(index+1)) return true; assigned[i] = prior;
      }
      failed.add(key); return false;
    }
    if (visit(0)) return rooms.flatMap((room,i) => partyTotal(assigned[i]) ? [{ room, party: assigned[i] }] : []);
  }
  return [];
}

/** Individual maxima do not override the combined guest limit. */
export function roomGuestLabel(room: OccupancyRoom): string {
  const total = roomMaxOccupancy(room);
  const capacity = Number(room.max_guests ?? room.guests ?? room.capacity ?? 0);
  const adults = Math.min(Number(room.max_adults ?? capacity), total);
  const children = room.count_children_toward_capacity === false ? Math.min(Number(room.max_children ?? 0), total) : total;
  return `${total} ${total === 1 ? 'Guest' : 'Guests'} (max ${adults} ${adults === 1 ? 'adult' : 'adults'}, ${children} ${children === 1 ? 'child' : 'children'})`;
}

/** Prefer a single room; suggest a real, available combination only if none fits. */
export function bookingRoomOptions<R extends OccupancyRoom & { slug: string }>(rooms: R[], party: GuestParty, preferredSlug: string) {
  const matches = rooms.filter(room => occupancy(room, party).fits);
  const selected = matches.find(room => room.slug === preferredSlug) ?? matches[0];
  const multiRoomCount = matches.length ? 0 : allocateParty(rooms, party).length;
  return { matches, selected, multiRoomCount: multiRoomCount >= 2 ? multiRoomCount : 0 };
}

/** Explain an exact-room search failure and offer an actual alternative. */
export function roomAllocationSearch<R extends OccupancyRoom>(rooms: R[], party: GuestParty, requestedRooms: number) {
  const assignments = allocateParty(rooms, party, requestedRooms);
  if (assignments.length) return { assignments, alternative: [] as PartyAllocation<R>[], reason: '' };
  const alternative = allocateParty(rooms, party);
  let reason: string;
  if (requestedRooms > partyTotal(party)) reason = `You selected ${requestedRooms} rooms for ${partyTotal(party)} guests. Each selected room must have at least one guest.`;
  else if (requestedRooms > rooms.length) reason = `Only ${rooms.length} ${rooms.length === 1 ? 'room is' : 'rooms are'} available for these dates; ${requestedRooms} were requested.`;
  else if (rooms.reduce((sum,room)=>sum+roomMaxOccupancy(room),0) < partyTotal(party)) reason = 'The available rooms do not have enough combined guest capacity.';
  else if (rooms.reduce((sum,room)=>sum+Math.min(Number(room.max_adults ?? room.max_guests ?? room.guests ?? room.capacity ?? 0),roomMaxOccupancy(room)),0) < party.adults) reason = 'The available rooms do not have enough adult places for your group.';
  else if (rooms.reduce((sum,room)=>sum+(room.count_children_toward_capacity === false ? Math.min(Number(room.max_children ?? 0),roomMaxOccupancy(room)) : roomMaxOccupancy(room)),0) < party.children) reason = 'The available rooms do not have enough extra child places under the current child policy.';
  else reason = `This adult and child combination cannot be allocated across exactly ${requestedRooms} available rooms within their limits.`;
  return { assignments, alternative, reason };
}
