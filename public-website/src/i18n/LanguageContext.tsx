import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { DEFAULT_LANGUAGE, getLanguageByCode, type Language } from './languages';

const STORAGE_KEY = 'tulip_guest_inn_language';
const CACHE_KEY = 'tulip_guest_inn_translation_cache_v1';
const TRANSLATE_API = 'https://api.mymemory.translated.net/get';
type LanguageApi = { language: Language; setLanguage: (code: string) => void; translateText: (text: string) => Promise<string> };
const LanguageContext = createContext<LanguageApi | null>(null);

const cleanText = (text: string) => String(text || '').replace(/\s+/g, ' ').trim();
const shouldSkip = (text: string) => !text || text.length > 450 || /^[\d\s.,:;!?()[\]{}+\-/%&|@#$*_=<>]+$/.test(text) || /^[A-Z]{2,6}$/.test(text) || /^LKR\b/i.test(text) || /^\+?\d[\d\s()-]+$/.test(text);
function readCache(): Record<string, string> { try { return JSON.parse(localStorage.getItem(CACHE_KEY) || '{}'); } catch { return {}; } }
function writeCache(cache: Record<string, string>) { try { localStorage.setItem(CACHE_KEY, JSON.stringify(cache)); } catch { /* Translation works without cache. */ } }

export function LanguageProvider({ children }: { children: React.ReactNode }) {
  const [language, setLanguageState] = useState(() => { try { return getLanguageByCode(localStorage.getItem(STORAGE_KEY) || DEFAULT_LANGUAGE.code); } catch { return DEFAULT_LANGUAGE; } });
  const setLanguage = useCallback((code: string) => { const next = getLanguageByCode(code); setLanguageState(next); try { localStorage.setItem(STORAGE_KEY, next.code); } catch { /* Ignore. */ } }, []);
  useEffect(() => { document.documentElement.lang = language.code; document.documentElement.dir = language.dir; }, [language]);
  const translateText = useCallback(async (text: string) => {
    const original = cleanText(text);
    if (language.code === 'en' || shouldSkip(original)) return original;
    const cache = readCache(); const key = `${language.code}::${original}`;
    if (cache[key]) return cache[key];
    try {
      const response = await fetch(`${TRANSLATE_API}?q=${encodeURIComponent(original)}&langpair=en|${encodeURIComponent(language.apiCode)}`);
      const data = await response.json();
      const translated = String(data?.responseData?.translatedText || '').trim();
      if (!response.ok || !translated) return original;
      cache[key] = translated; writeCache(cache); return translated;
    } catch { return original; }
  }, [language]);
  const value = useMemo(() => ({ language, setLanguage, translateText }), [language, setLanguage, translateText]);
  return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>;
}
export function useLanguage() { const value = useContext(LanguageContext); if (!value) throw new Error('useLanguage must be used inside LanguageProvider'); return value; }
