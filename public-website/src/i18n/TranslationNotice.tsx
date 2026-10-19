import { useLanguage } from './LanguageContext';

export default function TranslationNotice() {
  const { language, translating, translationError, setLanguage } = useLanguage();
  if (language.code === 'en') return null;
  return <aside data-no-translate dir="ltr" translate="no" className="fixed bottom-4 left-4 z-[100] max-w-[calc(100vw-2rem)] rounded-lg border border-border bg-white px-4 py-3 text-xs text-dark shadow-lg" aria-live="polite">
    {translationError ? <>
      <p role="alert" className="max-w-sm text-red-700">{translationError}</p>
      <button type="button" className="mt-2 underline" onClick={() => setLanguage('en')}>Use English</button>
      <button type="button" className="ml-4 mt-2 underline" onClick={() => window.location.reload()}>Retry</button>
    </> : <p>{translating ? 'Translating… ' : 'Automatic translation · '}<a href="https://mymemory.translated.net/" target="_blank" rel="noopener noreferrer" className="underline">MyMemory</a></p>}
  </aside>;
}
