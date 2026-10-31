import LegalDocument from '../components/ui/LegalDocument';
import { useLegalContent } from '../hooks/useLegalContent';
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
          {updated && <p className="text-sm text-muted mb-10">Last updated: {new Date(updated.replace(' ', 'T')).toLocaleDateString()}</p>}

          <div className="bg-surface border border-border p-7 md:p-10 space-y-9">
            {content?.html ? <LegalDocument html={content.html} /> : content ? <>
              <p className="text-sm text-muted leading-relaxed whitespace-pre-wrap">{content.intro}</p>
              {(content.sections ?? []).map((section,index)=><section key={index}><h2 className="font-serif text-2xl font-light text-dark mb-3">{section.title}</h2><p className="text-sm text-muted leading-relaxed whitespace-pre-wrap">{section.body}</p></section>)}
            </> : <>
            <p className="text-sm text-muted leading-relaxed">Refund policy will be published shortly. Please contact us with any refund enquiries.</p>
            </>}
          </div>
        </div>
      </section>
    </main>
  );
}
