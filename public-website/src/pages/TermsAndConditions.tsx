import { Link } from 'react-router-dom';
import PageHero from '../components/ui/PageHero';
import bannerBooking from '../assets/images/banners/banner-booking.jpg';

const sections = [
  {
    title: 'Bookings and Confirmation',
    text: 'Submitting a booking does not guarantee accommodation until the property confirms it or an online payment is successfully verified, as applicable. Availability and room allocation are checked again when the booking is processed. You must provide accurate guest, contact and stay information.',
  },
  {
    title: 'Rates and Payments',
    text: 'Rates are shown in the currency displayed during booking and are based on the selected dates, rooms and guest allocation. Pay-on-arrival bookings remain payment pending until payment is collected at the property. Online payments are processed through PayHere and are subject to successful gateway verification.',
  },
  {
    title: 'Changes, Cancellations and Refunds',
    text: 'Contact the property as early as possible if you need to change or cancel a booking. Any cancellation charge, refund or date-change approval depends on the conditions communicated with your booking and the property’s confirmation. Payment-provider processing times may apply to approved refunds.',
  },
  {
    title: 'Arrival and Departure',
    text: 'Standard check-in is from 1:00 PM and check-out is by 12:00 PM unless the property agrees otherwise. Early arrival or late departure is subject to availability and may involve an additional charge.',
  },
  {
    title: 'Guest Responsibilities',
    text: 'Guests must respect the property, staff, other guests and applicable laws. The lead guest is responsible for the conduct of the booking party and for loss or damage caused beyond normal use. Illegal, dangerous or seriously disruptive behaviour may result in the stay being ended without refund.',
  },
  {
    title: 'Website and Availability',
    text: 'We try to keep room descriptions, prices and availability accurate. Temporary errors, maintenance or third-party service interruptions may occur. We may correct obvious errors and will contact you if a material booking detail is affected.',
  },
  {
    title: 'Liability',
    text: 'To the extent permitted by law, Tulip Guest Inn is not responsible for indirect losses, third-party service failures or events outside its reasonable control. Nothing in these terms removes rights or liabilities that cannot legally be excluded.',
  },
  {
    title: 'Governing Law and Updates',
    text: 'These terms are governed by the laws of Sri Lanka. We may update them when the booking service or legal requirements change; the version published when you make a booking will apply to that booking unless the law requires otherwise.',
  },
];

export default function TermsAndConditions() {
  return (
    <main className="bg-background">
      <PageHero
        title="Terms & Conditions"
        subtitle="The booking, payment and stay conditions that apply when using Tulip Guest Inn services."
        image={bannerBooking}
        breadcrumb="Legal"
      />
      <section className="section-padding">
        <div className="container-custom max-w-4xl">
          <p className="text-sm text-gray-500 mb-10">Last updated: 20 August 2026</p>

          <div className="bg-white border border-border p-7 md:p-10 space-y-9">
            <p className="text-sm text-gray-600 leading-relaxed">
              These terms apply when you use the Tulip Guest Inn website, send an enquiry or make a room booking.
            </p>
            {sections.map((section) => (
              <section key={section.title}>
                <h2 className="font-serif text-2xl font-light text-dark mb-3">{section.title}</h2>
                <p className="text-sm text-gray-600 leading-relaxed">{section.text}</p>
              </section>
            ))}
            <section>
              <h2 className="font-serif text-2xl font-light text-dark mb-3">Contact</h2>
              <p className="text-sm text-gray-600 leading-relaxed">
                Questions about a booking or these terms can be sent to{' '}
                <a className="text-gold hover:underline" href="mailto:info@tulipguestinn.com">info@tulipguestinn.com</a>{' '}
                or through our <Link className="text-gold hover:underline" to="/contact">contact page</Link>.
              </p>
            </section>
          </div>
        </div>
      </section>
    </main>
  );
}
