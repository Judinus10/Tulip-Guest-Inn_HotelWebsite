import { Link } from 'react-router-dom';

const sections = [
  {
    title: 'Information We Collect',
    paragraphs: [
      'When you make a booking or contact us, we may collect your name, email address, phone number, nationality, stay dates, guest details, room preferences and any message or special request you provide.',
      'We may also collect basic technical information such as browser type, device information, IP address and website activity for security, troubleshooting and service improvement.',
    ],
  },
  {
    title: 'Payments',
    paragraphs: [
      'Online payments are processed by PayHere. Tulip Guest Inn does not receive or store your full card number, card security code or online banking credentials. PayHere processes payment information under its own privacy and security terms.',
      'For pay-on-arrival bookings, we record the selected payment method and payment status so the property can manage the reservation.',
    ],
  },
  {
    title: 'How We Use Your Information',
    paragraphs: [
      'We use your information to check availability, create and manage bookings, process or record payments, send booking communications, respond to enquiries, prevent misuse and comply with legal or accounting requirements.',
      'We do not sell your personal information.',
    ],
  },
  {
    title: 'Sharing and Service Providers',
    paragraphs: [
      'Information may be shared only when necessary with payment, email, hosting and other technical service providers that support the booking service, or when disclosure is required by law. These providers receive only the information needed to perform their service.',
    ],
  },
  {
    title: 'Retention and Security',
    paragraphs: [
      'We keep booking and communication records only for as long as reasonably needed for reservations, customer support, accounting, dispute handling and legal obligations. We use reasonable administrative and technical safeguards, but no internet transmission or storage method can be guaranteed to be completely secure.',
    ],
  },
  {
    title: 'Your Choices and Rights',
    paragraphs: [
      'You may ask us to access, correct or delete personal information we hold about you, subject to records we must retain by law or for legitimate business purposes. You may also ask questions about how your information is used.',
    ],
  },
];

export default function PrivacyPolicy() {
  return (
    <main className="bg-background pt-24 md:pt-28">
      <section className="section-padding">
        <div className="container-custom max-w-4xl">
          <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-4">Legal</p>
          <h1 className="font-serif text-4xl md:text-5xl font-light text-dark mb-4">Privacy Policy</h1>
          <div className="w-10 h-[1px] bg-gold mb-5" />
          <p className="text-sm text-gray-500 mb-10">Last updated: 20 August 2026</p>

          <div className="bg-white border border-border p-7 md:p-10 space-y-9">
            <p className="text-sm text-gray-600 leading-relaxed">
              This policy explains how Tulip Guest Inn collects and uses information when you use this website, submit an enquiry or make a booking.
            </p>
            {sections.map((section) => (
              <section key={section.title}>
                <h2 className="font-serif text-2xl font-light text-dark mb-3">{section.title}</h2>
                <div className="space-y-3">
                  {section.paragraphs.map((paragraph) => (
                    <p key={paragraph} className="text-sm text-gray-600 leading-relaxed">{paragraph}</p>
                  ))}
                </div>
              </section>
            ))}
            <section>
              <h2 className="font-serif text-2xl font-light text-dark mb-3">Contact Us</h2>
              <p className="text-sm text-gray-600 leading-relaxed">
                For privacy questions or requests, email{' '}
                <a className="text-gold hover:underline" href="mailto:info@tulipguestinn.com">info@tulipguestinn.com</a>{' '}
                or use our <Link className="text-gold hover:underline" to="/contact">contact page</Link>.
              </p>
            </section>
          </div>
        </div>
      </section>
    </main>
  );
}
