import { useEffect, useState } from 'react';
import { getPublicContactSettings, type ContactSettings } from '../services/contactSettingsApi';

// Keep display text as entered in Website Settings; normalize only the call link.
export function getContactPhones(settings?: Partial<ContactSettings> | null) {
  const seen = new Set<string>();
  return [settings?.phone, settings?.reception_contact_number].flatMap((value) => {
    const number = value?.trim() || '';
    const dialNumber = number.replace(/[^+\d]/g, '');
    if (!/\d/.test(dialNumber) || seen.has(dialNumber)) return [];
    seen.add(dialNumber);
    return [{ number, href: `tel:${dialNumber}` }];
  });
}

export function useContactPhones() {
  const [settings, setSettings] = useState<ContactSettings | null>(null);
  useEffect(() => {
    let active = true;
    getPublicContactSettings()
      .then((data) => { if (active) setSettings(data); })
      .catch(() => { if (active) setSettings(null); });
    return () => { active = false; };
  }, []);
  return getContactPhones(settings);
}
