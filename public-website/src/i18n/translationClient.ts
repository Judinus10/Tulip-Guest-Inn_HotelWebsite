const CACHE_KEY = 'tulip_mymemory_translation_cache_v2';
const MAX_ENTRIES = 1500;
const MAX_AGE = 30 * 24 * 60 * 60 * 1000;
const MAX_BYTES = 450;
const encoder = new TextEncoder();
type Entry = { value: string; time: number };
const cache = new Map<string, Entry>();
const pending = new Map<string, Promise<string>>();
let loaded = false;
let activeRequests = 0;
const waiting: Array<() => void> = [];
let quotaBlockedUntil = 0;
const keyFor = (language: string, text: string) => JSON.stringify([language, text]);

function loadCache() {
  if (loaded) return;
  loaded = true;
  try {
    const entries: unknown = JSON.parse(localStorage.getItem(CACHE_KEY) || '[]');
    if (!Array.isArray(entries)) return;
    for (const row of entries.slice(-MAX_ENTRIES)) {
      if (Array.isArray(row) && typeof row[0] === 'string' && typeof row[1]?.value === 'string'
        && typeof row[1]?.time === 'number' && Date.now() - row[1].time < MAX_AGE) cache.set(row[0], row[1]);
    }
  } catch { /* Caching is optional in private browsing. */ }
}
function persistCache() {
  while (cache.size > MAX_ENTRIES) cache.delete(cache.keys().next().value!);
  try { localStorage.setItem(CACHE_KEY, JSON.stringify([...cache])); } catch { /* Memory cache still works. */ }
}
function cached(key: string): string | undefined {
  const entry = cache.get(key);
  if (entry && Date.now() - entry.time < MAX_AGE) return entry.value;
  cache.delete(key); return undefined;
}

// MyMemory limits each segment by UTF-8 bytes. Never silently skip long copy.
function segments(text: string): string[] {
  const parts: string[] = [];
  let buffer = ''; let bytes = 0;
  for (const character of text) {
    const size = encoder.encode(character).length;
    if (bytes + size > MAX_BYTES) {
      const boundary = buffer.lastIndexOf(' ');
      if (boundary > buffer.length / 2) {
        parts.push(buffer.slice(0, boundary).trim()); buffer = buffer.slice(boundary + 1);
      } else { parts.push(buffer.trim()); buffer = ''; }
      bytes = encoder.encode(buffer).length;
    }
    buffer += character; bytes += size;
  }
  if (buffer.trim()) parts.push(buffer.trim());
  return parts;
}
function decodeEntities(value: string): string {
  const named: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };
  return value.replace(/&(#x[0-9a-f]+|#\d+|amp|lt|gt|quot|apos|nbsp);/gi, (match, entity: string) => {
    if (!entity.startsWith('#')) return named[entity.toLowerCase()] ?? match;
    const code = entity[1].toLowerCase() === 'x' ? parseInt(entity.slice(2), 16) : parseInt(entity.slice(1), 10);
    return code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : match;
  });
}
async function networkRequest(text: string, language: string): Promise<string> {
  if (activeRequests >= 3) await new Promise<void>(resolve => waiting.push(resolve));
  else activeRequests++;
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), 10000);
  try {
    if (Date.now() < quotaBlockedUntil) throw new Error('The free translation service has reached its limit. Please use English and try later.');
    const query = new URLSearchParams({ q: text, langpair: `en|${language}` });
    const response = await fetch(`https://api.mymemory.translated.net/get?${query}`, {
      signal: controller.signal, credentials: 'omit', referrerPolicy: 'no-referrer',
    });
    const payload = await response.json().catch(() => null);
    const providerStatus = Number(payload?.responseStatus);
    if (response.status === 429 || providerStatus === 429 || payload?.quotaFinished === true) {
      quotaBlockedUntil = Date.now() + 5 * 60 * 1000;
      throw new Error('The free translation service has reached its limit. Please use English and try later.');
    }
    if (!response.ok || providerStatus !== 200 || typeof payload?.responseData?.translatedText !== 'string'
      || !payload.responseData.translatedText.trim()) {
      throw new Error('The free translation service is unavailable for this request. Please use English or try again later.');
    }
    return decodeEntities(payload.responseData.translatedText);
  } catch (error) {
    if (controller.signal.aborted) throw new Error('Translation took too long. Please use English or retry.');
    throw error;
  } finally {
    window.clearTimeout(timer);
    const next = waiting.shift();
    if (next) next(); else activeRequests--;
  }
}
async function translateSegment(text: string, language: string): Promise<string> {
  const key = keyFor(language, text);
  const hit = cached(key);
  if (hit !== undefined) return hit;
  const existing = pending.get(key);
  if (existing) return existing;
  const request = networkRequest(text, language).then(value => {
    cache.set(key, { value, time: Date.now() }); persistCache(); return value;
  }).finally(() => { pending.delete(key); });
  pending.set(key, request); return request;
}

export async function translateBatch(texts: string[], language: string, signal?: AbortSignal,
  onResult?: (index: number, value: string) => void): Promise<string[]> {
  loadCache();
  const results = new Array<string>(texts.length);
  const unique = [...new Set(texts)];
  const indexes = new Map<string, number[]>();
  texts.forEach((text, index) => indexes.set(text, [...(indexes.get(text) || []), index]));
  let nextIndex = 0; let failure: unknown;
  const worker = async () => {
    while (nextIndex < unique.length && !failure) {
      if (signal?.aborted) return;
      const source = unique[nextIndex++];
      try {
        const key = keyFor(language, source);
        let value = cached(key);
        if (value === undefined) {
          const pieces: string[] = [];
          for (const part of segments(source)) {
            if (signal?.aborted || failure) return;
            pieces.push(await translateSegment(part, language));
          }
          value = pieces.join(' ');
          if (signal?.aborted || failure) return;
          cache.set(key, { value, time: Date.now() }); persistCache();
        }
        if (signal?.aborted || failure) return;
        for (const index of indexes.get(source) || []) {
          results[index] = value; onResult?.(index, value);
        }
      } catch (error) { failure = error; }
    }
  };
  await Promise.all(Array.from({ length: Math.min(3, unique.length) }, worker));
  if (signal?.aborted) throw new DOMException('Aborted', 'AbortError');
  if (failure) throw failure;
  return results;
}
