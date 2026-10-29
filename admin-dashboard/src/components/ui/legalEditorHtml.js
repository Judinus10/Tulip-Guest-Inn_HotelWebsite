export const legalFonts = ['Arial', 'Arial Black', 'Helvetica', 'Verdana', 'Tahoma', 'Trebuchet MS', 'Segoe UI', 'Calibri', 'Candara', 'Century Gothic', 'Lucida Sans Unicode', 'Georgia', 'Times New Roman', 'Cambria', 'Garamond', 'Palatino Linotype', 'Book Antiqua', 'Courier New', 'Consolas', 'Lucida Console', 'Impact', 'system-ui']
const fontMap = new Map([...legalFonts, 'sans-serif', 'serif', 'monospace'].map(font => [font.toLowerCase(), font]))
const allowedTags = new Set(['p', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'br', 'a', 'blockquote'])
const discardTags = new Set(['script', 'style', 'iframe', 'svg', 'math', 'object', 'embed', 'form', 'img', 'video', 'audio', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'template'])

export function safeLegalLink(value) {
  const href = String(value || '').trim()
  return !/[\u0000-\u0020\u007f]/.test(href) && /^(https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i.test(href) ? href : ''
}

function fontSize(value) {
  const match = String(value).trim().match(/^(\d+(?:\.\d+)?)\s*(px|pt|em|rem|%)$/i)
  if (!match) return ''
  let size = Number(match[1])
  const unit = match[2].toLowerCase()
  if (unit === 'pt') size *= 4 / 3
  if (unit === 'em' || unit === 'rem') size *= 16
  if (unit === '%') size *= 0.16
  return `${Math.round(Math.max(10, Math.min(48, size)) * 100) / 100}px`
}

export function sanitizeLegalHtml(html) {
  const template = document.createElement('template')
  template.innerHTML = String(html || '')
  const clean = parent => {
    for (const node of [...parent.childNodes]) {
      if (node.nodeType === Node.TEXT_NODE) continue
      if (node.nodeType !== Node.ELEMENT_NODE) { node.remove(); continue }
      let element = node
      let tag = element.tagName.toLowerCase()
      if (discardTags.has(tag)) { element.remove(); continue }
      if (tag === 'font') {
        const span = document.createElement('span')
        span.setAttribute('style', element.getAttribute('style') || '')
        if (!span.style.fontFamily) span.style.fontFamily = element.getAttribute('face') || ''
        if (!span.style.fontSize) span.style.fontSize = ({1:'10px',2:'13px',3:'16px',4:'18px',5:'24px',6:'32px',7:'48px'})[element.getAttribute('size')] || ''
        span.append(...element.childNodes)
        element.replaceWith(span); element = span; tag = 'span'
      }
      clean(element)
      if (!allowedTags.has(tag)) { element.replaceWith(...element.childNodes); continue }
      const styles = {}
      const family = fontMap.get(element.style.fontFamily.split(',')[0].replace(/["']/g, '').trim().toLowerCase())
      if (family) styles.fontFamily = family
      const size = fontSize(element.style.fontSize)
      if (size) styles.fontSize = size
      if (['left','center','right','justify'].includes(element.style.textAlign)) styles.textAlign = element.style.textAlign
      if (/^(normal|bold|[1-9]00)$/.test(element.style.fontWeight)) styles.fontWeight = element.style.fontWeight
      if (['normal','italic'].includes(element.style.fontStyle)) styles.fontStyle = element.style.fontStyle
      const decoration = element.style.textDecorationLine || element.style.textDecoration
      if (/^(none|underline|line-through|underline line-through)$/.test(decoration)) styles.textDecoration = decoration
      const href = tag === 'a' ? safeLegalLink(element.getAttribute('href')) : ''
      for (const attribute of [...element.attributes]) element.removeAttribute(attribute.name)
      Object.assign(element.style, styles)
      if (!element.getAttribute('style')) element.removeAttribute('style')
      if (href) { element.setAttribute('href', href); element.setAttribute('target', '_blank'); element.setAttribute('rel', 'noopener noreferrer') }
    }
  }
  clean(template.content)
  return template.innerHTML
}

// Apply exact CSS values to selected text, including mixed formatting/multiple blocks.
export function applyLegalTextStyle(editor, range, property, value) {
  if (!range || !editor.contains(range.commonAncestorContainer)) return false
  if (range.collapsed) {
    let element = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement
    let block = element.closest('p,h1,h2,h3,h4,h5,h6,li,blockquote,div')
    if (!block || block === editor) {
      document.execCommand('formatBlock', false, 'p')
      const selection = window.getSelection()
      element = selection.anchorNode.nodeType === Node.ELEMENT_NODE ? selection.anchorNode : selection.anchorNode.parentElement
      block = element.closest('p,h1,h2,h3,h4,h5,h6,li,blockquote,div')
    }
    if (!block || block === editor || !editor.contains(block)) return false
    block.style[property] = value
    block.querySelectorAll('*').forEach(child => {
      child.style[property] = ''
      if (child.tagName === 'FONT') child.removeAttribute(property === 'fontSize' ? 'size' : 'face')
    })
    return true
  }
  const walker = document.createTreeWalker(editor, NodeFilter.SHOW_TEXT)
  const selected = []
  let node
  while ((node = walker.nextNode())) {
    if (!range.intersectsNode(node)) continue
    const start = node === range.startContainer ? range.startOffset : 0
    const end = node === range.endContainer ? range.endOffset : node.length
    if (end > start) selected.push({ node, start, end })
  }
  const spans = []
  for (const item of selected.reverse()) {
    let text = item.node
    if (item.end < text.length) text.splitText(item.end)
    if (item.start > 0) text = text.splitText(item.start)
    const span = document.createElement('span')
    span.style[property] = value
    text.replaceWith(span); span.append(text); spans.unshift(span)
  }
  if (!spans.length) return false
  const selection = window.getSelection()
  const updated = document.createRange()
  updated.setStart(spans[0].firstChild, 0)
  const last = spans[spans.length - 1].lastChild
  updated.setEnd(last, last.length)
  selection.removeAllRanges(); selection.addRange(updated)
  return true
}
