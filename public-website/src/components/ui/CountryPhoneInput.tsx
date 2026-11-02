import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';

type CallingCode = { country: string; code: string; flag?: string };

// Sri Lanka is the default. The full list includes the most common guest origins
// and major destinations; duplicate calling codes are shown by country name.
const callingCodes: CallingCode[] = [
  { country: 'Afghanistan', code: '93' }, { country: 'Albania', code: '355' },
  { country: 'Algeria', code: '213' }, { country: 'Argentina', code: '54' },
  { country: 'Armenia', code: '374' }, { country: 'Australia', code: '61' },
  { country: 'Austria', code: '43' }, { country: 'Bahrain', code: '973' },
  { country: 'Bangladesh', code: '880' }, { country: 'Belgium', code: '32' },
  { country: 'Bhutan', code: '975' }, { country: 'Brazil', code: '55' },
  { country: 'Brunei', code: '673' }, { country: 'Bulgaria', code: '359' },
  { country: 'Cambodia', code: '855' },
  { country: 'Chile', code: '56' }, { country: 'China', code: '86' },
  { country: 'Colombia', code: '57' }, { country: 'Croatia', code: '385' },
  { country: 'Cyprus', code: '357' }, { country: 'Czechia', code: '420' },
  { country: 'Denmark', code: '45' }, { country: 'Egypt', code: '20' },
  { country: 'Estonia', code: '372' }, { country: 'Ethiopia', code: '251' },
  { country: 'Finland', code: '358' }, { country: 'France', code: '33' },
  { country: 'Georgia', code: '995' }, { country: 'Germany', code: '49' },
  { country: 'Ghana', code: '233' }, { country: 'Greece', code: '30' },
  { country: 'Hong Kong', code: '852' }, { country: 'Hungary', code: '36' },
  { country: 'Iceland', code: '354' }, { country: 'India', code: '91' },
  { country: 'Indonesia', code: '62' }, { country: 'Iran', code: '98' },
  { country: 'Iraq', code: '964' }, { country: 'Ireland', code: '353' },
  { country: 'Israel', code: '972' }, { country: 'Italy', code: '39' },
  { country: 'Japan', code: '81' }, { country: 'Jordan', code: '962' },
  { country: 'Kenya', code: '254' }, { country: 'Kuwait', code: '965' },
  { country: 'Laos', code: '856' }, { country: 'Latvia', code: '371' },
  { country: 'Lebanon', code: '961' }, { country: 'Lithuania', code: '370' },
  { country: 'Luxembourg', code: '352' }, { country: 'Macau', code: '853' },
  { country: 'Malaysia', code: '60' }, { country: 'Maldives', code: '960' },
  { country: 'Malta', code: '356' }, { country: 'Mauritius', code: '230' },
  { country: 'Mexico', code: '52' }, { country: 'Monaco', code: '377' },
  { country: 'Mongolia', code: '976' }, { country: 'Morocco', code: '212' },
  { country: 'Myanmar', code: '95' }, { country: 'Nepal', code: '977' },
  { country: 'Netherlands', code: '31' }, { country: 'New Zealand', code: '64' },
  { country: 'Nigeria', code: '234' }, { country: 'Norway', code: '47' },
  { country: 'Oman', code: '968' }, { country: 'Pakistan', code: '92' },
  { country: 'Palestine', code: '970' }, { country: 'Panama', code: '507' },
  { country: 'Peru', code: '51' }, { country: 'Philippines', code: '63' },
  { country: 'Poland', code: '48' }, { country: 'Portugal', code: '351' },
  { country: 'Qatar', code: '974' }, { country: 'Romania', code: '40' },
  { country: 'Russia', code: '7' }, { country: 'Saudi Arabia', code: '966' },
  { country: 'Serbia', code: '381' }, { country: 'Singapore', code: '65' },
  { country: 'Slovakia', code: '421' }, { country: 'Slovenia', code: '386' },
  { country: 'South Africa', code: '27' }, { country: 'South Korea', code: '82' },
  { country: 'Spain', code: '34' }, { country: 'Sri Lanka', code: '94' },
  { country: 'Sweden', code: '46' }, { country: 'Switzerland', code: '41' },
  { country: 'Taiwan', code: '886' }, { country: 'Tanzania', code: '255' },
  { country: 'Thailand', code: '66' }, { country: 'Tunisia', code: '216' },
  { country: 'Turkey', code: '90' }, { country: 'Ukraine', code: '380' },
  { country: 'United Arab Emirates', code: '971' }, { country: 'United Kingdom', code: '44' },
  { country: 'US / Canada', code: '1' }, { country: 'Vietnam', code: '84' },
];

