import { useEffect, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { LANGUAGES } from '../../i18n/languages';
import { useLanguage } from '../../i18n/LanguageContext';

export default function LanguageSwitcher({ light = false }: { light?: boolean }) {
  const [open, setOpen] = useState(false); const ref = useRef<HTMLDivElement>(null); const { language, setLanguage } = useLanguage();
  useEffect(() => { const close = (event: MouseEvent) => { if (!ref.current?.contains(event.target as Node)) setOpen(false); }; document.addEventListener('mousedown', close); return () => document.removeEventListener('mousedown', close); }, []);
  return <div ref={ref} className="relative" data-no-translate>
    <button type="button" onClick={() => setOpen((value) => !value)} className={`flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-[0.12em] transition-colors ${light ? 'text-white hover:text-gold' : 'text-dark hover:text-gold'}`} aria-haspopup="listbox" aria-expanded={open}>
      {language.code.toUpperCase()} <ChevronDown size={12} />
    </button>
    {open && <div className="absolute right-0 top-full mt-3 max-h-72 w-44 overflow-y-auto border border-border bg-white py-1 shadow-luxury-lg" role="listbox">
      {LANGUAGES.map((item) => <button type="button" key={item.code} onClick={() => { setLanguage(item.code); setOpen(false); }} className={`block w-full px-4 py-2.5 text-left text-xs ${language.code === item.code ? 'bg-gold/10 text-gold' : 'text-dark hover:bg-background'}`} role="option" aria-selected={language.code === item.code}>{item.label}</button>)}
    </div>}
  </div>;
}
