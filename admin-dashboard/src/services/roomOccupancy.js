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
