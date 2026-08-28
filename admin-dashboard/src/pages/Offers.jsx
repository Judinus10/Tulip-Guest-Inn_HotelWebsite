import { useEffect, useMemo, useState } from 'react'
import {
  BadgePercent,
  CalendarDays,
  Eye,
  Gift,
  ImageIcon,
  MoreHorizontal,
  Pencil,
  Plus,
  Search,
  Tag,
  Trash2,
  Upload,
  X,
} from 'lucide-react'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Dropdown } from '@/components/ui/dropdown'
import { Card, CardContent } from '@/components/ui/card'
import { Input, Label } from '@/components/ui/input'
import { discountTypes, offerStatuses, packageCategories } from '@/data/offerData'
import { createOffer, deleteOfferById, fetchOffers, updateOffer } from '@/services/offersApi'
import { useToastState } from '@/context/ToastContext'

const emptyForm = {
  title: '',
  description: '',
  details: [],
  package_category: 'Family Stay Offer',
  discount_type: 'percentage',
  discount_value: '',
  start_date: '',
  end_date: '',
  status: 'active',
  image_path: '',
  image_preview: '',
  image_file_name: '',
}

const statusVariant = {
  active: 'success',
  inactive: 'secondary',
  upcoming: 'info',
  expired: 'destructive',
}

function titleCase(value) {
  return String(value || '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function formatDate(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
  }).format(new Date(value))
}

