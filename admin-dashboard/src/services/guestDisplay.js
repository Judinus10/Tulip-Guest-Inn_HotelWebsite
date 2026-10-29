export function guestSummary(party) {
  const adults = Number(party.adults ?? party.guests ?? 0);
  const children = Number(party.children ?? 0);
  return `${adults} ${adults === 1 ? 'adult' : 'adults'}, ${children} ${children === 1 ? 'child' : 'children'}`;
}
export function childAgeSummary(party) {
  const children = Number(party.children ?? 0);
  if (!children) return '';
  const ages = party.children_ages ?? [];
  if (ages.length !== children) return 'Not recorded';
  return ages.map(age => Number(age) === 0 ? 'Under 1' : String(age)).join(', ');
}