type Props = {
  value: string;
  countryCode: string;
  onChange: (value: string) => void;
  onCountryCodeChange: (code: string) => void;
  id?: string;
  name?: string;
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
  autoComplete?: string;
  'aria-invalid'?: boolean;
  'aria-describedby'?: string;
};

export default function CountryPhoneInput({
  value, countryCode, onChange, onCountryCodeChange, id, name = 'phone',
  placeholder = 'Phone number', required, disabled, autoComplete = 'tel-national',
  'aria-invalid': ariaInvalid, 'aria-describedby': ariaDescribedBy,
}: Props) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [dialInput, setDialInput] = useState('+' + countryCode);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  useEffect(() => { setDialInput('+' + countryCode); }, [countryCode]);
  useEffect(() => {
    if (!open) return;
    searchRef.current?.focus();
    const close = (event: MouseEvent) => {
      if (!wrapperRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, [open]);

  const countryCodes: Record<string, string> = {
    Afghanistan:'AF', Albania:'AL', Algeria:'DZ', Argentina:'AR', Armenia:'AM', Australia:'AU', Austria:'AT', Bahrain:'BH', Bangladesh:'BD', Belgium:'BE', Bhutan:'BT', Brazil:'BR', Brunei:'BN', Bulgaria:'BG', Cambodia:'KH', Chile:'CL', China:'CN', Colombia:'CO', Croatia:'HR', Cyprus:'CY', Denmark:'DK', Egypt:'EG', Estonia:'EE', Ethiopia:'ET', Finland:'FI', France:'FR', Georgia:'GE', Germany:'DE', Ghana:'GH', Greece:'GR', Hungary:'HU', Iceland:'IS', India:'IN', Indonesia:'ID', Iran:'IR', Iraq:'IQ', Ireland:'IE', Israel:'IL', Italy:'IT', Japan:'JP', Jordan:'JO', Kenya:'KE', Kuwait:'KW', Laos:'LA', Latvia:'LV', Lebanon:'LB', Lithuania:'LT', Luxembourg:'LU', Malaysia:'MY', Maldives:'MV', Malta:'MT', Mauritius:'MU', Mexico:'MX', Monaco:'MC', Mongolia:'MN', Morocco:'MA', Myanmar:'MM', Nepal:'NP', 'New Zealand':'NZ', Nigeria:'NG', Norway:'NO', Oman:'OM', Pakistan:'PK', Panama:'PA', Peru:'PE', Philippines:'PH', Poland:'PL', Portugal:'PT', Qatar:'QA', Romania:'RO', 'Saudi Arabia':'SA', Serbia:'RS', Singapore:'SG', Slovakia:'SK', Slovenia:'SI', 'South Africa':'ZA', Spain:'ES', Sweden:'SE', Switzerland:'CH', Tanzania:'TZ', Thailand:'TH', Tunisia:'TN', Turkey:'TR', Ukraine:'UA'
  };
  const flagFor = (country: string) => {
    const names: Record<string, string> = {
      'Sri Lanka':'LK','United Kingdom':'GB','US / Canada':'US','Netherlands':'NL',
      'South Korea':'KR','Russia':'RU','Vietnam':'VN','United Arab Emirates':'AE',
      'Hong Kong':'HK','Macau':'MO','Taiwan':'TW','Palestine':'PS','Czechia':'CZ',
    };
    const code = names[country] || countryCodes[country];
    return code || null;
  };
  const flagImage = (country: string) => {
    const iso = flagFor(country);
    return iso ? <img src={`https://flagcdn.com/24x18/${iso.toLowerCase()}.png`} alt={`${country} flag`} width={20} height={15} className="inline-block h-[15px] w-5 shrink-0 object-cover" loading="lazy" /> : <span aria-hidden="true">🌐</span>;
  };
  const selected = callingCodes.find(item => item.code === countryCode);
  const filtered = callingCodes.filter(item =>
    item.country.toLowerCase().includes(search.toLowerCase()) || ('+' + item.code).includes(search.trim())
  );
  const handleNumberChange = (event: ChangeEvent<HTMLInputElement>) => {
    const raw = event.target.value;
    if (raw.trim().startsWith('+')) {
      const digits = raw.replace(/\D/g, '');
      const match = [...callingCodes].sort((a,b) => b.code.length - a.code.length)
        .find(item => digits.startsWith(item.code));
      if (match) {
        onCountryCodeChange(match.code);
        onChange(digits.slice(match.code.length, 15));
      } else {
        onChange(digits.slice(0, 15));
      }
      return;
    }
    onChange(raw.replace(/\D/g, '').slice(0, Math.max(4, 15 - countryCode.length)));
  };
  const updateDial = (raw: string) => {
    const digits = raw.replace(/\D/g, '').slice(0, 4);
    setDialInput('+' + digits);
    if (digits) onCountryCodeChange(digits);
  };

  return (
    <div ref={wrapperRef} className="relative flex min-w-0 w-full">
      <div className="relative flex shrink-0 border border-r-0 border-border bg-background focus-within:border-gold disabled:opacity-60">
        <button type="button" aria-label="Choose country" aria-expanded={open}
          disabled={disabled} onClick={() => { setSearch(''); setOpen(v => !v); }}
          className="flex items-center gap-1 pl-2 text-sm text-dark">
          <span aria-hidden="true">{selected ? flagImage(selected.country) : '🌐'}</span>
          <span aria-hidden="true" className="text-[10px]">▾</span>
        </button>
        <input aria-label="Country dialing code" type="tel" inputMode="tel"
          value={dialInput} onChange={e => updateDial(e.target.value)} disabled={disabled}
          className="w-[53px] bg-transparent px-1 text-sm text-dark outline-hidden sm:w-[59px]" />
        {open && <div className="absolute left-0 top-full z-50 mt-1 w-[min(320px,85vw)] overflow-hidden border border-border bg-white shadow-lg">
          <input ref={searchRef} type="text" aria-label="Search country or dialing code"
            placeholder="Search country or code" value={search} onChange={e => setSearch(e.target.value)}
            className="w-full border-b border-border bg-white px-3 py-3 text-sm outline-hidden focus:border-gold" />
          <div className="max-h-56 overflow-y-auto" role="listbox" aria-label="Countries">
            {filtered.map(item => <button type="button" role="option" aria-selected={item.code === countryCode}
              key={`${item.country}-${item.code}`} onClick={() => { onCountryCodeChange(item.code); setOpen(false); }}
              className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-dark hover:bg-gray-100 focus:bg-gray-100">
              <span className="flex w-5 shrink-0 items-center">{flagImage(item.country)}</span><span className="flex-1">{item.country}</span><span className="text-gray-500">+{item.code}</span>
            </button>)}
            {!filtered.length && <div className="px-3 py-3 text-sm text-gray-500">No countries found</div>}
          </div>
        </div>}
      </div>
      <input
        id={id} name={name} type="tel" value={value} onChange={handleNumberChange}
        inputMode="tel" maxLength={Math.max(4, 15 - countryCode.length)}
        autoComplete={autoComplete} required={required} disabled={disabled}
        aria-invalid={ariaInvalid} aria-describedby={ariaDescribedBy}
        placeholder={placeholder}
        className="min-w-0 w-full border border-border bg-background px-4 py-3.5 text-sm text-dark outline-hidden transition-colors duration-200 placeholder-gray-400 focus:border-gold disabled:opacity-60"
      />
    </div>
  );
}
