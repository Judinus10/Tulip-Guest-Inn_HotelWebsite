import { useEffect, useRef } from 'react'
import { Bold, Italic, List, ListOrdered, Undo2, Redo2, RemoveFormatting } from 'lucide-react'

export default function LegalRichTextEditor({ value, onChange }) {
 const ref = useRef(null)
 useEffect(() => {
  if (ref.current && ref.current.innerHTML !== (value || '')) ref.current.innerHTML = value || ''
 }, [value])
 const sync = () => onChange(ref.current?.innerHTML || '')
 const run = (command, arg) => {
  ref.current?.focus()
  document.execCommand(command, false, arg)
  sync()
 }
 const button = (name, Icon, command, arg) => (
  <button key={name} type="button" title={name} aria-label={name}
   onMouseDown={e=>e.preventDefault()} onClick={()=>run(command,arg)}
   className="rounded-lg p-2 text-text-primary hover:bg-slate-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
   <Icon className="h-4 w-4"/>
  </button>
 )
 return <div className="overflow-hidden rounded-xl border border-border bg-white">
  <div className="flex flex-wrap items-center gap-1 border-b border-border bg-slate-50 p-2">
   <select aria-label="Text style" defaultValue="p" onChange={e=>run('formatBlock',e.target.value)}
    className="rounded-lg border border-border bg-white px-2 py-2 text-sm">
    <option value="p">Normal Text</option><option value="h2">Heading</option>
   </select>
   {button('Bold',Bold,'bold')}{button('Italic',Italic,'italic')}
   <span className="mx-1 h-6 w-px bg-slate-200"/>
   {button('Bulleted list',List,'insertUnorderedList')}
   {button('Numbered list',ListOrdered,'insertOrderedList')}
   <span className="mx-1 h-6 w-px bg-slate-200"/>
   {button('Undo',Undo2,'undo')}{button('Redo',Redo2,'redo')}
   {button('Clear formatting',RemoveFormatting,'removeFormat')}
  </div>
  <div ref={ref} contentEditable suppressContentEditableWarning role="textbox" aria-label="Legal page content"
   aria-multiline="true" onInput={sync} onBlur={sync}
   onPaste={e=>{
    e.preventDefault()
    const text=e.clipboardData.getData('text/plain')
    document.execCommand('insertText',false,text)
    sync()
   }}
   className="legal-editor min-h-[400px] max-h-[65vh] overflow-y-auto px-6 py-5 text-sm leading-7 text-text-primary outline-none focus:ring-2 focus:ring-inset focus:ring-blue-200
    [&_h2]:my-4 [&_h2]:text-xl [&_h2]:font-semibold [&_p]:my-2 [&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-6"/>
 </div>
}
