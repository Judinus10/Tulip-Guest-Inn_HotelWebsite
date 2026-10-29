export const defaultThemeColors = {
  primary: '#C7A86B', secondary: '#19352B', background: '#FAF8F4', surface: '#FFFFFF',
  text: '#1A1A1A', muted: '#6B7280', footer: '#1A1A1A', border: '#ECE7DF',
}
export const themeColorFields = [
  { key: 'primary', label: 'Primary colour', description: 'Main buttons, links and accents.' },
  { key: 'secondary', label: 'Secondary colour', description: 'Feature sections and secondary buttons.' },
  { key: 'background', label: 'Page background', description: 'The background behind page sections.' },
  { key: 'surface', label: 'Card background', description: 'Cards, forms and content panels.' },
  { key: 'text', label: 'Main text', description: 'Headings and regular content.' },
  { key: 'muted', label: 'Secondary text', description: 'Descriptions and supporting text.' },
  { key: 'footer', label: 'Footer background', description: 'The bottom section of the website.' },
  { key: 'border', label: 'Border colour', description: 'Cards, inputs and section dividers.' },
]
export const isThemeColor = value => typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value.trim())
function luminance(hex) {
  const rgb = [1, 3, 5].map(offset => parseInt(hex.slice(offset, offset + 2), 16) / 255)
  const linear = rgb.map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)
  return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]
}
export function contrastRatio(first, second) {
  const a = luminance(first); const b = luminance(second)
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)
}
export function readableText(background) {
  return contrastRatio(background, '#FFFFFF') >= contrastRatio(background, '#1A1A1A') ? '#FFFFFF' : '#1A1A1A'
}
export function shade(hex, amount) {
  const target = amount >= 0 ? 255 : 0
  return '#' + [1, 3, 5].map(offset => {
    const channel = parseInt(hex.slice(offset, offset + 2), 16)
    return Math.round(channel + (target - channel) * Math.abs(amount)).toString(16).padStart(2, '0')
  }).join('').toUpperCase()
}
