import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useLanguage } from './LanguageContext';

type RecordState = { source: string; applied: string };
type Target = { node: Node; attribute: string; state: RecordState; snapshot: string };
const records = new WeakMap<Node, Map<string, RecordState>>();
const ignored = '[data-no-translate], [translate="no"], .tulip-verification, [role="alert"], [role="status"], script, style, noscript, svg, canvas, code, pre, textarea, option:not([value])';
const read = (target: Pick<Target, 'node' | 'attribute'>) => target.attribute
  ? (target.node as Element).getAttribute(target.attribute) || '' : target.node.nodeValue || '';
const write = (target: Target, value: string) => {
  if (target.attribute) (target.node as Element).setAttribute(target.attribute, value);
  else target.node.nodeValue = value;
  target.state.applied = value;
};
const eligible = (text: string) => /\p{L}/u.test(text) && !/@|https?:\/\/|www\./i.test(text);

function targets(): Target[] {
  const found: Target[] = [];
  const add = (node: Node, attribute = '') => {
    const current = read({ node, attribute });
    if (!current.trim()) return;
    let entries = records.get(node);
    if (!entries) { entries = new Map(); records.set(node, entries); }
    let state = entries.get(attribute);
    // If React changes a text node, the new English value becomes its source.
    if (!state || current !== state.applied) {
      state = { source: current, applied: current }; entries.set(attribute, state);
    }
    if (eligible(state.source)) found.push({ node, attribute, state, snapshot: current });
  };
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
    acceptNode: node => node.parentElement?.closest(ignored) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT,
  });
  while (walker.nextNode()) add(walker.currentNode);
  document.querySelectorAll('[placeholder], [title], [aria-label], img[alt]').forEach(element => {
    if (element.closest(ignored)) return;
    for (const attribute of ['placeholder', 'title', 'aria-label', 'alt']) {
      if (element.hasAttribute(attribute)) add(element, attribute);
    }
  });
  return found;
}

export default function AutoTranslate() {
  const location = useLocation();
  const { language, translateTexts, setTranslationState } = useLanguage();
  useEffect(() => {
    const controller = new AbortController();
    let active = true; let busy = false; let dirty = false; let failed = false;
    let timer = 0;
    const observe = () => observer.observe(document.body, {
      childList: true, characterData: true, subtree: true,
      attributes: true, attributeFilter: ['placeholder', 'title', 'aria-label', 'alt'],
    });
    const apply = (items: Target[], values: string[]) => {
      // Avoid observing our own translations and starting an endless rescan.
      observer.disconnect();
      items.forEach((item, index) => {
        if (item.node.isConnected && read(item) === item.snapshot) { write(item, values[index]); item.snapshot = values[index]; }
        else dirty = true;
      });
      observe();
    };
    const run = async () => {
      if (!active || failed) return;
      if (busy) { dirty = true; return; }
      busy = true; dirty = false;
      const items = targets();
      try {
        if (language.code === 'en') {
          apply(items, items.map(item => item.state.source));
          setTranslationState(false);
        } else {
          setTranslationState(true);
          const sources = items.map(item => item.state.source.trim());
          const values = await translateTexts(sources, controller.signal, (index, translated) => {
            if (!active) return;
            const item = items[index];
            const source = item.state.source;
            const value = source.slice(0, source.length - source.trimStart().length)
              + translated + source.slice(source.trimEnd().length);
            apply([item], [value]);
          });
          if (!active) return;
          apply(items, items.map((item, index) => {
            const source = item.state.source;
            return source.slice(0, source.length - source.trimStart().length)
              + values[index] + source.slice(source.trimEnd().length);
          }));
          setTranslationState(false);
        }
      } catch (error) {
        if (!active) return;
        failed = true;
        apply(items, items.map(item => item.state.source));
        setTranslationState(false, error instanceof Error ? error.message : 'Translation is unavailable. Please use English.');
      } finally {
        busy = false;
        if (active && dirty && !failed) timer = window.setTimeout(run, 60);
      }
    };
    const observer = new MutationObserver(changes => {
      const relevant = changes.some(change => {
        const element = change.target instanceof Element ? change.target : change.target.parentElement;
        return !element?.closest(ignored);
      });
      if (!relevant || failed) return;
      if (busy) { dirty = true; return; }
      window.clearTimeout(timer); timer = window.setTimeout(run, 80);
    });
    observe(); timer = window.setTimeout(run, 0);
    return () => {
      active = false; controller.abort(); window.clearTimeout(timer); observer.disconnect();
    };
  }, [language, location.pathname, translateTexts, setTranslationState]);
  return null;
}
