import { useEffect, useState } from 'react';
import { FaWhatsapp } from 'react-icons/fa';
import './WhatsAppButton.css';
import { getPublicContactSettings } from '../../services/contactSettingsApi';

export default function WhatsAppButton() {
  const [number, setNumber] = useState('');

  useEffect(() => {
    let active = true;
    getPublicContactSettings()
      .then((settings) => {
        if (!active) return;
        const savedNumber = (settings.whatsapp_reservation_number || '').trim();
        // wa.me requires an international number with no spaces or plus sign.
        const digits = savedNumber.replace(/[^0-9]/g, '');
        setNumber(/^[1-9][0-9]{6,14}$/.test(digits) ? digits : '');
      })
      .catch(() => { if (active) setNumber(''); });
    return () => { active = false; };
  }, []);

  if (!number) return null;

  return (
    <a
      href={`https://wa.me/${number}`}
      target="_blank"
      rel="noopener noreferrer"
      aria-label="Chat with Tulip Guest Inn on WhatsApp (opens in a new tab)"
      title="Chat on WhatsApp"
      data-no-translate="true"
      style={{
        bottom: 'calc(1rem + env(safe-area-inset-bottom, 0px))',
        left: 'max(1rem, env(safe-area-inset-left, 0px))',
      }}
      className="tulip-whatsapp-float fixed z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg transition-colors hover:bg-[#128C7E] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#128C7E] print:hidden"
    >
      <FaWhatsapp size={30} aria-hidden="true" />
    </a>
  );
}
