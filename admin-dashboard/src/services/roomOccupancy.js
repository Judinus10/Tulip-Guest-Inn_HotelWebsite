export const occupancyFields = [
  { key: 'capacity', label: 'Guest capacity', min: 1, max: 20, hint: 'The guest-capacity limit used for this room.' },
  { key: 'max_adults', label: 'Maximum adults', min: 1, max: 20, hint: 'Child places cannot be used as extra adult places.' },
  { key: 'max_children', label: 'Maximum children', min: 0, max: 20, hint: 'Set 0 if this room does not accommodate children.' },
  { key: 'max_total_occupants', label: 'Maximum total occupants', min: 1, max: 20, hint: 'The total number of people allowed, including children.' },
  { key: 'max_child_age', label: 'Maximum child age (inclusive)', min: 0, max: 17, hint: 'Age at check-in. A limit of 11 includes ages 0–11; ages 12+ use adult places.' },
]

export function validateRoomOccupancy(form) {
  const errors = {}
  for (const { key, label, min, max } of occupancyFields) {
    const value = String(form[key] ?? '')
    if (!/^(0|[1-9][0-9]*)$/.test(value) || Number(value) < min || Number(value) > max) {
      errors[key] = `${label} must be a whole number from ${min} to ${max}.`
    }
  }
  if (Object.keys(errors).length) return errors
  if (Number(form.max_adults) > Number(form.capacity)) errors.max_adults = 'Maximum adults cannot exceed guest capacity.'
  if (Number(form.max_total_occupants) < Math.max(Number(form.max_adults), Number(form.max_children))) errors.max_total_occupants = 'Total occupancy cannot be lower than an individual adult or child limit.'
  if (Number(form.max_total_occupants) > Number(form.max_adults) + Number(form.max_children)) errors.max_total_occupants = 'Total occupancy cannot exceed the combined adult and child limits.'
  return errors
}

/** Reconcile dependent limits without increasing the room's configured capacity.
 * Keep a temporarily empty field while typing; commit fills it with a valid limit.
 * The shared child age is an independent policy and is never guessed here.
 */
export function normalizeRoomOccupancy(form, countChildren, changedKey = null) {
  if (changedKey && String(form[changedKey] ?? '').trim() === '') return { ...form }
  const integer = (value, fallback, min, max) => {
    const number = String(value ?? '').trim() === '' ? fallback : Number(value)
    return Math.min(max, Math.max(min, Number.isFinite(number) ? Math.floor(number) : fallback))
  }
  const capacity = integer(form.capacity, 2, 1, 20)
  let adults = integer(form.max_adults, capacity, 1, capacity)
  let children = integer(form.max_children, 0, 0, countChildren ? capacity : 20)
  const ceiling = countChildren ? capacity : 20
  let total = integer(form.max_total_occupants, adults, 1, ceiling)
  if (changedKey === 'max_total_occupants') {
    // A deliberate lower total also lowers any individual limits above it.
    total = Math.min(total, adults + children)
    adults = Math.min(adults, total)
    children = Math.min(children, total)
  } else {
    // Raise the total only when an individual limit requires it; preserve a
    // separately chosen lower combined limit whenever it remains valid.
    total = Math.min(ceiling, adults + children, Math.max(total, adults, children))
  }
  return { ...form, capacity, max_adults: adults, max_children: children, max_total_occupants: total }
}
