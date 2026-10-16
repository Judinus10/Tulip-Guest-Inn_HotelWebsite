import { useEffect, useRef, useState } from 'react'
import { Bold, Italic, Underline, List, ListOrdered, Undo2, Redo2, RemoveFormatting, AlignLeft, AlignCenter } from 'lucide-react'

export default function LegalRichTextEditor({ value, onChange }) {
 const ref = useRef(null)
 const selectionRef = useRef(null)
 const [active, setActive] = useState({})
 useEffect(() => {
  if (ref.current && ref.current.innerHTML !== (value || '') && !ref.current.contains(document.activeElement)) ref.current.innerHTML = value || ''
 }, [value])
 const sync = () => onChange(ref.current?.innerHTML || '')
 useEffect(() => { document.execCommand('defaultParagraphSeparator', false, 'p') }, [])
 const rememberSelection = () => {
  const selection = window.getSelection()
  if (selection?.rangeCount && ref.current?.contains(selection.anchorNode)) selectionRef.current = selection.getRangeAt(0).cloneRange()
  if (ref.current?.contains(selection?.anchorNode)) setActive({bold:document.queryCommandState('bold'),italic:document.queryCommandState('italic'),underline:document.queryCommandState('underline')})
 }
 const run = (command, arg) => {
  const editor = ref.current
  if (!editor) return
  editor.focus()
  const range = selectionRef.current
  if (range && editor.contains(range.commonAncestorContainer)) {
   const selection = window.getSelection()
   selection.removeAllRanges(); selection.addRange(range)
  }
  document.execCommand(command, false, arg)
  rememberSelection(); sync()
 }
 const btn = (name, Icon, command, arg, selected=false) => (
  <button key={name} type="button" title={name} aria-label={name} aria-pressed={selected}
   onMouseDown={e => e.preventDefault()} onClick={() => run(command,arg)}
   className={`inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm hover:bg-slate-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500 ${selected?'bg-blue-100 text-blue-700':'text-slate-700'}`}>
   {Icon && <Icon className="h-4 w-4"/>}{name==='Heading'||name==='Text' ? name : null}
  </button>
 )
 return <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
  <div className="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 p-2" onMouseDown={e=>{if(e.target.closest('button'))e.preventDefault()}}>
   {btn('Heading',null,'formatBlock','H2')}{btn('Text',null,'formatBlock','P')}
   <span className="mx-1 h-6 w-px bg-slate-200"/>
   {btn('Bold',Bold,'bold',undefined,active.bold)}{btn('Italic',Italic,'italic',undefined,active.italic)}{btn('Underline',Underline,'underline',undefined,active.underline)}
   <span className="mx-1 h-6 w-px bg-slate-200"/>
   {btn('Bullets',List,'insertUnorderedList')}{btn('Numbers',ListOrdered,'insertOrderedList')}
   {btn('Align left',AlignLeft,'justifyLeft')}{btn('Align center',AlignCenter,'justifyCenter')}
   <span className="mx-1 h-6 w-px bg-slate-200"/>
   {btn('Undo',Undo2,'undo')}{btn('Redo',Redo2,'redo')}{btn('Clear formatting',RemoveFormatting,'removeFormat')}
  </div>
  <div ref={ref} contentEditable suppressContentEditableWarning role="textbox" aria-label="Legal page content" aria-multiline="true"
   onInput={sync} onKeyDown={e=>{ if(e.key==='Enter' && !e.shiftKey) document.execCommand('defaultParagraphSeparator', false, 'p') }} onKeyUp={rememberSelection} onMouseUp={rememberSelection} onBlur={rememberSelection}
   onPaste={e=>{e.preventDefault();const plain=e.clipboardData.getData('text/plain');const safe=plain.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');const paragraphs=safe.split(/\r?\n/).map(line=>line||'<br>').join('</p><p>');document.execCommand('insertHTML',false,`<p>${paragraphs}</p>`);sync()}}
   className="min-h-[440px] max-h-[70vh] overflow-y-auto px-6 py-5 text-base leading-7 text-slate-700 outline-none focus:ring-2 focus:ring-inset focus:ring-blue-200 [&_h2]:my-6 [&_h2]:font-serif [&_h2]:text-2xl [&_h2]:font-normal [&_h2]:leading-snug [&_p]:my-3 [&_p:empty]:min-h-[1.75rem] [&_div]:my-3 [&_div:empty]:min-h-[1.75rem] [&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-7 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-7" />
 </div>
}
