export function formatMoney(currency: string | undefined, amount: number): string {
  const code = String(currency || 'LKR').trim().toUpperCase();
  const value = Number.isFinite(Number(amount)) ? Number(amount) : 0;
  const formatted = value.toLocaleString('en-LK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  return code === 'LKR' ? `Rs. ${formatted}` : `${code} ${formatted}`;
}
