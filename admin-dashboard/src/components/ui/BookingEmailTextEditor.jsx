import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react'
import { Bold, Italic, Link as LinkIcon, Unlink, List, ListOrdered } from 'lucide-react'

export function safeBookingEmailLink(value) {
  const href = String(value || '').trim()
  return !/[\u0000-\u0020\u007f{}]/u.test(href) && /^(https?:\/\/[^/]+|mailto:[^@]+@[^@]+|tel:\+?[0-9().-]+)/i.test(href) ? href : ''
}
export function sanitizeBookingEmailHtml(value) {
  const doc = new DOMParser().parseFromString(String(value || ''), 'text/html')
  const allowed = new Set(['P', 'DIV', 'STRONG', 'B', 'EM', 'I', 'A', 'UL', 'OL', 'LI', 'BR'])
  const discard = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'MATH', 'TEMPLATE', 'FORM', 'INPUT', 'BUTTON', 'TEXTAREA', 'SELECT', 'IMG', 'VIDEO', 'AUDIO', 'LINK', 'META', 'BASE'])
  const clean = node => {
    for (const child of [...node.childNodes]) {
      if (child.nodeType === 1) {
        if (discard.has(child.tagName)) { child.remove(); continue }
        clean(child)
        if (!allowed.has(child.tagName)) { child.replaceWith(...child.childNodes); continue }
        const href = child.tagName === 'A' ? safeBookingEmailLink(child.getAttribute('href')) : ''
        for (const attr of [...child.attributes]) child.removeAttribute(attr.name)
        if (href) { child.setAttribute('href', href); child.setAttribute('rel', 'noopener noreferrer') }
      } else if (child.nodeType !== 3) child.remove()
    }
  }
  clean(doc.body)
  return doc.body.innerHTML.trim()
}

const BookingEmailTextEditor = forwardRef(function BookingEmailTextEditor({ id, value, onChange, onFocus, disabled = false }, forwardedRef) {
  const editor = useRef(null)
  const selection = useRef(null)
  const [linkOpen, setLinkOpen] = useState(false)
  const [link, setLink] = useState('')
  const [linkError, setLinkError] = useState('')
  useEffect(() => {
    const html = sanitizeBookingEmailHtml(value)
    if (editor.current && editor.current.innerHTML !== html) {
      editor.current.innerHTML = html
      selection.current = null
    }
  }, [value])
  const remember = () => {
    const sel = window.getSelection()
    if (sel?.rangeCount && editor.current?.contains(sel.anchorNode) && editor.current.contains(sel.focusNode)) selection.current = sel.getRangeAt(0).cloneRange()
  }
  const restore = () => {
    const node = editor.current
    if (!node || disabled) return false
    node.focus()
    const sel = window.getSelection()
    const range = selection.current
    sel.removeAllRanges()
    if (range && node.contains(range.commonAncestorContainer)) sel.addRange(range)
    else { const end = document.createRange(); end.selectNodeContents(node); end.collapse(false); sel.addRange(end) }
    return true
  }
  const sync = () => { remember(); onChange(sanitizeBookingEmailHtml(editor.current?.innerHTML || '')) }
  const run = (command, argument) => {
    if (!restore()) return
    document.execCommand('styleWithCSS', false, false)
    document.execCommand(command, false, argument)
    sync()
  }
  useImperativeHandle(forwardedRef, () => ({ insertText: text => run('insertText', text) }))
  const commands = [['bold', 'Bold', Bold], ['italic', 'Italic', Italic], ['insertUnorderedList', 'Bullet list', List], ['insertOrderedList', 'Numbered list', ListOrdered], ['unlink', 'Remove link', Unlink]]
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-white focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
      <div role="toolbar" aria-label="Text formatting" className="flex flex-wrap items-center gap-1 border-b border-border bg-slate-50 px-2 py-2">
        {commands.map(([command, label, Icon]) => <button key={command} type="button" title={label} aria-label={label} disabled={disabled}
          onMouseDown={event => event.preventDefault()} onClick={() => run(command)}
          className="rounded-lg p-2 text-slate-600 transition hover:bg-white hover:text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-40"><Icon className="h-4 w-4" /></button>)}
        <span className="mx-1 h-5 border-l border-border" />
        <button type="button" title="Add link" aria-label="Add link" disabled={disabled} onMouseDown={event => event.preventDefault()}
          onClick={() => { remember(); setLinkOpen(open => !open); setLinkError(''); setLink('') }} className="rounded-lg p-2 text-slate-600 hover:bg-white hover:text-blue-700"><LinkIcon className="h-4 w-4" /></button>
        <span className="ml-auto pr-2 text-xs text-text-secondary">Text formatting</span>
      </div>
      {linkOpen && <div className="border-b border-border bg-blue-50/50 p-3">
        <label htmlFor={`${id}-link`} className="mb-1 block text-xs font-semibold text-text-primary">Link URL</label>
        <div className="flex flex-wrap gap-2">
          <input id={`${id}-link`} value={link} onChange={e => setLink(e.target.value)} placeholder="https://example.com" disabled={disabled} className="min-w-0 flex-1 rounded-lg border border-border bg-white px-3 py-2 text-sm" />
          <button type="button" disabled={disabled} onClick={() => {
            const href = safeBookingEmailLink(link)
            if (!href) { setLinkError('Use an https://, http://, mailto: or tel: link.'); return }
            run('createLink', href); setLinkOpen(false)
          }} className="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Apply</button>
          <button type="button" onClick={() => setLinkOpen(false)} className="rounded-lg px-2 text-sm text-text-secondary">Cancel</button>
        </div>
        {linkError && <p role="alert" className="mt-1 text-xs text-red-700">{linkError}</p>}
      </div>}
      <div id={id} ref={editor} role="textbox" aria-multiline="true" aria-labelledby={`${id}-label`} aria-disabled={disabled}
        contentEditable={!disabled} suppressContentEditableWarning onFocus={onFocus} onBlur={remember} onMouseUp={remember} onKeyUp={remember} onInput={sync}
        onPaste={event => {
          event.preventDefault()
          const raw = event.clipboardData.getData('text/html')
          if (raw) run('insertHTML', sanitizeBookingEmailHtml(raw))
          else run('insertText', event.clipboardData.getData('text/plain'))
        }}
        className="min-h-[135px] max-h-[320px] overflow-y-auto px-4 py-3 text-sm leading-7 text-text-primary outline-none [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_a]:text-blue-700 [&_a]:underline [&_strong]:font-bold [&_b]:font-bold [&_em]:italic [&_i]:italic" />
    </div>
  )
})
export default BookingEmailTextEditor
