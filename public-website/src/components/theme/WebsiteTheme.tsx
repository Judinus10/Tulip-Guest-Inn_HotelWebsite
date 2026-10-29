import { useEffect } from 'react';
import { requestJson } from '../../services/apiClient';
import { isThemeColors, themeVariables } from '../../services/themeColors';

export default function WebsiteTheme() {
  useEffect(() => {
    let active = true;
    let pending = false;
    async function refresh() {
      if (!active || pending || document.visibilityState === 'hidden') return;
      pending = true;
      try {
        const settings = await requestJson<{ colors: unknown }>('/settings/theme.php', { cache: 'no-store' });
        if (!active || !isThemeColors(settings.colors)) return;
        const variables = themeVariables(settings.colors);
        for (const [name, value] of Object.entries(variables)) {
          document.documentElement.style.setProperty(name, value);
        }
      } catch {
        // Initial failures keep the built-in palette. Later failures keep the last valid palette.
        // Theme loading must never block booking or hide the website.
      } finally {
        pending = false;
      }
    }
    void refresh();
    const onVisible = () => { if (document.visibilityState === 'visible') void refresh(); };
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', onVisible);
    return () => {
      active = false;
      window.removeEventListener('focus', refresh);
      document.removeEventListener('visibilitychange', onVisible);
    };
  }, []);
  return null;
}