function formatDateTime(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function formatDiscount(offer) {
  if (offer.discount_type === 'percentage') return `${Number(offer.discount_value || 0)}%`
  return `$${Number(offer.discount_value || 0).toLocaleString()}`
}

function Toast({ message, onClose }) {
  if (!message) return null

  return (
    <div className="fixed right-5 top-5 z-50 flex items-center gap-3 rounded-2xl border border-blue-100 bg-white px-4 py-3 text-sm font-medium text-slate-800 shadow-2xl">
      <span className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-700">
        <Gift className="h-4 w-4" />
      </span>
      <span>{message}</span>
      <button type="button" onClick={onClose} className="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
        <X className="h-4 w-4" />
      </button>
    </div>
  )
}

function StatCard({ title, value, description, icon: Icon }) {
  return (
    <Card>
      <CardContent className="p-5">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-sm font-medium text-text-secondary">{title}</p>
            <p className="mt-2 text-2xl font-bold tracking-tight text-text-primary">{value}</p>
            {description ? <p className="mt-1 text-xs text-text-secondary">{description}</p> : null}
          </div>
          <div className="rounded-xl bg-blue-50 p-3 text-blue-700">
            <Icon className="h-5 w-5" />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

function Modal({ title, description, children, onClose, size = 'max-w-2xl' }) {
  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm"
      onClick={onClose}
    >
      <div
        className={`max-h-[90vh] w-full overflow-hidden rounded-3xl bg-white shadow-2xl ${size}`}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-4 border-b border-border px-6 py-5">
          <div>
            <h2 className="text-lg font-bold text-text-primary">{title}</h2>
            {description ? <p className="mt-1 text-sm text-text-secondary">{description}</p> : null}
          </div>
          <button type="button" onClick={onClose} className="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="max-h-[calc(90vh-86px)] overflow-y-auto p-6">{children}</div>
      </div>
    </div>
  )
}

function OfferFormModal({ mode, offer, onClose, onSubmit }) {
  const [form, setForm] = useState(() => (offer ? { ...offer } : { ...emptyForm }))
  const [errors, setErrors] = useState({})

  const updateField = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }))
    setErrors((current) => ({ ...current, [field]: '' }))
  }

  const detailsText = Array.isArray(form.details) ? form.details.join('\n') : String(form.details || '')

  const updateDetails = (value) => {
    setForm((current) => ({
      ...current,
      details: value
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean),
    }))
  }

  const handleImageChange = (event) => {
    const file = event.target.files?.[0]
    if (!file) return

    if (!file.type.startsWith('image/')) {
      setErrors((current) => ({ ...current, image: 'Please select a valid image file.' }))
      event.target.value = ''
      return
    }

    const preview = URL.createObjectURL(file)
    setForm((current) => ({
      ...current,
      image_file: file,
      image_file_name: file.name,
      image_path: preview,
      image_preview: preview,
    }))
    setErrors((current) => ({ ...current, image: '' }))
  }

  const removeImage = () => {
    setForm((current) => ({
      ...current,
      image_file: null,
      image_file_name: '',
      image_path: '',
      image_preview: '',
    }))
  }

  const validate = () => {
    const nextErrors = {}

    if (!form.title.trim()) nextErrors.title = 'Offer title is required.'
    if (!form.description.trim()) nextErrors.description = 'Description is required.'
    if (!form.package_category) nextErrors.package_category = 'Package category is required.'
    if (!form.discount_type) nextErrors.discount_type = 'Discount type is required.'
    if (form.discount_value === '' || Number(form.discount_value) <= 0) {
      nextErrors.discount_value = 'Discount value must be greater than 0.'
    }
    if (form.discount_type === 'percentage' && Number(form.discount_value) > 100) {
      nextErrors.discount_value = 'Percentage discount cannot be more than 100.'
    }
    if (!form.start_date) nextErrors.start_date = 'Start date is required.'
    if (!form.end_date) nextErrors.end_date = 'End date is required.'
    if (form.start_date && form.end_date && new Date(form.end_date) < new Date(form.start_date)) {
      nextErrors.end_date = 'End date cannot be before start date.'
    }
    if (!form.status) nextErrors.status = 'Status is required.'

    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  const handleSubmit = (event) => {
    event.preventDefault()
    if (!validate()) return

    const { image_file, ...safePayload } = form

    onSubmit({
      ...safePayload,
      discount_value: Number(safePayload.discount_value),
    })
  }

  return (
    <Modal
      title={mode === 'edit' ? 'Edit offer' : 'Add new offer'}
      description="Manage offer information stored in the database."
      onClose={onClose}
      size="max-w-3xl"
    >
      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="grid gap-5 md:grid-cols-2">
          <div className="space-y-2 md:col-span-2">
            <Label htmlFor="title">Offer title</Label>
            <Input id="title" value={form.title} onChange={(event) => updateField('title', event.target.value)} placeholder="Summer Stay Saver" />
            {errors.title ? <p className="text-xs font-medium text-red-600">{errors.title}</p> : null}
          </div>

          <div className="space-y-2 md:col-span-2">
            <Label htmlFor="description">Description</Label>
            <textarea
              id="description"
              value={form.description}
              onChange={(event) => updateField('description', event.target.value)}
              rows={4}
              placeholder="Write a short offer description..."
              className="min-h-28 w-full rounded-xl border border-border bg-white px-4 py-3 text-sm text-text-primary shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            />
            {errors.description ? <p className="text-xs font-medium text-red-600">{errors.description}</p> : null}
          </div>

          <div className="space-y-2 md:col-span-2">
            <Label htmlFor="details">Offer details / bullet points</Label>
            <textarea
              id="details"
              value={detailsText}
              onChange={(event) => updateDetails(event.target.value)}
              rows={4}
              placeholder={"20% room rate discount\nComplimentary extra bed\nEarly check-in from 10 AM\nPool access included"}
              className="min-h-28 w-full rounded-xl border border-border bg-white px-4 py-3 text-sm text-text-primary shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            />
            <p className="text-xs text-text-secondary">Enter one public offer point per line.</p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="package_category">Package category</Label>
            <select
              id="package_category"
              value={form.package_category}
              onChange={(event) => updateField('package_category', event.target.value)}
              className="h-11 w-full rounded-xl border border-border bg-white px-4 text-sm text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >
              {packageCategories.map((category) => (
                <option key={category} value={category}>
                  {category}
                </option>
              ))}
            </select>
            {errors.package_category ? <p className="text-xs font-medium text-red-600">{errors.package_category}</p> : null}
          </div>

          <div className="space-y-2">
            <Label htmlFor="discount_type">Discount type</Label>
            <select
              id="discount_type"
              value={form.discount_type}
              onChange={(event) => updateField('discount_type', event.target.value)}
              className="h-11 w-full rounded-xl border border-border bg-white px-4 text-sm text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >
              {discountTypes.map((type) => (
                <option key={type} value={type}>
                  {titleCase(type)}
                </option>
              ))}
            </select>
            {errors.discount_type ? <p className="text-xs font-medium text-red-600">{errors.discount_type}</p> : null}
          </div>

          <div className="space-y-2">
            <Label htmlFor="discount_value">Discount value</Label>
            <Input
              id="discount_value"
              type="number"
              min="0"
              step="0.01"
              value={form.discount_value}
              onChange={(event) => updateField('discount_value', event.target.value)}
              placeholder={form.discount_type === 'percentage' ? '15' : '50'}
            />
            {errors.discount_value ? <p className="text-xs font-medium text-red-600">{errors.discount_value}</p> : null}
          </div>

          <div className="space-y-2">
            <Label htmlFor="start_date">Start date</Label>
            <Input id="start_date" type="date" value={form.start_date} onChange={(event) => updateField('start_date', event.target.value)} />
            {errors.start_date ? <p className="text-xs font-medium text-red-600">{errors.start_date}</p> : null}
          </div>

          <div className="space-y-2">
            <Label htmlFor="end_date">End date</Label>
            <Input id="end_date" type="date" value={form.end_date} onChange={(event) => updateField('end_date', event.target.value)} />
            {errors.end_date ? <p className="text-xs font-medium text-red-600">{errors.end_date}</p> : null}
          </div>

          <div className="space-y-2">
            <Label htmlFor="status">Status</Label>
            <select
              id="status"
              value={form.status}
              onChange={(event) => updateField('status', event.target.value)}
              className="h-11 w-full rounded-xl border border-border bg-white px-4 text-sm text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >
              {offerStatuses.map((status) => (
                <option key={status} value={status}>
                  {titleCase(status)}
                </option>
              ))}
            </select>
            {errors.status ? <p className="text-xs font-medium text-red-600">{errors.status}</p> : null}
          </div>

          <div className="space-y-3 md:col-span-2">
            <div className="flex items-center justify-between gap-3">
              <Label htmlFor="package_image">Package image optional</Label>
              <p className="text-xs text-text-secondary">Future path: bend/uploads/offers/</p>
            </div>
            <label
              htmlFor="package_image"
              className="flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-blue-200 bg-blue-50/40 px-4 py-6 text-center transition hover:border-blue-400 hover:bg-blue-50"
            >
              <Upload className="h-7 w-7 text-blue-700" />
              <span className="mt-2 text-sm font-semibold text-text-primary">Upload package image</span>
              <span className="mt-1 text-xs text-text-secondary">PNG, JPG, JPEG or WebP. No URL input.</span>
              <input id="package_image" type="file" accept="image/*" onChange={handleImageChange} className="sr-only" />
            </label>
            {errors.image ? <p className="text-xs font-medium text-red-600">{errors.image}</p> : null}

            {form.image_preview || form.image_path ? (
              <div className="rounded-2xl border border-border bg-white p-3">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                  <img
                    src={form.image_preview || form.image_path}
                    alt={form.title || 'Package preview'}
                    className="h-28 w-full rounded-xl object-cover sm:w-40"
                  />
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-text-primary">Selected image</p>
                    <p className="mt-1 truncate text-xs text-text-secondary">{form.image_file_name || form.image_path || 'Existing package image'}</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      <label htmlFor="package_image_replace" className="inline-flex h-9 cursor-pointer items-center justify-center rounded-lg border border-border bg-white px-3 text-sm font-medium text-text-primary shadow-sm transition hover:bg-slate-50">
                        Change image
                        <input id="package_image_replace" type="file" accept="image/*" onChange={handleImageChange} className="sr-only" />
                      </label>
                      <Button type="button" variant="outline" size="sm" onClick={removeImage}>
                        Remove image
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            ) : null}
          </div>
        </div>

        <div className="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit">{mode === 'edit' ? 'Save Changes' : 'Add Offer'}</Button>
        </div>
      </form>
    </Modal>
  )
}

