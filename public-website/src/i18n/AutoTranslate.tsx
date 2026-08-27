import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useLanguage } from './LanguageContext';

const translatedAttr = 'data-translated-language';
const originalTextAttr = 'data-original-text';
const originalPlaceholderAttr = 'data-original-placeholder';
const ignored = '[data-no-translate], script, style, noscript, svg, canvas, code, pre, textarea';

export default function AutoTranslate() {
  const location = useLocation();
  const { language, translateText } = useLanguage();
  useEffect(() => {
    let cancelled = false;
    const translatePage = async () => {
      const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, { acceptNode(node) {
        if (node.parentElement?.closest(ignored)) return NodeFilter.FILTER_REJECT;
        return node.nodeValue?.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }});
      const nodes: Text[] = []; while (walker.nextNode()) nodes.push(walker.currentNode as Text);
      const placeholders = Array.from(document.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input[placeholder], textarea[placeholder]')).filter((element) => !element.closest('[data-no-translate]'));
      for (const node of nodes) {
        if (cancelled || !node.parentElement) return;
        const current = node.nodeValue?.replace(/\s+/g, ' ').trim() || '';
        const original = node.parentElement.getAttribute(originalTextAttr) || current;
        if (!original) continue;
        node.parentElement.setAttribute(originalTextAttr, original);
        if (language.code === 'en') { node.nodeValue = node.nodeValue?.replace(current, original) || original; node.parentElement.removeAttribute(translatedAttr); continue; }
        if (node.parentElement.getAttribute(translatedAttr) === language.code) continue;
        const translated = await translateText(original); if (cancelled) return;
        node.nodeValue = node.nodeValue?.replace(current, translated) || translated;
        node.parentElement.setAttribute(translatedAttr, language.code);
      }
      for (const element of placeholders) {
        if (cancelled) return;
        const original = element.getAttribute(originalPlaceholderAttr) || element.placeholder;
        element.setAttribute(originalPlaceholderAttr, original);
        if (language.code === 'en') { element.placeholder = original; element.removeAttribute(translatedAttr); continue; }
        if (element.getAttribute(translatedAttr) === language.code) continue;
        element.placeholder = await translateText(original); element.setAttribute(translatedAttr, language.code);
      }
    };
    let timer = window.setTimeout(translatePage, 120);
    const observer = new MutationObserver(() => { window.clearTimeout(timer); timer = window.setTimeout(translatePage, 250); });
    observer.observe(document.body, { childList: true, subtree: true });
    return () => { cancelled = true; window.clearTimeout(timer); observer.disconnect(); };
  }, [language, location.pathname, location.search, translateText]);
  return null;
}
