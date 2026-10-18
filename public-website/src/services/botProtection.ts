import { requestJson } from './apiClient';
type Turnstile = { render: (element: HTMLElement, options: Record<string, unknown>) => string; remove: (id: string) => void };
declare global { interface Window { turnstile?: Turnstile } }
let scriptPromise: Promise<void> | undefined;
export async function getBotToken(action: 'booking' | 'contact'): Promise<string> {
  const config = await requestJson<{bot_protection_required: boolean; turnstile_site_key: string}>('/settings/public-features.php');
  if (!config.bot_protection_required) return '';
  if (!config.turnstile_site_key) throw new Error('Form verification is unavailable. Please contact the property.');
  if (!window.turnstile) {
    scriptPromise ??= new Promise<void>((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
      script.onload = () => resolve(); script.onerror = () => { scriptPromise = undefined; reject(new Error('Unable to load security verification.')); };
      document.head.appendChild(script);
    });
    await scriptPromise;
  }
  return new Promise<string>((resolve, reject) => {
    const dialog = document.createElement('dialog');
    const title = document.createElement('p'); title.textContent = 'Complete the security check';
    const target = document.createElement('div'); const cancel = document.createElement('button'); cancel.textContent = 'Cancel';
    dialog.append(title, target, cancel); document.body.appendChild(dialog); dialog.showModal();
    let widget: string | undefined; let done = false;
    const finish = (token?: string) => { if (done) return; done = true; clearTimeout(timer); if (widget) window.turnstile?.remove(widget); dialog.remove(); token ? resolve(token) : reject(new Error('Security check cancelled or expired. Please try again.')); };
    const timer = setTimeout(() => finish(), 180000);
    cancel.onclick = () => finish(); dialog.oncancel = () => finish();
    try { widget = window.turnstile!.render(target, { sitekey: config.turnstile_site_key, action, callback: (token: string) => finish(token), 'error-callback': () => finish(), 'expired-callback': () => finish() }); }
    catch { finish(); }
  });
}
