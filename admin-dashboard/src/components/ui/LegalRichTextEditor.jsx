import { useEffect, useRef, useState } from 'react'
import { Bold, Italic, Underline, List, ListOrdered, Undo2, Redo2, RemoveFormatting, AlignLeft, AlignCenter, AlignRight, AlignJustify, Link as LinkIcon, Unlink, ChevronDown, Check, Type, Minus, Plus } from 'lucide-react'
import { legalFonts, safeLegalLink, sanitizeLegalHtml, applyLegalTextStyle } from './legalEditorHtml'
import './LegalRichTextEditor.css'

export default function LegalRichTextEditor({ value, onChange }) {
  const ref = useRef(null)
  const selectionRef = useRef(null)
  const history = useRef({ entries: [], index: -1 })
  const [fontOpen, setFontOpen] = useState(false)
  const [fontSearch, setFontSearch] = useState('')
  const fontPickerRef = useRef(null)
  const [active, setActive] = useState({})
  const [size, setSize] = useState(16)
  const [font, setFont] = useState('')
  const [block, setBlock] = useState('p')
  const [linkError, setLinkError] = useState('')
  useEffect(() => {
    const clean = sanitizeLegalHtml(value || '')
    if (ref.current && ref.current.innerHTML !== clean && document.activeElement !== ref.current) ref.current.innerHTML = clean
    if (history.current.index < 0) history.current = { entries: [clean], index: 0 }
  }, [value])
  useEffect(() => { document.execCommand('defaultParagraphSeparator', false, 'p') }, [])
  const sync = () => {
    const clean = sanitizeLegalHtml(ref.current?.innerHTML || '')
    const store = history.current
    if (store.entries[store.index] !== clean) {
      store.entries = [...store.entries.slice(0, store.index + 1), clean].slice(-100)
      store.index = store.entries.length - 1
    }
    onChange(clean)
  }
  useEffect(() => {
    const close = event => { if (!fontPickerRef.current?.contains(event.target)) setFontOpen(false) }
    document.addEventListener('pointerdown', close)
    return () => document.removeEventListener('pointerdown', close)
  }, [])
  const rememberSelection = () => {
    const selection = window.getSelection()
    if (!selection?.rangeCount || !ref.current?.contains(selection.anchorNode) || !ref.current?.contains(selection.focusNode)) return
    selectionRef.current = selection.getRangeAt(0).cloneRange()
    setActive({ bold: document.queryCommandState('bold'), italic: document.queryCommandState('italic'), underline: document.queryCommandState('underline') })
    const element = selection.anchorNode.nodeType === Node.ELEMENT_NODE ? selection.anchorNode : selection.anchorNode.parentElement
    if (element) {
      const style = getComputedStyle(element)
      const family = style.fontFamily.split(',')[0].replace(/["']/g, '').trim()
      setFont(legalFonts.find(item => item.toLowerCase() === family.toLowerCase()) || '')
      setSize(Math.round(parseFloat(style.fontSize)) || 16)
      const blockTag = element.closest('h1,h2,h3,h4,h5,h6,p,div')?.tagName.toLowerCase()
      setBlock(blockTag === 'div' ? 'p' : blockTag || 'p')
    }
  }
  const restore = () => {
    const editor = ref.current
    if (!editor) return false
    editor.focus()
    const selection = window.getSelection()
    const range = selectionRef.current
    if (range && editor.contains(range.commonAncestorContainer)) {
      selection.removeAllRanges(); selection.addRange(range)
    } else {
      const end = document.createRange(); end.selectNodeContents(editor); end.collapse(false)
      selection.removeAllRanges(); selection.addRange(end)
    }
    return true
  }
  const run = (command, arg) => {
    if (!restore()) return
    document.execCommand('styleWithCSS', false, true)
    document.execCommand(command, false, arg)
    rememberSelection(); sync()
  }
  const applyStyle = (property, value) => {
    if (!restore()) return
    const range = window.getSelection().getRangeAt(0)
    if (applyLegalTextStyle(ref.current, range, property, value)) { rememberSelection(); sync() }
  }
  const applySize = (nextSize = size) => {
    const pixels = Math.max(10, Math.min(48, Number(nextSize) || 16))
    applyStyle('fontSize', `${pixels}px`)
    setSize(pixels)
  }
  const restoreHistory = direction => {
    const store = history.current
    const index = store.index + direction
    if (index < 0 || index >= store.entries.length) return
    store.index = index
    ref.current.innerHTML = store.entries[index]
    selectionRef.current = null
    restore(); rememberSelection(); onChange(store.entries[index])
  }
  const addLink = () => {
    const value = window.prompt('Enter a link (https://, mailto:, tel:, /page or #section):')
    if (value === null) return
    const href = safeLegalLink(value)
    if (!href) { setLinkError('Enter a valid website, email, phone or internal link.'); return }
    setLinkError(''); run('createLink', href)
  }
  const button = (name, Icon, command, selected = false, action) => (
    <button key={name} type="button" title={name} aria-label={name} aria-pressed={selected}
      onMouseDown={event => event.preventDefault()} onClick={action || (() => command === 'undo' ? restoreHistory(-1) : command === 'redo' ? restoreHistory(1) : run(command))}
      className={`legal-tool-button ${selected ? 'is-active' : ''}`}>
      <Icon className="h-4 w-4" />
    </button>
  )
  const chooseFont = name => {
    applyStyle('fontFamily', name)
    setFont(name); setFontOpen(false); setFontSearch('')
  }
  return <div className="legal-editor-shell">
    <div className="legal-editor-toolbar" role="group" aria-label="Document formatting">
      <div className="legal-editor-toolbar-primary">
        <label className="legal-editor-field legal-style-field"><span>Text style</span>
          <div className="legal-select-wrap">
            <select aria-label="Paragraph or heading" value={block} onChange={event => { setBlock(event.target.value); run('formatBlock', event.target.value) }}>
              <option value="p">Normal text</option><option value="h1">Heading 1</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option>
              {['h4','h5','h6'].includes(block) && <option value={block}>Heading {block.slice(1)}</option>}
            </select><ChevronDown size={14} aria-hidden="true" />
          </div>
        </label>
        <div className="legal-editor-field legal-font-field" ref={fontPickerRef}><span id="legal-font-label">Font family</span>
          <button type="button" className="legal-font-trigger" aria-labelledby="legal-font-label" aria-expanded={fontOpen} aria-haspopup="listbox" onClick={() => setFontOpen(open => !open)}>
            <span style={{ fontFamily: font || 'inherit' }}>{font || 'Default font'}</span><ChevronDown size={14} />
          </button>
          {fontOpen && <div className="legal-font-menu" onKeyDown={event => { if (event.key === 'Escape') setFontOpen(false) }}>
            <input autoFocus type="search" placeholder="Search fonts…" aria-label="Search font families" value={fontSearch} onChange={event => setFontSearch(event.target.value)} />
            <div role="listbox" aria-label="Font families" className="legal-font-options">
              {legalFonts.filter(name => name.toLowerCase().includes(fontSearch.toLowerCase())).map(name => <button type="button" role="option" aria-selected={font === name} key={name} style={{ fontFamily: name }} onMouseDown={event => event.preventDefault()} onClick={() => chooseFont(name)}>
                <span>{name}</span>{font === name && <Check size={14} />}
              </button>)}
              {!legalFonts.some(name => name.toLowerCase().includes(fontSearch.toLowerCase())) && <p>No matching fonts</p>}
            </div>
          </div>}
        </div>
        <div className="legal-editor-field"><span>Size · px</span><div className="legal-size-control">
          <button type="button" aria-label="Decrease font size" onMouseDown={event => event.preventDefault()} onClick={() => applySize(Number(size) - 1)}><Minus size={14} /></button>
          <input aria-label="Font size in pixels" type="number" min="10" max="48" step="1" value={size} onChange={event => setSize(event.target.value)} onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); applySize() } }} />
          <button type="button" aria-label="Increase font size" onMouseDown={event => event.preventDefault()} onClick={() => applySize(Number(size) + 1)}><Plus size={14} /></button>
        </div></div>
        <button type="button" className="legal-size-apply" onMouseDown={event => event.preventDefault()} onClick={() => applySize()}><Check size={15} />Apply</button>
      </div>
      <div className="legal-editor-toolbar-secondary">
        <div className="legal-tool-group" role="group" aria-label="Text emphasis">{button('Bold', Bold, 'bold', active.bold)}{button('Italic', Italic, 'italic', active.italic)}{button('Underline', Underline, 'underline', active.underline)}</div>
        <div className="legal-tool-group" role="group" aria-label="Lists">{button('Bullets', List, 'insertUnorderedList')}{button('Numbers', ListOrdered, 'insertOrderedList')}</div>
        <div className="legal-tool-group" role="group" aria-label="Alignment">{button('Align left', AlignLeft, 'justifyLeft')}{button('Align center', AlignCenter, 'justifyCenter')}{button('Align right', AlignRight, 'justifyRight')}{button('Justify', AlignJustify, 'justifyFull')}</div>
        <div className="legal-tool-group" role="group" aria-label="Links">{button('Add link', LinkIcon, null, false, addLink)}{button('Remove link', Unlink, 'unlink')}</div>
        <div className="legal-tool-group" role="group" aria-label="History and formatting">{button('Undo', Undo2, 'undo')}{button('Redo', Redo2, 'redo')}{button('Clear formatting', RemoveFormatting, 'removeFormat')}</div>
      </div>
    </div>
    <div className="legal-editor-hint"><Type size={14} /><span>Select text to format it, or place the cursor in a paragraph to change the whole paragraph.</span></div>
    {linkError && <p role="alert" className="px-4 pt-2 text-sm text-red-700">{linkError}</p>}
    <div ref={ref} contentEditable suppressContentEditableWarning role="textbox" aria-label="Legal page content" aria-multiline="true"
      onInput={() => { sync(); rememberSelection() }} onKeyDown={event => {
        if ((event.ctrlKey || event.metaKey) && ['z','y'].includes(event.key.toLowerCase())) {
          event.preventDefault(); restoreHistory(event.key.toLowerCase() === 'y' || event.shiftKey ? 1 : -1)
        }
      }} onKeyUp={rememberSelection} onMouseUp={rememberSelection} onBlur={rememberSelection}
      onPaste={event => {
        event.preventDefault()
        const rich = event.clipboardData.getData('text/html')
        const plain = event.clipboardData.getData('text/plain')
        const escaped = plain.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        const html = rich || `<p>${escaped.split(/\r?\n/).map(line => line || '<br>').join('</p><p>')}</p>`
        document.execCommand('insertHTML', false, sanitizeLegalHtml(html)); sync(); rememberSelection()
      }}
      className="tulip-legal-editor" />
  </div>
}
