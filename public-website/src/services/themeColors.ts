// Keep these defaults aligned with api/settings/theme.php and the CSS fallback.
export const defaultThemeColors = {
  primary: '#C7A86B', secondary: '#19352B', background: '#FAF8F4', surface: '#FFFFFF',
  text: '#1A1A1A', muted: '#6B7280', footer: '#1A1A1A', border: '#ECE7DF',
} as const;
export type ThemeColors = Record<keyof typeof defaultThemeColors, string>;

export function isThemeColors(value: unknown): value is ThemeColors {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const record = value as Record<string, unknown>;
  const keys = Object.keys(defaultThemeColors);
  return Object.keys(record).length === keys.length && keys.every(key =>
    typeof record[key] === 'string' && /^#[0-9a-f]{6}$/i.test(record[key] as string));
}
function channels(hex: string) {
  return [1, 3, 5].map(offset => parseInt(hex.slice(offset, offset + 2), 16));
}
function luminance(hex: string) {
  const [r, g, b] = channels(hex).map(channel => {
    const value = channel / 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  });
  return .2126 * r + .7152 * g + .0722 * b;
}
export function readableText(background: string) {
  const light = (1.05) / (luminance(background) + .05);
  const dark = (luminance(background) + .05) / (luminance('#1A1A1A') + .05);
  return light >= dark ? '#FFFFFF' : '#1A1A1A';
}
function shade(hex: string, amount: number) {
  const target = amount >= 0 ? 255 : 0;
  return '#' + channels(hex).map(channel => Math.round(channel + (target - channel) * Math.abs(amount))
    .toString(16).padStart(2, '0')).join('').toUpperCase();
}
export function themeVariables(colors: ThemeColors): Record<string, string> {
  // Validate the entire response before producing CSS. Never accept arbitrary CSS from settings.
  if (!isThemeColors(colors)) throw new Error('Invalid website palette');
  const palette = {
    ...colors,
    'primary-light': shade(colors.primary, .2),
    'primary-dark': shade(colors.primary, -.2),
    'secondary-light': shade(colors.secondary, .15),
  };
  const variables: Record<string, string> = {};
  for (const [key, hex] of Object.entries(palette)) {
    variables[`--theme-${key}`] = hex;
    variables[`--theme-${key}-rgb`] = channels(hex).join(' ');
  }
  for (const key of ['primary', 'secondary', 'footer'] as const) {
    variables[`--theme-on-${key}`] = readableText(colors[key]);
  }
  return variables;
}
