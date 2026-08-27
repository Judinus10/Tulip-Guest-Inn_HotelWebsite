export type Language = { code: string; apiCode: string; label: string; dir: 'ltr' | 'rtl' };
export const LANGUAGES: Language[] = [
  { code: 'en', apiCode: 'en', label: 'English', dir: 'ltr' },
  { code: 'ta', apiCode: 'ta', label: 'தமிழ்', dir: 'ltr' },
  { code: 'si', apiCode: 'si', label: 'සිංහල', dir: 'ltr' },
  { code: 'zh-CN', apiCode: 'zh-CN', label: '中文', dir: 'ltr' },
  { code: 'ru', apiCode: 'ru', label: 'Русский', dir: 'ltr' },
  { code: 'de', apiCode: 'de', label: 'Deutsch', dir: 'ltr' },
  { code: 'fr', apiCode: 'fr', label: 'Français', dir: 'ltr' },
  { code: 'ar', apiCode: 'ar', label: 'العربية', dir: 'rtl' },
];
export const DEFAULT_LANGUAGE = LANGUAGES[0];
export const getLanguageByCode = (code: string) => LANGUAGES.find((language) => language.code === code) || DEFAULT_LANGUAGE;