function OfferDetailsModal({ offer, onClose }) {
  return (
    <Modal title="Offer details" description="Full promotion information." onClose={onClose} size="max-w-2xl">
      <div className="space-y-5">
        {offer.image_preview || offer.image_path ? (
          <div className="overflow-hidden rounded-2xl border border-border bg-slate-50">
            <img src={offer.image_preview || offer.image_path} alt={offer.title} className="h-56 w-full object-cover" />
          </div>
        ) : null}

        <div className="rounded-2xl border border-border bg-slate-50 p-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-text-secondary">Offer</p>
              <h3 className="mt-2 text-xl font-bold text-text-primary">{offer.title}</h3>
              <p className="mt-2 text-sm leading-6 text-text-secondary">{offer.description}</p>
            </div>
            <Badge variant={statusVariant[offer.status]}>{titleCase(offer.status)}</Badge>
          </div>
        </div>

        {Array.isArray(offer.details) && offer.details.length > 0 ? (
          <div className="rounded-2xl border border-border p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Offer Details</p>
            <ul className="mt-3 list-disc space-y-2 pl-5 text-sm text-text-primary">
              {offer.details.map((detail, index) => (
                <li key={`${offer.id || offer.title}-detail-${index}`}>{detail}</li>
              ))}
            </ul>
          </div>
        ) : null}

        <div className="grid gap-4 md:grid-cols-2">
          <div className="rounded-2xl border border-border p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Package Category</p>
            <p className="mt-2 text-sm font-semibold text-text-primary">{offer.package_category || 'General Package'}</p>
          </div>
          <div className="rounded-2xl border border-border p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Discount Type</p>
            <p className="mt-2 text-sm font-semibold text-text-primary">{titleCase(offer.discount_type)}</p>
          </div>
          <div className="rounded-2xl border border-border p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Discount Value</p>
            <p className="mt-2 text-sm font-semibold text-text-primary">{formatDiscount(offer)}</p>
          </div>
          <div className="rounded-2xl border border-border p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Start Date</p>
            <p className="mt-2 text-sm font-semibold text-text-primary">{formatDate(offer.start_date)}</p>
          </div>
          <div className="rounded-2xl border border-border p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">End Date</p>
            <p className="mt-2 text-sm font-semibold text-text-primary">{formatDate(offer.end_date)}</p>
          </div>
          <div className="rounded-2xl border border-border p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Created</p>
            <p className="mt-2 text-sm font-semibold text-text-primary">{formatDateTime(offer.created_at)}</p>
          </div>
          <div className="rounded-2xl border border-border p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Updated</p>
            <p className="mt-2 text-sm font-semibold text-text-primary">{formatDateTime(offer.updated_at)}</p>
          </div>
        </div>
      </div>
    </Modal>
  )
}

