import './LegalDocument.css';

// The legal-content endpoint sanitizes published HTML on both save and read.
export default function LegalDocument({ html }: { html: string }) {
  return <div className="legal-document text-gray-600" dangerouslySetInnerHTML={{ __html: html }} />;
}
