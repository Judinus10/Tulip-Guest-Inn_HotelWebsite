import { FaFacebookF, FaInstagram, FaYoutube, FaTiktok, FaLinkedinIn, FaGlobe } from 'react-icons/fa';
import { FaXTwitter } from 'react-icons/fa6';
import type { ContactSettings } from './contactSettingsApi';
const icons = { facebook: FaFacebookF, instagram: FaInstagram, youtube: FaYoutube, tiktok: FaTiktok, x: FaXTwitter, linkedin: FaLinkedinIn, other: FaGlobe };
const names = { facebook: 'Facebook', instagram: 'Instagram', youtube: 'YouTube', tiktok: 'TikTok', x: 'X', linkedin: 'LinkedIn', other: 'Social media' };
export function getSocialLinks(settings: Pick<ContactSettings, 'social_links' | 'facebook_link' | 'instagram_link'>) {
  let items: unknown;
  if (settings.social_links) {
    try { items = JSON.parse(settings.social_links); } catch { return []; }
  } else {
    items = [{ platform: 'instagram', url: settings.instagram_link }, { platform: 'facebook', url: settings.facebook_link }];
  }
  if (!Array.isArray(items)) return [];
  return items.slice(0, 20).flatMap(item => {
    if (!item || typeof item !== 'object' || typeof item.url !== 'string' || !Object.prototype.hasOwnProperty.call(icons, item.platform)) return [];
    try {
      const url = new URL(item.url.trim());
      if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password) return [];
      const platform = item.platform as keyof typeof icons;
      return [{ href: url.href, label: platform === 'other' && typeof item.label === 'string' && item.label.trim() ? item.label.trim() : names[platform], icon: icons[platform] }];
    } catch { return []; }
  });
}
