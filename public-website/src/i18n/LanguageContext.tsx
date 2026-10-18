import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { DEFAULT_LANGUAGE, getLanguageByCode, type Language } from './languages';

const STORAGE_KEY = 'tulip_guest_inn_language';
const CACHE_KEY = 'tulip_guest_inn_translation_cache_v1';
type LanguageApi = { language: Language; setLanguage: (code: string) => void; translateText: (text: string) => Promise<string> };
const LanguageContext = createContext<LanguageApi | null>(null);

const cleanText = (text: string) => String(text || '').replace(/\s+/g, ' ').trim();
export function LanguageProvider({ children }: { children: React.ReactNode }) {
  useEffect(() => { try { localStorage.removeItem(CACHE_KEY); } catch { /* no storage */ } }, []);
  const [language, setLanguageState] = useState(() => { try { return getLanguageByCode(localStorage.getItem(STORAGE_KEY) || DEFAULT_LANGUAGE.code); } catch { return DEFAULT_LANGUAGE; } });
  const setLanguage = useCallback((code: string) => { const next = getLanguageByCode(code); setLanguageState(next); try { localStorage.setItem(STORAGE_KEY, next.code); } catch { /* Ignore. */ } }, []);
  useEffect(() => { document.documentElement.lang = language.code; document.documentElement.dir = language.dir; }, [language]);
  const translateText = useCallback(async (text: string) => {
    const original = cleanText(text);
    const labels: Record<string, Record<string, string>> = {
      ta: { Home: 'முகப்பு', Rooms: 'அறைகள்', Contact: 'தொடர்பு', Gallery: 'படத்தொகுப்பு' },
      si: { Home: 'මුල් පිටුව', Rooms: 'කාමර', Contact: 'අමතන්න', Gallery: 'ගැලරිය' },
    };
    return labels[language.code]?.[original] ?? original;
  }, [language]);
  const value = useMemo(() => ({ language, setLanguage, translateText }), [language, setLanguage, translateText]);
  return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>;
}
export function useLanguage() { const value = useContext(LanguageContext); if (!value) throw new Error('useLanguage must be used inside LanguageProvider'); return value; }
