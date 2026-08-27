import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useLanguage } from './LanguageContext';

const ignored = '[data-no-translate], script, style, noscript, svg, canvas, code, pre, textarea';
const originalText = new WeakMap<Text, string>();
const translatedLanguage = new WeakMap<Text, string>();
const originalPlaceholder = new WeakMap<HTMLInputElement | HTMLTextAreaElement, string>();
const placeholderLanguage = new WeakMap<HTMLInputElement | HTMLTextAreaElement, string>();

export default function AutoTranslate() {
  const location = useLocation();
  const { language, translateText } = useLanguage();
  useEffect(() => {
    let cancelled = false;
    let translating = false;
    const translatePage = async () => {
      if (translating) return;
      translating = true;
      const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, { acceptNode(node) {
        if (node.parentElement?.closest(ignored)) return NodeFilter.FILTER_REJECT;
        return node.nodeValue?.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }});
      const nodes: Text[] = []; while (walker.nextNode()) nodes.push(walker.currentNode as Text);
      const placeholders = Array.from(document.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input[placeholder], textarea[placeholder]')).filter((element) => !element.closest('[data-no-translate]'));
      for (const node of nodes) {
        if (cancelled) break;
        if (!node.parentElement) continue;
        const current = node.nodeValue?.replace(/\s+/g, ' ').trim() || '';
        const original = originalText.get(node) || current;
        if (!original) continue;
        if (!originalText.has(node)) originalText.set(node, original);
        if (language.code === 'en') {
          if (translatedLanguage.has(node)) {
            if (current !== original) node.nodeValue = node.nodeValue?.replace(current, original) || original;
            translatedLanguage.delete(node);
          } else if (current !== original) {
            // React may update an existing text node after an API request.
            // In English that new value is the authoritative source text.
            originalText.set(node, current);
          }
          continue;
        }
        if (translatedLanguage.get(node) === language.code) continue;
        const translated = await translateText(original); if (cancelled) return;
        node.nodeValue = node.nodeValue?.replace(current, translated) || translated;
        translatedLanguage.set(node, language.code);
      }
      for (const element of placeholders) {
        if (cancelled) break;
        const original = originalPlaceholder.get(element) || element.placeholder;
        if (!originalPlaceholder.has(element)) originalPlaceholder.set(element, original);
        if (language.code === 'en') { element.placeholder = original; placeholderLanguage.delete(element); continue; }
        if (placeholderLanguage.get(element) === language.code) continue;
        element.placeholder = await translateText(original); placeholderLanguage.set(element, language.code);
      }
      translating = false;
    };
    let timer = window.setTimeout(translatePage, 120);
    const observer = new MutationObserver(() => { if (translating) return; window.clearTimeout(timer); timer = window.setTimeout(translatePage, 250); });
    observer.observe(document.body, { childList: true, subtree: true });
    return () => { cancelled = true; window.clearTimeout(timer); observer.disconnect(); };
  }, [language, location.pathname, location.search, translateText]);
  return null;
}
