import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { DEFAULT_LANGUAGE, getLanguageByCode, type Language } from './languages';
import { translateBatch } from './translationClient';

const STORAGE_KEY = 'tulip_guest_inn_language';
type LanguageApi = {
  language: Language;
  setLanguage: (code: string) => void;
  translateTexts: (texts: string[], signal?: AbortSignal, onResult?: (index: number, value: string) => void) => Promise<string[]>;
  translating: boolean;
  translationError: string;
  setTranslationState: (busy: boolean, error?: string) => void;
};
const LanguageContext = createContext<LanguageApi | null>(null);

export function LanguageProvider({ children }: { children: React.ReactNode }) {
  const [language, setLanguageState] = useState(() => {
    try { return getLanguageByCode(localStorage.getItem(STORAGE_KEY) || DEFAULT_LANGUAGE.code); }
    catch { return DEFAULT_LANGUAGE; }
  });
  const [translating, setTranslating] = useState(false);
  const [translationError, setTranslationError] = useState('');
  const setTranslationState = useCallback((busy: boolean, error = '') => {
    setTranslating(busy); setTranslationError(error);
  }, []);
  const setLanguage = useCallback((code: string) => {
    const next = getLanguageByCode(code);
    setLanguageState(next); setTranslationError('');
    try { localStorage.setItem(STORAGE_KEY, next.code); } catch { /* Storage is optional. */ }
  }, []);
  useEffect(() => {
    document.documentElement.lang = language.code;
    document.documentElement.dir = language.dir;
  }, [language]);
  const translateTexts = useCallback((texts: string[], signal?: AbortSignal, onResult?: (index: number, value: string) => void) =>
    language.code === 'en' ? Promise.resolve(texts) : translateBatch(texts, language.apiCode, signal, onResult), [language]);
  const value = useMemo(() => ({ language, setLanguage, translateTexts, translating, translationError, setTranslationState }),
    [language, setLanguage, translateTexts, translating, translationError, setTranslationState]);
  return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>;
}
export function useLanguage() {
  const value = useContext(LanguageContext);
  if (!value) throw new Error('useLanguage must be used inside LanguageProvider');
  return value;
}
