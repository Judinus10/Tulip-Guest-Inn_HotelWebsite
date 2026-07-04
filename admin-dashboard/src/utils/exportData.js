function getExportDate() {
  return new Date().toISOString().slice(0, 10)
}

function cleanFileName(value) {
  return String(value || 'export')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function escapeCsv(value) {
  const text = value === null || value === undefined ? '' : String(value)
  return `"${text.replace(/"/g, '""')}"`
}

function escapeHtml(value) {
  return String(value === null || value === undefined ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

function downloadBlob(content, fileName, type) {
  const blob = new Blob([content], { type })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

export function exportCsv({ fileName, rows }) {
  if (!rows?.length) return false

  const headers = Object.keys(rows[0])
  const csv = [
    headers.map(escapeCsv).join(','),
    ...rows.map((row) => headers.map((header) => escapeCsv(row[header])).join(',')),
  ].join('\n')

  downloadBlob(csv, `${cleanFileName(fileName)}-${getExportDate()}.csv`, 'text/csv;charset=utf-8;')
  return true
}

export function exportExcel({ fileName, rows }) {
  if (!rows?.length) return false

  const headers = Object.keys(rows[0])
  const html = `
    <html>
      <head>
        <meta charset="utf-8" />
      </head>
      <body>
        <table border="1">
          <thead>
            <tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr>
          </thead>
          <tbody>
            ${rows
              .map((row) => `<tr>${headers.map((header) => `<td>${escapeHtml(row[header])}</td>`).join('')}</tr>`)
              .join('')}
          </tbody>
        </table>
      </body>
    </html>
  `

  downloadBlob(html, `${cleanFileName(fileName)}-${getExportDate()}.xls`, 'application/vnd.ms-excel;charset=utf-8;')
  return true
}

export function exportPdf({ title, rows }) {
  if (!rows?.length) return false

  const headers = Object.keys(rows[0])
  const html = `
    <!doctype html>
    <html>
      <head>
        <title>${escapeHtml(title)}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 24px; color: #111827; }
          h1 { margin: 0 0 6px; font-size: 22px; }
          p { margin: 0 0 20px; color: #64748b; font-size: 13px; }
          table { width: 100%; border-collapse: collapse; font-size: 11px; }
          th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; vertical-align: top; }
          th { background: #f1f5f9; font-weight: 700; }
          @page { size: landscape; margin: 12mm; }
          @media print { body { padding: 0; } }
        </style>
      </head>
      <body>
        <h1>${escapeHtml(title)}</h1>
        <p>Generated on ${escapeHtml(new Date().toLocaleString())}</p>
        <table>
          <thead>
            <tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr>
          </thead>
          <tbody>
            ${rows
              .map((row) => `<tr>${headers.map((header) => `<td>${escapeHtml(row[header])}</td>`).join('')}</tr>`)
              .join('')}
          </tbody>
        </table>
      </body>
    </html>
  `

  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.style.visibility = 'hidden'
  document.body.appendChild(iframe)

  const iframeWindow = iframe.contentWindow
  const iframeDocument = iframeWindow?.document

  if (!iframeWindow || !iframeDocument) {
    iframe.remove()
    return false
  }

  iframeDocument.open()
  iframeDocument.write(html)
  iframeDocument.close()

  window.setTimeout(() => {
    iframeWindow.focus()
    iframeWindow.print()

    window.setTimeout(() => {
      iframe.remove()
    }, 1000)
  }, 300)

  return true
}