function DeleteOfferModal({ offer, onClose, onConfirm }) {
  return (
    <Modal title="Delete Package" description="Confirm package removal." onClose={onClose} size="max-w-lg">
      <div className="space-y-5">
        <div className="rounded-2xl border border-red-100 bg-red-50 p-4 text-sm text-red-800">
          <p className="font-semibold">Are you sure you want to delete this package?</p>
          <p className="mt-1">This action cannot be undone.</p>
        </div>
        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={() => onConfirm(offer.id)}>
            Delete
          </Button>
        </div>
      </div>
    </Modal>
  )
}


function PaginationControls({ currentPage, totalPages, totalItems, pageSize, onPageChange }) {
  const startItem = totalItems === 0 ? 0 : (currentPage - 1) * pageSize + 1
  const endItem = Math.min(currentPage * pageSize, totalItems)

  return (
    <div className="sticky bottom-0 z-10 flex flex-row items-center justify-between gap-3 border-t border-border bg-white/95 px-4 py-4 backdrop-blur">
      <p className="shrink-0 text-sm font-medium text-text-secondary">Showing {startItem}-{endItem} of {totalItems}</p>
      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={currentPage === 1}
          onClick={() => onPageChange(Math.max(1, currentPage - 1))}
        >
          Previous
        </Button>
        <span className="rounded-xl border border-border bg-white px-3 py-2 text-sm font-bold text-text-primary shadow-sm">
          {currentPage} / {totalPages}
        </span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={currentPage === totalPages}
          onClick={() => onPageChange(Math.min(totalPages, currentPage + 1))}
        >
          Next
        </Button>
      </div>
    </div>
  )
}

