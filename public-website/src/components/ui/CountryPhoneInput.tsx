import type { ChangeEvent } from 'react';

type CallingCode = { country: string; code: string };

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
  const handleNumberChange = (event: ChangeEvent<HTMLInputElement>) => {
    const maxLength = Math.max(4, 15 - countryCode.length);
    onChange(event.target.value.replace(/\D/g, '').slice(0, maxLength));
  };

  return (
    <div className="flex min-w-0 w-full">
      <select
        aria-label="Country calling code"
        value={countryCode}
        onChange={(event) => onCountryCodeChange(event.target.value)}
        disabled={disabled}
        className="w-[76px] shrink-0 border border-r-0 border-border bg-background px-2 text-sm text-dark outline-hidden focus:border-gold disabled:opacity-60 sm:w-[80px]"
      >
        {callingCodes.map(({ country, code }) => (
          <option key={`${country}-${code}`} value={code}>+{code}</option>
        ))}
      </select>
      <input
        id={id}
        name={name}
        type="tel"
        value={value}
        onChange={handleNumberChange}
        inputMode="numeric"
        pattern="[0-9]*"
        maxLength={Math.max(4, 15 - countryCode.length)}
        autoComplete={autoComplete}
        required={required}
        disabled={disabled}
        aria-invalid={ariaInvalid}
        aria-describedby={ariaDescribedBy}
        placeholder={placeholder}
        className="min-w-0 w-full border border-border bg-background px-4 py-3.5 text-sm text-dark outline-hidden transition-colors duration-200 placeholder-gray-400 focus:border-gold disabled:opacity-60"
      />
    </div>
  );
}

