export const occupancyFields = [
  { key: 'max_total_occupants', label: 'Maximum total guests', min: 1, max: 20, hint: 'Hard limit for adults and children combined. This limit always applies.' },
  { key: 'max_adults', label: 'Maximum adults', min: 1, max: 20, hint: 'Adult limit within the total guest limit. Child places do not allow extra adults.' },
  { key: 'max_children', label: 'Extra child places', min: 0, max: 20, hint: 'Used only when children do not use normal guest places. These places never override the total guest limit.' },
  { key: 'max_child_age', label: 'Maximum child age (inclusive)', min: 0, max: 17, hint: 'Age at check-in. A limit of 11 includes ages 0–11; ages 12+ use adult places.' },
]

export function validateRoomOccupancy(form) {
  const errors = {}
  for (const { key, label, min, max } of occupancyFields) {
    const value = String(form[key] ?? '')
    if (!/^(0|[1-9][0-9]*)$/.test(value) || Number(value) < min || Number(value) > max) errors[key] = `${label} must be a whole number from ${min} to ${max}.`
  }
  if (Object.keys(errors).length) return errors
  if (Number(form.max_adults) > Number(form.max_total_occupants)) errors.max_adults = 'Maximum adults cannot exceed maximum total guests.'
  if (Number(form.max_children) > Number(form.max_total_occupants)) errors.max_children = 'Extra child places cannot exceed maximum total guests.'
  return errors
}

/** The total is authoritative; retain capacity only as a compatible storage alias.
 * A temporarily empty input stays editable until blur/save commits it.
 * Toggling the shared policy does not change a room's configured limits.
 */
export function normalizeRoomOccupancy(form, countChildren, changedKey = null) {
  if (changedKey && String(form[changedKey] ?? '').trim() === '') return { ...form }
  const integer = (value, fallback, min, max) => {
    const number = String(value ?? '').trim() === '' ? fallback : Number(value)
    return Math.min(max, Math.max(min, Number.isFinite(number) ? Math.floor(number) : fallback))
  }
  const legacyCapacity = integer(form.capacity, 2, 1, 20)
  const total = integer(form.max_total_occupants, legacyCapacity, 1, 20)
  const adults = integer(form.max_adults, total, 1, total)
  const children = integer(form.max_children, 0, 0, total)
  return { ...form, capacity: total, max_adults: adults, max_children: children, max_total_occupants: total }
}