function OfferActionsDropdown({ offer, onView, onEdit, onDelete }) {
  return (
    <Dropdown className="inline-flex justify-end" contentClassName="w-48 py-1 text-left" trigger={<Button type="button" variant="outline" size="sm" className="gap-2"><MoreHorizontal className="h-4 w-4" />Actions</Button>}>
      {(close) => (
        <>
        <button
          type="button"
          onClick={() => { onView(offer); close() }}
          className="flex w-full items-center gap-2 px-3 py-2 text-sm font-medium text-text-primary transition hover:bg-blue-50 hover:text-blue-700"
        >
          <Eye className="h-4 w-4" />
          View Package
        </button>
        <button
          type="button"
          onClick={() => { onEdit(offer); close() }}
          className="flex w-full items-center gap-2 px-3 py-2 text-sm font-medium text-text-primary transition hover:bg-blue-50 hover:text-blue-700"
        >
          <Pencil className="h-4 w-4" />
          Edit Package
        </button>
        <button
          type="button"
          onClick={() => { onDelete(offer); close() }}
          className="flex w-full items-center gap-2 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
        >
          <Trash2 className="h-4 w-4" />
          Delete Package
        </button>
        </>
      )}
    </Dropdown>
  )
}

export default function Offers() {
  const [offers, setOffers] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [searchTerm, setSearchTerm] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [discountFilter, setDiscountFilter] = useState('all')
  const [formMode, setFormMode] = useState(null)
  const [editingOffer, setEditingOffer] = useState(null)
  const [viewOffer, setViewOffer] = useState(null)
  const [deleteOffer, setDeleteOffer] = useState(null)
  const [toast, setToast] = useToastState('')
  const [currentPage, setCurrentPage] = useState(1)

  const showToast = (message) => {
    setToast(message)
    window.setTimeout(() => setToast(''), 2500)
  }

  const loadOffers = async () => {
    setIsLoading(true)
    setLoadError('')

    try {
      const data = await fetchOffers()
      setOffers(data)
    } catch (error) {
      setLoadError(error.message || 'Unable to load offers.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadOffers()
  }, [])

  const summary = useMemo(() => {
    return {
      total: offers.length,
      active: offers.filter((offer) => offer.status === 'active').length,
      expired: offers.filter((offer) => offer.status === 'expired').length,
      upcoming: offers.filter((offer) => offer.status === 'upcoming').length,
    }
  }, [offers])

  const filteredOffers = useMemo(() => {
    const query = searchTerm.trim().toLowerCase()

    return offers.filter((offer) => {
      const matchesSearch = !query || offer.title.toLowerCase().includes(query) || offer.description.toLowerCase().includes(query)
      const matchesStatus = statusFilter === 'all' || offer.status === statusFilter
      const matchesDiscount = discountFilter === 'all' || offer.discount_type === discountFilter

      return matchesSearch && matchesStatus && matchesDiscount
    })
  }, [offers, searchTerm, statusFilter, discountFilter])

  const pageSize = 6
  const totalPages = Math.max(1, Math.ceil(filteredOffers.length / pageSize))
  const paginatedOffers = filteredOffers.slice((currentPage - 1) * pageSize, currentPage * pageSize)

  useEffect(() => {
    setCurrentPage(1)
  }, [searchTerm, statusFilter, discountFilter])

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages)
    }
  }, [currentPage, totalPages])

  const resetFilters = () => {
    setSearchTerm('')
    setStatusFilter('all')
    setDiscountFilter('all')
  }

  const openAddModal = () => {
    setEditingOffer(null)
    setFormMode('add')
  }

  const openEditModal = (offer) => {
    setEditingOffer(offer)
    setFormMode('edit')
  }

  const closeFormModal = () => {
    setFormMode(null)
    setEditingOffer(null)
  }

  const handleSubmitOffer = async (payload) => {
    try {
      if (formMode === 'edit' && editingOffer) {
        const savedOffer = await updateOffer({ ...payload, id: editingOffer.id })
        setOffers((current) => current.map((offer) => (offer.id === editingOffer.id ? savedOffer : offer)))
        showToast('Offer updated successfully.')
      } else {
        const savedOffer = await createOffer(payload)
        setOffers((current) => [savedOffer, ...current])
        showToast('Offer added successfully.')
      }

      closeFormModal()
    } catch (error) {
      showToast(error.message || 'Unable to save offer.')
    }
  }

  const handleDeleteOffer = async (id) => {
    try {
      await deleteOfferById(id)
      setOffers((current) => current.filter((offer) => offer.id !== id))
      setDeleteOffer(null)
      showToast('Offer deleted successfully.')
    } catch (error) {
      showToast(error.message || 'Unable to delete offer.')
    }
  }

  return (
    <div className="space-y-6">
      <Toast message={toast} onClose={() => setToast('')} />

      <PageHeader
        title="Offers Management"
        description="Create, manage, and monitor hotel promotions and discounts."
      >
        <Button onClick={openAddModal} className="w-full gap-2 sm:w-auto">
          <Plus className="h-4 w-4" />
          Add Offer
        </Button>
      </PageHeader>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard title="Total Offers" value={summary.total} description="All promotional records" icon={Gift} />
        <StatCard title="Active Offers" value={summary.active} description="Visible to guests" icon={BadgePercent} />
        <StatCard title="Expired Offers" value={summary.expired} description="Past promotion period" icon={CalendarDays} />
        <StatCard title="Upcoming Offers" value={summary.upcoming} description="Scheduled campaigns" icon={Tag} />
      </div>

      <Card>
        <CardContent className="p-5">
          <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:grid-cols-[minmax(0,1fr)_180px_180px_auto]">
            <div className="relative md:col-span-3 lg:col-span-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input
                value={searchTerm}
                onChange={(event) => setSearchTerm(event.target.value)}
                placeholder="Search offer title or description..."
                className="pl-10"
              />
            </div>

            <select
              value={statusFilter}
              onChange={(event) => setStatusFilter(event.target.value)}
              className="h-11 min-w-0 rounded-xl border border-border bg-white px-4 text-sm text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >
              <option value="all">All statuses</option>
              {offerStatuses.map((status) => (
                <option key={status} value={status}>
                  {titleCase(status)}
                </option>
              ))}
            </select>

            <select
              value={discountFilter}
              onChange={(event) => setDiscountFilter(event.target.value)}
              className="h-11 min-w-0 rounded-xl border border-border bg-white px-4 text-sm text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >
              <option value="all">All discount types</option>
              {discountTypes.map((type) => (
                <option key={type} value={type}>
                  {titleCase(type)}
                </option>
              ))}
            </select>

            <Button variant="outline" onClick={resetFilters} className="min-w-[88px]">
              Reset
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-0">
          {loadError ? (
            <div className="border-b border-border px-5 py-4 text-sm font-medium text-red-600">{loadError}</div>
          ) : null}

          {isLoading ? (
            <div className="px-5 py-10 text-center text-sm font-medium text-text-secondary">Loading offers...</div>
          ) : null}

          {!isLoading ? (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-border">
              <thead className="sticky top-0 z-10 bg-slate-50">
                <tr>
                  <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-text-secondary">Title</th>
                  <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-text-secondary">Discount Type</th>
                  <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-text-secondary">Discount Value</th>
                  <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-text-secondary">Start Date</th>
                  <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-text-secondary">End Date</th>
                  <th className="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-text-secondary">Status</th>
                  <th className="px-5 py-4 text-right text-xs font-bold uppercase tracking-wide text-text-secondary">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border bg-white">
                {paginatedOffers.map((offer) => (
                  <tr key={offer.id} className="transition hover:bg-slate-50/80">
                    <td className="px-5 py-4">
                      <div className="min-w-64">
                        <p className="font-semibold text-text-primary">{offer.title}</p>
                        <p className="mt-1 text-xs font-semibold text-blue-700">{offer.package_category || 'General Package'}</p>
                        <p className="mt-1 line-clamp-1 text-sm text-text-secondary">{offer.description}</p>
                      </div>
                    </td>
                    <td className="px-5 py-4 text-sm font-medium text-text-primary">{titleCase(offer.discount_type)}</td>
                    <td className="px-5 py-4 text-sm font-bold text-blue-700">{formatDiscount(offer)}</td>
                    <td className="px-5 py-4 text-sm text-text-secondary">{formatDate(offer.start_date)}</td>
                    <td className="px-5 py-4 text-sm text-text-secondary">{formatDate(offer.end_date)}</td>
                    <td className="px-5 py-4">
                      <Badge variant={statusVariant[offer.status]}>{titleCase(offer.status)}</Badge>
                    </td>
                    <td className="px-5 py-4 text-right">
                      <OfferActionsDropdown
                        offer={offer}
                        onView={setViewOffer}
                        onEdit={openEditModal}
                        onDelete={setDeleteOffer}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          ) : null}

          {!isLoading && filteredOffers.length > 0 ? (
            <PaginationControls
              currentPage={currentPage}
              totalPages={totalPages}
              totalItems={filteredOffers.length}
              pageSize={pageSize}
              onPageChange={setCurrentPage}
            />
          ) : null}

          {!isLoading && filteredOffers.length === 0 ? (
            <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
              <div className="rounded-full bg-blue-50 p-4 text-blue-700">
                <Gift className="h-8 w-8" />
              </div>
              <h3 className="mt-4 text-lg font-bold text-text-primary">No offers found</h3>
              <p className="mt-2 max-w-md text-sm text-text-secondary">Change the filters or add a new promotion to keep your hotel offers organized.</p>
              <Button className="mt-5" onClick={openAddModal}>
                <Plus className="h-4 w-4" />
                Add Offer
              </Button>
            </div>
          ) : null}
        </CardContent>
      </Card>

      {formMode ? (
        <OfferFormModal mode={formMode} offer={editingOffer} onClose={closeFormModal} onSubmit={handleSubmitOffer} />
      ) : null}

      {viewOffer ? <OfferDetailsModal offer={viewOffer} onClose={() => setViewOffer(null)} /> : null}

      {deleteOffer ? (
        <DeleteOfferModal offer={deleteOffer} onClose={() => setDeleteOffer(null)} onConfirm={handleDeleteOffer} />
      ) : null}
    </div>
  )
}
