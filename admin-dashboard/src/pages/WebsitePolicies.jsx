import { useCallback, useState } from 'react'
import { FileText, RotateCcw, ShieldCheck } from 'lucide-react'
import { SectionCard } from '@/components/ui/page-header'
import LegalContent from '@/pages/LegalContent'

const policies = [
  { key: 'terms', title: 'Terms & Conditions', description: 'Website use and booking terms', Icon: FileText },
  { key: 'privacy', title: 'Privacy Policy', description: 'Guest information and data privacy', Icon: ShieldCheck },
  { key: 'refund', title: 'Refund Policy', description: 'Cancellations and refund conditions', Icon: RotateCcw },
]

export default function WebsitePolicies() {
  const [selected, setSelected] = useState('terms')
  const [opened, setOpened] = useState(['terms'])
  const [dirtyPages, setDirtyPages] = useState({})
  const updateDirty = useCallback((page, dirty) => {
    setDirtyPages(current => current[page] === dirty ? current : { ...current, [page]: dirty })
  }, [])

  function selectPolicy(page) {
    setSelected(page)
    setOpened(current => current.includes(page) ? current : [...current, page])
  }

  return <div className="grid items-start gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
    <aside className="min-w-0" aria-label="Policy selection">
      <SectionCard>
        <div className="mb-5">
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Policies</p>
          <h2 className="mt-1 text-lg font-semibold text-slate-900">Choose a policy</h2>
        </div>
        <div className="space-y-3" role="group" aria-label="Policy page">
          {policies.map(({ key, title, description, Icon }) => <button key={key} id={`policy-choice-${key}`} type="button"
            aria-pressed={selected === key} onClick={() => selectPolicy(key)} aria-controls={`policy-panel-${key}`}
            className={`flex w-full items-start gap-3 rounded-xl border p-4 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 ${selected === key ? 'border-violet-500 bg-violet-50' : 'border-slate-200 bg-white hover:border-slate-300'}`}>
            <Icon className="mt-0.5 h-5 w-5 shrink-0 text-slate-600" aria-hidden="true" />
            <span className="min-w-0 flex-1">
              <span className="flex flex-wrap items-center justify-between gap-2">
                <strong className="text-sm text-slate-900">{title}</strong>
                {dirtyPages[key] && <span className="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">Unsaved</span>}
              </span>
              <span className="mt-1 block text-xs text-slate-500">{description}</span>
            </span>
          </button>)}
        </div>
      </SectionCard>
    </aside>
    <div className="min-w-0">
      {policies.filter(({ key }) => opened.includes(key)).map(({ key }) => <div key={key} id={`policy-panel-${key}`}
        role="region" aria-labelledby={`policy-choice-${key}`} hidden={selected !== key}>
        <LegalContent page={key} onDirtyChange={updateDirty} />
      </div>)}
    </div>
  </div>
}
