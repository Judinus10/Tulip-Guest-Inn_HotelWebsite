export function toInternationalPhone(countryCode: string, localNumber: string): string {
  let localDigits = localNumber.replace(/\D/g, '');
  // Most countries omit the national trunk prefix when dialling internationally.
  // Italy is the notable exception, where the leading zero is significant.
  if (countryCode !== '39') localDigits = localDigits.replace(/^0+/, '');
  const digits = `${countryCode}${localDigits}`.replace(/\D/g, '');
  return digits ? `+${digits}` : '';
}
