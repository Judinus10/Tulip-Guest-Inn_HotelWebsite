import { useLegalContent } from '../hooks/useLegalContent';
import { Link } from 'react-router-dom';
import PageHero from '../components/ui/PageHero';
import bannerAbout from '../assets/images/banners/banner-about.jpg';

export default function RefundPolicy() {
  const { content, updated } = useLegalContent('refund');
  return (
    <main className="bg-background">
      <PageHero
        title="Refund Policy"
        subtitle="Information about cancellations and refunds for Tulip Guest Inn bookings."
        image={bannerAbout}
        breadcrumb="Legal"
      />
      <section className="section-padding">
        <div className="container-custom max-w-4xl">
          {updated && <p className="text-sm text-gray-500 mb-10">Last updated: {new Date(updated.replace(' ', 'T')).toLocaleDateString()}</p>}

          <div className="bg-white border border-border p-7 md:p-10 space-y-9">
            {content?.html ? <div className="legal-document text-sm leading-7 text-gray-600 [&_u]:underline [&_strong]:font-semibold [&_h2]:mb-4 [&_h2]:mt-9 [&_h2]:font-serif [&_h2]:text-2xl [&_h2]:font-light [&_h2]:text-dark [&_p]:my-3 [&_p:empty]:min-h-[1.75rem] [&_div]:my-3 [&_div:empty]:min-h-[1.75rem] [&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-6" dangerouslySetInnerHTML={{ __html: content.html }} /> : content ? <>
              <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{content.intro}</p>
              {(content.sections ?? []).map((section,index)=><section key={index}><h2 className="font-serif text-2xl font-light text-dark mb-3">{section.title}</h2><p className="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{section.body}</p></section>)}
            </> : <>
            <p className="text-sm text-gray-600 leading-relaxed">Refund policy will be published shortly. Please contact us with any refund enquiries.</p>
            </>}
            <section>
              <h2 className="font-serif text-2xl font-light text-dark mb-3">Contact Us</h2>
              <p className="text-sm text-gray-600 leading-relaxed">
                For refund enquiries, email{' '}
                <a className="text-gold hover:underline" href="mailto:booking@tulipguestinn.com">booking@tulipguestinn.com</a>{' '}
                or use our <Link className="text-gold hover:underline" to="/contact">contact page</Link>.
              </p>
            </section>
          </div>
        </div>
      </section>
    </main>
  );
}
