import { useCallback, useState } from 'react'
import LegalContent from '@/pages/LegalContent'

const policies = [
  { key: 'terms', title: 'Terms & Conditions' },
  { key: 'privacy', title: 'Privacy Policy' },
  { key: 'refund', title: 'Refund Policy' },
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

  return <div className="space-y-5">
    <div className="flex flex-wrap gap-2" role="group" aria-label="Policy page">
      {policies.map(({ key, title }) => <button key={key} type="button" aria-pressed={selected === key}
        onClick={() => selectPolicy(key)} aria-controls={`policy-panel-${key}`}
        className={`rounded-lg border px-3 py-2 text-xs font-semibold ${selected === key ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-border bg-white text-text-secondary'}`}>
        {title}{dirtyPages[key] && <span className="ml-1" aria-label="Unsaved changes">•</span>}
      </button>)}
    </div>
    {policies.filter(({ key }) => opened.includes(key)).map(({ key }) => <div key={key} id={`policy-panel-${key}`} hidden={selected !== key}>
      <LegalContent page={key} onDirtyChange={updateDirty} />
    </div>)}
  </div>
}
