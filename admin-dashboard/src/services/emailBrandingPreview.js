/** Preview-only override. Nothing is uploaded, saved or sent from this function. */
export function applyEmailBrandingPreview(html, branding) {
  if (!html || !branding || typeof DOMParser === 'undefined') return html
  const width = Number(branding.width)
  const logo = branding.logo
  if (!Number.isInteger(width) || width < 40 || width > 240 || typeof logo !== 'string'
      || logo.length > 2800000 || (logo !== '' && !/^data:image\/(png|jpeg);base64,[A-Za-z0-9+/=]+$/.test(logo))) return html
  const doc = new DOMParser().parseFromString(html, 'text/html')
  const container = doc.querySelector('[data-email-brand-logo="true"]')
  if (!container) return html
  const alt = container.querySelector('img')?.getAttribute('alt') || 'Email logo'
  container.replaceChildren()
  if (logo) {
    const image = doc.createElement('img')
    image.src = logo
    image.alt = alt
    image.setAttribute('width', String(width))
    image.setAttribute('style', `display:block;width:${width}px;max-width:100%;height:auto;margin:0 auto 12px;border:0;`)
    container.appendChild(image)
  }
  return '<!doctype html>\n' + doc.documentElement.outerHTML
}
