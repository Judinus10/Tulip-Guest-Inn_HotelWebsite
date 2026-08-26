import { useEffect, useMemo, useRef, useState } from 'react'
import {
  Bath,
  BedDouble,
  BriefcaseBusiness,
  Building2,
  Check,
  ChevronLeft,
  ChevronRight,
  Coffee,
  Eye,
  GlassWater,
  ImagePlus,
  MoreHorizontal,
  Pencil,
  Plus,
  Search,
  Settings2,
  Trash2,
  Waves,
  Wifi,
  Wind,
  X,
} from 'lucide-react'
import { useToastState } from '@/context/ToastContext'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input, Label, Textarea } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { createAmenity, createRoom, deleteAmenity, deleteRoomById, deleteRoomImage, listAmenities, listRooms, updateAmenity, updateRoom } from '@/services/roomsApi'

const emptyForm = {
  room_name: '',
  room_type: 'Ground Floor',
  room_category: 'standard',
  price_per_night: '',
  capacity: '',
  bed_type: 'Double Bed',
  room_size: '',
  currency: 'LKR',
  description: '',
  status: 'Available',
  images: [],
  amenity_ids: [],
  show_unavailable_amenities: false,
}

const roomTypes = ['Ground Floor', 'First Floor', 'Family Room', 'Private Cottage']
const roomCategories = [
  { value: 'standard', label: 'Standard' },
  { value: 'deluxe', label: 'Deluxe' },
  { value: 'family', label: 'Family' },
  { value: 'suite', label: 'Suite' },
]
const bedTypes = ['Single Bed', 'Double Bed', 'Twin Beds', 'King Bed', 'Queen Bed', 'King + Twin Beds']
const roomStatuses = ['Available', 'Unavailable', 'Maintenance']

const statusVariant = {
  Available: 'success',
  Unavailable: 'secondary',
  Maintenance: 'warning',
}

const amenityIcons = {
  Wifi,
  Wind,
  Coffee,
  Waves,
  Building2,
  GlassWater,
  BriefcaseBusiness,
  Bath,
}

function formatCurrency(value) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'LKR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))
}

function formatDate(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value))
}


function resolveImageSrc(image) {
  if (!image) return ''
  if (typeof image === 'string') return image
  return image.image_url || image.url || image.image_path || ''
}

function getRoomImages(room) {
  if (!room) return []

  const records = Array.isArray(room.image_records) ? room.image_records : []
  const urls = Array.isArray(room.images) ? room.images : []
  const mainImage = room.image ? [room.image] : []
  const merged = [...records, ...mainImage, ...urls]

  const unique = []
  const seen = new Set()

  merged.forEach((image) => {
    const src = resolveImageSrc(image)
    if (!src || seen.has(src)) return
    seen.add(src)
    unique.push({ src, raw: image })
  })

  return unique
}

function ImageCarousel({ room, heightClass = 'h-72', showThumbnails = true, onDeleteImage = null, deletingImageId = null }) {
  const images = getRoomImages(room)
  const [activeIndex, setActiveIndex] = useState(0)

  useEffect(() => {
    setActiveIndex(0)
  }, [room?.id, images.length])

  const hasImages = images.length > 0
  const activeImage = hasImages ? images[Math.min(activeIndex, images.length - 1)] : null

  function previousImage() {
    if (!hasImages) return
    setActiveIndex((current) => (current === 0 ? images.length - 1 : current - 1))
  }

  function nextImage() {
    if (!hasImages) return
    setActiveIndex((current) => (current === images.length - 1 ? 0 : current + 1))
  }

  function getImageId(image) {
    if (!image?.raw || typeof image.raw !== 'object') return null
    return image.raw.id || image.raw.image_id || null
  }

  function handleDeleteImage(event, image, index) {
    event.preventDefault()
    event.stopPropagation()
    if (!onDeleteImage) return

    onDeleteImage(image, index)

    if (images.length <= 1) {
      setActiveIndex(0)
      return
    }

    setActiveIndex((current) => {
      if (index < current) return current - 1
      if (index === current && current >= images.length - 1) return images.length - 2
      return current
    })
  }

  return (
    <div className="space-y-3">
      <div className={cn('relative overflow-hidden rounded-2xl border border-border bg-slate-100', heightClass)}>
        {activeImage ? (
          <img src={activeImage.src} alt={room?.room_name || 'Room'} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-slate-400">
            <BedDouble className="h-10 w-10" />
          </div>
        )}

        {activeImage && onDeleteImage && (
          <button
            type="button"
            onClick={(event) => handleDeleteImage(event, activeImage, Math.min(activeIndex, images.length - 1))}
            disabled={deletingImageId && deletingImageId === getImageId(activeImage)}
            className="absolute right-3 top-3 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-red-600 shadow-lg shadow-slate-900/20 transition hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-60"
            aria-label="Delete this room image"
            title="Delete this image"
          >
            <Trash2 className="h-5 w-5" />
          </button>
        )}

        {images.length > 1 && (
          <>
            <button
              type="button"
              onClick={previousImage}
              className="absolute left-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-lg shadow-slate-900/15 transition hover:bg-white"
              aria-label="Previous room image"
            >
              <ChevronLeft className="h-5 w-5" />
            </button>
            <button
              type="button"
              onClick={nextImage}
              className="absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-lg shadow-slate-900/15 transition hover:bg-white"
              aria-label="Next room image"
            >
              <ChevronRight className="h-5 w-5" />
            </button>
            <div className="absolute bottom-3 right-3 rounded-full bg-slate-950/70 px-3 py-1 text-xs font-semibold text-white">
              {Math.min(activeIndex, images.length - 1) + 1} / {images.length}
            </div>
          </>
        )}
      </div>

      {showThumbnails && images.length > 0 && (
        <div className="flex gap-2 overflow-x-auto pb-1">
          {images.map((image, index) => (
            <div key={image.src} className="group relative h-14 w-20 shrink-0">
              <button
                type="button"
                onClick={() => setActiveIndex(index)}
                className={cn(
                  'h-full w-full overflow-hidden rounded-xl border bg-slate-100 transition',
                  index === activeIndex ? 'border-primary-600 ring-2 ring-primary-600/20' : 'border-border hover:border-primary-300'
                )}
                aria-label={`View room image ${index + 1}`}
              >
                <img src={image.src} alt={`${room?.room_name || 'Room'} ${index + 1}`} className="h-full w-full object-cover" />
              </button>
              {onDeleteImage && (
                <button
                  type="button"
                  onClick={(event) => handleDeleteImage(event, image, index)}
                  disabled={deletingImageId && deletingImageId === getImageId(image)}
                  className="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-white opacity-100 shadow-md shadow-slate-900/20 transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60 sm:opacity-0 sm:group-hover:opacity-100"
                  aria-label={`Delete room image ${index + 1}`}
                  title="Delete this image"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

function RoomImage({ src, name }) {
  const imageSrc = resolveImageSrc(src)

  return (
    <div className="h-14 w-20 overflow-hidden rounded-xl border border-border bg-slate-100">
      {imageSrc ? (
        <img src={imageSrc} alt={name} className="h-full w-full object-cover" />
      ) : (
        <div className="flex h-full w-full items-center justify-center text-slate-400">
          <BedDouble className="h-5 w-5" />
        </div>
      )}
    </div>
  )
}

function AmenitiesPreview({ amenities }) {
  const visibleAmenities = amenities.slice(0, 2)
  const hiddenAmenities = amenities.slice(2)

  return (
    <div className="flex max-w-[260px] flex-wrap items-center gap-1.5">
      {visibleAmenities.map((amenity) => (
        <Badge key={amenity.id} variant="secondary" className="whitespace-nowrap text-xs font-medium">
          {amenity.amenity_name}
        </Badge>
      ))}

      {hiddenAmenities.length > 0 && (
        <div className="group relative inline-flex">
          <Badge variant="outline" className="cursor-default whitespace-nowrap text-xs font-semibold">
            +{hiddenAmenities.length}
          </Badge>
          <div className="pointer-events-none absolute left-1/2 top-full z-30 mt-2 hidden w-56 -translate-x-1/2 rounded-xl border border-border bg-white p-3 text-left shadow-xl shadow-slate-900/10 group-hover:block">
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">More amenities</p>
            <div className="space-y-1.5">
              {hiddenAmenities.map((amenity) => (
                <div key={amenity.id} className="flex items-center gap-2 text-sm font-medium text-text-primary">
                  <span className="h-1.5 w-1.5 rounded-full bg-primary-600" />
                  {amenity.amenity_name}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {amenities.length === 0 && <span className="text-xs font-medium text-text-secondary">No amenities</span>}
    </div>
  )
}

function ManageAmenitiesModal({ amenities, onChange, onClose }) {
  const [newName, setNewName] = useState('')
  const [editingId, setEditingId] = useState(null)
  const [editingName, setEditingName] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  async function addItem() {
    const name = newName.trim()
    if (!name || busy) return
    setBusy(true)
    setError('')
    try {
      onChange(await createAmenity(name))
      setNewName('')
    } catch (requestError) {
      setError(requestError.message || 'Unable to add amenity.')
    } finally {
      setBusy(false)
    }
  }

  async function saveItem(id) {
    const name = editingName.trim()
    if (!name || busy) return
    setBusy(true)
    setError('')
    try {
      onChange(await updateAmenity(id, name))
      setEditingId(null)
      setEditingName('')
    } catch (requestError) {
      setError(requestError.message || 'Unable to update amenity.')
    } finally {
      setBusy(false)
    }
  }

  async function removeItem(amenity) {
    const usage = Number(amenity.assigned_room_count || 0)
    const warning = usage > 0
      ? `${amenity.amenity_name} is assigned to ${usage} room${usage === 1 ? '' : 's'}. Delete it and remove those assignments?`
      : `Delete ${amenity.amenity_name}?`
    if (!window.confirm(warning) || busy) return
    setBusy(true)
    setError('')
    try {
      onChange(await deleteAmenity(amenity.id))
    } catch (requestError) {
      setError(requestError.message || 'Unable to delete amenity.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" onClick={onClose}>
      <div className="max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl" onClick={(event) => event.stopPropagation()}>
        <div className="flex items-center justify-between border-b border-border px-6 py-4">
          <div>
            <h2 className="text-lg font-bold text-text-primary">Manage Amenities</h2>
            <p className="text-sm text-text-secondary">Add, rename or delete amenities used by rooms.</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100"><X className="h-5 w-5" /></button>
        </div>
        <div className="max-h-[calc(90vh-80px)] overflow-y-auto p-6">
          <div className="flex gap-3">
            <Input value={newName} onChange={(event) => setNewName(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); addItem() } }} placeholder="New amenity name" />
            <Button type="button" onClick={addItem} disabled={!newName.trim() || busy}><Plus className="h-4 w-4" /> Add</Button>
          </div>
          {error && <p className="mt-3 text-sm font-medium text-red-600">{error}</p>}
          <div className="mt-5 space-y-3">
            {amenities.length === 0 && <p className="rounded-xl bg-slate-50 p-5 text-center text-sm text-text-secondary">No amenities created.</p>}
            {amenities.map((amenity) => (
              <div key={amenity.id} className="flex flex-col gap-3 rounded-xl border border-border p-3 sm:flex-row sm:items-center">
                {editingId === amenity.id ? (
                  <Input value={editingName} onChange={(event) => setEditingName(event.target.value)} className="flex-1" />
                ) : (
                  <div className="flex-1"><p className="font-semibold text-text-primary">{amenity.amenity_name}</p><p className="text-xs text-text-secondary">Used by {amenity.assigned_room_count || 0} rooms</p></div>
                )}
                <div className="flex gap-2">
                  {editingId === amenity.id ? (
                    <><Button type="button" size="sm" onClick={() => saveItem(amenity.id)} disabled={!editingName.trim() || busy}>Save</Button><Button type="button" size="sm" variant="outline" onClick={() => setEditingId(null)}>Cancel</Button></>
                  ) : (
                    <Button type="button" size="sm" variant="outline" onClick={() => { setEditingId(amenity.id); setEditingName(amenity.amenity_name) }}><Pencil className="h-4 w-4" /> Edit</Button>
                  )}
                  <Button type="button" size="sm" variant="outline" onClick={() => removeItem(amenity)} disabled={busy}><Trash2 className="h-4 w-4 text-red-600" /> Delete</Button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}

function RoomActionsDropdown({ room, isOpen, onToggle, onView, onEdit, onDelete }) {
  const dropdownRef = useRef(null)

  useEffect(() => {
    if (!isOpen) return undefined

    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        onToggle()
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [isOpen, onToggle])

  return (
    <div ref={dropdownRef} className="relative inline-flex justify-end">
      <Button type="button" variant="outline" size="sm" onClick={onToggle} className="gap-2">
        Actions
        <MoreHorizontal className="h-4 w-4" />
      </Button>

      {isOpen && (
        <div className="absolute right-0 top-full z-40 mt-2 w-44 overflow-hidden rounded-xl border border-border bg-white py-1 shadow-xl shadow-slate-900/10">
          <button
            type="button"
            onClick={() => onView(room)}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-text-primary transition hover:bg-slate-50"
          >
            <Eye className="h-4 w-4 text-primary-600" />
            View Details
          </button>
          <button
            type="button"
            onClick={() => onEdit(room)}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-text-primary transition hover:bg-slate-50"
          >
            <Pencil className="h-4 w-4 text-primary-600" />
            Edit Room
          </button>
          <button
            type="button"
            onClick={() => onDelete(room)}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50"
          >
            <Trash2 className="h-4 w-4" />
            Delete Room
          </button>
        </div>
      )}
    </div>
  )
}

function Toast({ toast, onClose }) {
  if (!toast) return null

  return (
    <div className="fixed right-6 top-6 z-50 flex w-[calc(100%-3rem)] max-w-sm items-start gap-3 rounded-xl border border-emerald-200 bg-white p-4 text-sm shadow-xl shadow-slate-900/10 sm:w-full">
      <div className="mt-0.5 rounded-full bg-emerald-100 p-1 text-emerald-700">
        <Check className="h-4 w-4" />
      </div>
      <div className="flex-1">
        <p className="font-semibold text-text-primary">{toast.title}</p>
        <p className="mt-0.5 text-text-secondary">{toast.message}</p>
      </div>
      <button onClick={onClose} className="text-slate-400 transition hover:text-slate-700">
        <X className="h-4 w-4" />
      </button>
    </div>
  )
}

function RoomFormModal({ mode, room, amenities, onClose, onSubmit, onDeleteExistingImage }) {
  const [form, setForm] = useState(() => room || emptyForm)
  const [errors, setErrors] = useState({})
  const [selectedFiles, setSelectedFiles] = useState([])
  const [deletingImageId, setDeletingImageId] = useState(null)

  const isEdit = mode === 'edit'

  function updateField(name, value) {
    setForm((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [name]: '' }))
  }

  function toggleAmenity(amenityId) {
    setForm((current) => {
      const exists = current.amenity_ids.includes(amenityId)
      return {
        ...current,
        amenity_ids: exists
          ? current.amenity_ids.filter((id) => id !== amenityId)
          : [...current.amenity_ids, amenityId],
      }
    })
  }

  function removeSelectedFile(_, index) {
    setSelectedFiles((current) => current.filter((__, fileIndex) => fileIndex !== index))
  }

  async function removeExistingImage(image) {
    const imageId = image?.raw && typeof image.raw === 'object' ? image.raw.id || image.raw.image_id : null

    if (!imageId) {
      setErrors((current) => ({ ...current, images: 'This image cannot be deleted because it has no database image id.' }))
      return
    }

    const confirmed = window.confirm('Delete this room image permanently?')
    if (!confirmed) return

    setDeletingImageId(imageId)
    setErrors((current) => ({ ...current, images: '' }))

    try {
      await onDeleteExistingImage(imageId)
      setForm((current) => {
        const imageRecords = Array.isArray(current.image_records) ? current.image_records.filter((record) => Number(record.id || record.image_id) !== Number(imageId)) : []
        const removedSrc = image.src
        const images = Array.isArray(current.images) ? current.images.filter((src) => resolveImageSrc(src) !== removedSrc) : []
        const mainImageSrc = resolveImageSrc(current.image)

        return {
          ...current,
          image_records: imageRecords,
          images,
          image: mainImageSrc === removedSrc ? imageRecords[0] || images[0] || null : current.image,
        }
      })
    } catch (error) {
      setErrors((current) => ({ ...current, images: error.message || 'Unable to delete image.' }))
    } finally {
      setDeletingImageId(null)
    }
  }

  function handleSubmit(event) {
    event.preventDefault()

    const nextErrors = {}
    if (!form.room_name.trim()) nextErrors.room_name = 'Room name is required.'
    if (!form.room_type.trim()) nextErrors.room_type = 'Room type is required.'
    if (!form.room_category.trim()) nextErrors.room_category = 'Room category is required.'
    if (!String(form.price_per_night).trim()) nextErrors.price_per_night = 'Price is required.'
    if (Number(form.price_per_night) <= 0) nextErrors.price_per_night = 'Price must be greater than 0.'
    if (!String(form.capacity).trim()) nextErrors.capacity = 'Capacity is required.'
    if (Number(form.capacity) <= 0) nextErrors.capacity = 'Capacity must be greater than 0.'
    if (!String(form.room_size).trim()) nextErrors.room_size = 'Room size is required.'
    if (Number(form.room_size) <= 0) nextErrors.room_size = 'Room size must be greater than 0.'
    if (!form.description.trim()) nextErrors.description = 'Description is required.'
    if (!form.status.trim()) nextErrors.status = 'Status is required.'

    setErrors(nextErrors)
    if (Object.keys(nextErrors).length > 0) return

    onSubmit({
      ...form,
      price_per_night: Number(form.price_per_night),
      capacity: Number(form.capacity),
      room_size: Number(form.room_size),
      images: selectedFiles,
    })
  }

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" onClick={onClose}>
      <div className="max-h-[92vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/20" onClick={(event) => event.stopPropagation()}>
        <div className="flex items-center justify-between border-b border-border px-6 py-4">
          <div>
            <h2 className="text-lg font-bold text-text-primary">{isEdit ? 'Edit Room' : 'Add Room'}</h2>
            <p className="text-sm text-text-secondary">
              {isEdit ? 'Update room details and amenities.' : 'Create a new room in the inventory.'}
            </p>
          </div>
          <button onClick={onClose} className="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="max-h-[calc(92vh-81px)] overflow-y-auto p-6">
          <div className="grid gap-5 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="room_name">Room name</Label>
              <Input id="room_name" value={form.room_name} onChange={(e) => updateField('room_name', e.target.value)} placeholder="Ex: Ground Floor Room 1" />
              {errors.room_name && <p className="text-xs font-medium text-red-600">{errors.room_name}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="room_type">Room type</Label>
              <select
                id="room_type"
                value={form.room_type}
                onChange={(e) => updateField('room_type', e.target.value)}
                className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              >
                {roomTypes.map((type) => <option key={type}>{type}</option>)}
              </select>
              {errors.room_type && <p className="text-xs font-medium text-red-600">{errors.room_type}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="room_category">Public category</Label>
              <select
                id="room_category"
                value={form.room_category}
                onChange={(e) => updateField('room_category', e.target.value)}
                className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              >
                {roomCategories.map((category) => <option key={category.value} value={category.value}>{category.label}</option>)}
              </select>
              {errors.room_category && <p className="text-xs font-medium text-red-600">{errors.room_category}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="price_per_night">Price per night</Label>
              <Input id="price_per_night" type="number" min="1" value={form.price_per_night} onChange={(e) => updateField('price_per_night', e.target.value)} placeholder="Ex: 380" />
              {errors.price_per_night && <p className="text-xs font-medium text-red-600">{errors.price_per_night}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="capacity">Capacity</Label>
              <Input id="capacity" type="number" min="1" value={form.capacity} onChange={(e) => updateField('capacity', e.target.value)} placeholder="Ex: 2" />
              {errors.capacity && <p className="text-xs font-medium text-red-600">{errors.capacity}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="bed_type">Bed type</Label>
              <select
                id="bed_type"
                value={form.bed_type}
                onChange={(e) => updateField('bed_type', e.target.value)}
                className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              >
                {bedTypes.map((bedType) => <option key={bedType}>{bedType}</option>)}
              </select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="room_size">Room size (m²)</Label>
              <Input id="room_size" type="number" min="1" value={form.room_size} onChange={(e) => updateField('room_size', e.target.value)} placeholder="Ex: 24" />
              {errors.room_size && <p className="text-xs font-medium text-red-600">{errors.room_size}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="status">Status</Label>
              <select
                id="status"
                value={form.status}
                onChange={(e) => updateField('status', e.target.value)}
                className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              >
                {roomStatuses.map((status) => <option key={status}>{status}</option>)}
              </select>
              {errors.status && <p className="text-xs font-medium text-red-600">{errors.status}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="room_images">Room images</Label>
              <div className="flex items-center gap-3 rounded-lg border border-dashed border-border bg-slate-50 px-3 py-2">
                <ImagePlus className="h-5 w-5 text-primary-600" />
                <Input
                  id="room_images"
                  type="file"
                  multiple
                  accept="image/png,image/jpeg,image/webp"
                  onChange={(e) => setSelectedFiles(Array.from(e.target.files || []))}
                  className="border-0 bg-transparent p-0 shadow-none focus-visible:ring-0"
                />
              </div>
              {isEdit && <p className="text-xs text-text-secondary">Upload new images only if you want to add more photos.</p>}
            </div>

            <div className="space-y-2 md:col-span-2">
              <Label htmlFor="description">Description</Label>
              <Textarea id="description" value={form.description} onChange={(e) => updateField('description', e.target.value)} placeholder="Short room description" />
              {errors.description && <p className="text-xs font-medium text-red-600">{errors.description}</p>}
            </div>

            <div className="space-y-3 md:col-span-2">
              <Label>Amenities</Label>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {amenities.map((amenity) => {
                  const Icon = amenityIcons[amenity.icon] || Check
                  const selected = form.amenity_ids.includes(amenity.id)

                  return (
                    <button
                      type="button"
                      key={amenity.id}
                      onClick={() => toggleAmenity(amenity.id)}
                      className={cn(
                        'flex items-center gap-2 rounded-xl border px-3 py-2 text-left text-sm transition',
                        selected
                          ? 'border-primary-600 bg-blue-50 text-primary-700 shadow-sm'
                          : 'border-border bg-white text-text-secondary hover:border-blue-200 hover:bg-slate-50'
                      )}
                    >
                      <Icon className="h-4 w-4 shrink-0" />
                      <span className="truncate font-medium">{amenity.amenity_name}</span>
                    </button>
                  )
                })}
              </div>
              <label className="flex items-start gap-3 rounded-xl border border-border bg-slate-50 p-3">
                <input
                  type="checkbox"
                  checked={Boolean(form.show_unavailable_amenities)}
                  onChange={(event) => updateField('show_unavailable_amenities', event.target.checked)}
                  className="mt-1 h-4 w-4 rounded border-border text-primary-600 focus:ring-primary-500"
                />
                <span>
                  <span className="block text-sm font-semibold text-text-primary">Show amenities this room does not provide</span>
                  <span className="mt-1 block text-xs text-text-secondary">Unselected catalogue amenities will appear with a cross on the public room-details page.</span>
                </span>
              </label>
            </div>
          </div>

          {(isEdit || selectedFiles.length > 0) && (
            <div className="mt-6 border-t border-border pt-5">
              <div className="mb-3 flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-text-primary">Room image preview</p>
                  <p className="text-xs text-text-secondary">
                    Use the arrows or thumbnails to review photos. Use the delete button to remove a specific image.
                  </p>
                </div>
                {selectedFiles.length > 0 && (
                  <Badge variant="secondary">{selectedFiles.length} new selected</Badge>
                )}
              </div>

              {selectedFiles.length > 0 ? (
                <ImageCarousel
                  room={{
                    ...form,
                    images: selectedFiles.map((file) => URL.createObjectURL(file)),
                    image_records: [],
                    image: null,
                  }}
                  heightClass="h-56"
                  onDeleteImage={removeSelectedFile}
                />
              ) : (
                <ImageCarousel
                  room={form}
                  heightClass="h-56"
                  onDeleteImage={removeExistingImage}
                  deletingImageId={deletingImageId}
                />
              )}
              {errors.images && <p className="mt-3 text-xs font-medium text-red-600">{errors.images}</p>}
            </div>
          )}

          <div className="mt-6 flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-end">
            <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
            <Button type="submit">{isEdit ? 'Save Changes' : 'Add Room'}</Button>
          </div>
        </form>
      </div>
    </div>
  )
}

function RoomDetailsModal({ room, amenities, image, onClose }) {
  if (!room) return null

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center overflow-x-hidden bg-slate-950/50 p-3 backdrop-blur-sm sm:p-4" onClick={onClose}>
      <div className="max-h-[92vh] w-full max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/20 sm:max-w-4xl" onClick={(event) => event.stopPropagation()}>
        <div className="flex items-start justify-between gap-3 border-b border-border px-4 py-4 sm:px-6">
          <div>
            <h2 className="text-lg font-bold text-text-primary">Room Details</h2>
            <p className="text-sm text-text-secondary">Full room information, pricing, images, and amenities.</p>
          </div>
          <button onClick={onClose} className="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="max-h-[calc(92vh-81px)] overflow-x-hidden overflow-y-auto p-4 sm:p-6">
          <div className="grid min-w-0 gap-6 lg:grid-cols-[1fr_1.1fr]">
            <ImageCarousel room={room} heightClass="h-52 sm:h-72" />

            <div className="min-w-0 space-y-5">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 className="break-words text-xl font-bold text-text-primary sm:text-2xl">{room.room_name}</h3>
                  <p className="mt-1 text-sm font-medium text-text-secondary">{room.room_type} room · {room.capacity} guests · {room.bed_type || room.beds} · {room.room_size || room.size} m²</p>
                </div>
                <Badge variant={statusVariant[room.status] || 'secondary'}>{room.status}</Badge>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl border border-border bg-slate-50 p-4">
                  <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Price per night</p>
                  <p className="mt-1 text-xl font-bold text-text-primary">{formatCurrency(room.price_per_night)}</p>
                </div>
                <div className="rounded-xl border border-border bg-slate-50 p-4">
                  <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Capacity</p>
                  <p className="mt-1 text-xl font-bold text-text-primary">{room.capacity} guests</p>
                </div>
              </div>

              <div>
                <p className="text-sm font-semibold text-text-primary">Description</p>
                <p className="mt-2 break-words text-sm leading-6 text-text-secondary">{room.description}</p>
              </div>

              <div>
                <p className="text-sm font-semibold text-text-primary">Amenities</p>
                <div className="mt-3 flex min-w-0 flex-wrap gap-2 overflow-hidden">
                  {amenities.map((amenity) => (
                    <Badge key={amenity.id} variant="secondary">{amenity.amenity_name}</Badge>
                  ))}
                </div>
              </div>

              <div className="grid gap-3 border-t border-border pt-4 text-sm sm:grid-cols-2">
                <div>
                  <p className="font-medium text-text-secondary">Created</p>
                  <p className="font-semibold text-text-primary">{formatDate(room.created_at)}</p>
                </div>
                <div>
                  <p className="font-medium text-text-secondary">Updated</p>
                  <p className="font-semibold text-text-primary">{formatDate(room.updated_at)}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function DeleteDialog({ room, onCancel, onConfirm }) {
  if (!room) return null

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" onClick={onCancel}>
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl shadow-slate-950/20" onClick={(event) => event.stopPropagation()}>
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-700">
          <Trash2 className="h-5 w-5" />
        </div>
        <h2 className="mt-4 text-lg font-bold text-text-primary">Delete room?</h2>
        <p className="mt-2 text-sm leading-6 text-text-secondary">
          This will remove <span className="font-semibold text-text-primary">{room.room_name}</span> from the database and public website.
        </p>
        <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <Button variant="outline" onClick={onCancel}>Cancel</Button>
          <Button variant="destructive" onClick={onConfirm}>Delete Room</Button>
        </div>
      </div>
    </div>
  )
}

export default function Rooms() {
  const [rooms, setRooms] = useState([])
  const [amenities, setAmenities] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('All')
  const [typeFilter, setTypeFilter] = useState('All')
  const [formMode, setFormMode] = useState(null)
  const [selectedRoom, setSelectedRoom] = useState(null)
  const [viewRoom, setViewRoom] = useState(null)
  const [deleteRoom, setDeleteRoom] = useState(null)
  const [toast, setToast] = useToastState(null)
  const [openActionsId, setOpenActionsId] = useState(null)
  const [manageAmenitiesOpen, setManageAmenitiesOpen] = useState(false)

  function showToast(title, message) {
    setToast({ title, message })
    window.setTimeout(() => setToast(null), 2800)
  }

  async function loadRooms() {
    setLoading(true)
    setLoadError('')

    try {
      const [roomData, amenityData] = await Promise.all([listRooms(), listAmenities()])
      setRooms(roomData)
      setAmenities(amenityData)
    } catch (error) {
      setLoadError(error.message || 'Unable to load rooms.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadRooms()
  }, [])

  const roomTypeOptions = useMemo(() => ['All', ...new Set(rooms.map((room) => room.room_type || room.type).filter(Boolean))], [rooms])
  const roomStatusOptions = ['All', ...roomStatuses]

  const enrichedRooms = useMemo(() => {
    return rooms.map((room) => {
      const amenityRecords = Array.isArray(room.amenity_records) ? room.amenity_records : []
      const amenityIds = Array.isArray(room.amenity_ids) ? room.amenity_ids.map(Number) : []
      const imageRecords = Array.isArray(room.image_records) ? room.image_records : []
      const imageUrls = Array.isArray(room.images) ? room.images : []
      const image = imageRecords[0] || room.image || (room.main_image ? { image_url: room.main_image } : null)

      return {
        ...room,
        image_records: imageRecords,
        images: imageUrls,
        room_type: room.room_type || room.type,
        price_per_night: room.price_per_night ?? room.base_price ?? room.price,
        capacity: room.capacity ?? room.max_guests ?? room.guests,
        room_category: room.room_category || room.category || 'standard',
        bed_type: room.bed_type || room.beds || 'Double Bed',
        room_size: room.room_size ?? room.size ?? 24,
        image,
        amenities: amenityRecords,
        amenity_ids: amenityIds,
        show_unavailable_amenities: Boolean(room.show_unavailable_amenities),
      }
    })
  }, [rooms])

  const filteredRooms = useMemo(() => {
    const term = search.trim().toLowerCase()

    return enrichedRooms.filter((room) => {
      const matchesSearch = !term || room.room_name.toLowerCase().includes(term) || room.room_type.toLowerCase().includes(term) || String(room.room_category || '').toLowerCase().includes(term)
      const matchesStatus = statusFilter === 'All' || room.status === statusFilter
      const matchesType = typeFilter === 'All' || room.room_type === typeFilter
      return matchesSearch && matchesStatus && matchesType
    })
  }, [enrichedRooms, search, statusFilter, typeFilter])

  function openAddModal() {
    setOpenActionsId(null)
    setSelectedRoom(null)
    setFormMode('add')
  }

  function openEditModal(room) {
    setOpenActionsId(null)
    setSelectedRoom({
      ...room,
      room_type: room.room_type || room.type || 'Ground Floor',
      room_category: room.room_category || room.category || 'standard',
      price_per_night: room.price_per_night ?? room.base_price ?? room.price,
      capacity: room.capacity ?? room.max_guests ?? room.guests,
      bed_type: room.bed_type || room.beds || 'Double Bed',
      room_size: room.room_size ?? room.size ?? 24,
      currency: room.currency || 'LKR',
      amenity_ids: room.amenity_ids || [],
      show_unavailable_amenities: Boolean(room.show_unavailable_amenities),
    })
    setFormMode('edit')
  }

  function closeFormModal() {
    setFormMode(null)
    setSelectedRoom(null)
  }

  async function handleSubmitRoom(form) {
    const payload = new FormData()
    if (formMode === 'edit') payload.append('id', selectedRoom.id)
    payload.append('room_name', form.room_name)
    payload.append('description', form.description)
    payload.append('max_guests', form.capacity)
    payload.append('capacity', form.capacity)
    payload.append('room_category', form.room_category || 'standard')
    payload.append('category', form.room_category || 'standard')
    payload.append('bed_type', form.bed_type || 'Double Bed')
    payload.append('room_size', form.room_size || 24)
    payload.append('size', form.room_size || 24)
    payload.append('base_price', form.price_per_night)
    payload.append('price_per_night', form.price_per_night)
    payload.append('currency', form.currency || 'LKR')
    payload.append('amenity_ids', JSON.stringify(form.amenity_ids))
    payload.append('show_unavailable_amenities', form.show_unavailable_amenities ? '1' : '0')
    payload.append('status', form.status)
    payload.append('sort_order', form.sort_order || 0)

    ;(form.images || []).forEach((file) => payload.append('images[]', file))

    try {
      if (formMode === 'edit') {
        await updateRoom(payload)
        showToast('Room updated', `${form.room_name} was updated successfully.`)
      } else {
        await createRoom(payload)
        showToast('Room added', `${form.room_name} was added successfully.`)
      }
      closeFormModal()
      await loadRooms()
    } catch (error) {
      showToast('Room save failed', error.message || 'Unable to save room.')
    }
  }

  async function handleDeleteRoomImage(imageId) {
    await deleteRoomImage(imageId)
    await loadRooms()
    showToast('Image deleted', 'The selected room image was removed.')
  }

  async function confirmDeleteRoom() {
    try {
      await deleteRoomById(deleteRoom.id)
      showToast('Room deleted', `${deleteRoom.room_name} was removed.`)
      setDeleteRoom(null)
      await loadRooms()
    } catch (error) {
      showToast('Delete failed', error.message || 'Unable to delete room.')
    }
  }

  return (
    <div className="min-w-0 max-w-full space-y-6 overflow-x-hidden">
      <Toast toast={toast} onClose={() => setToast(null)} />

      <div className="mb-8 flex items-start justify-between gap-4">
        <div className="min-w-0">
          <h1 className="text-2xl font-bold text-text-primary md:text-3xl">Rooms</h1>
          <p className="mt-1 text-sm text-text-secondary">Manage room inventory, pricing, amenities, images, and availability.</p>
        </div>
        <div className="flex shrink-0 gap-2">
          <Button type="button" variant="outline" onClick={() => setManageAmenitiesOpen(true)}>
            <Settings2 className="h-4 w-4" />
            Manage Amenities
          </Button>
          <Button onClick={openAddModal}>
            <Plus className="h-4 w-4" />
            Add Room
          </Button>
        </div>
      </div>

      <div className="grid min-w-0 max-w-full gap-4 rounded-2xl border border-border bg-white p-4 shadow-sm shadow-slate-200/60 md:grid-cols-[minmax(0,1fr)_180px_180px]">
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by room name or type..." className="pl-9" />
        </div>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="h-10 rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        >
          {roomStatusOptions.map((status) => <option key={status}>{status}</option>)}
        </select>
        <select
          value={typeFilter}
          onChange={(e) => setTypeFilter(e.target.value)}
          className="h-10 rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
        >
          {roomTypeOptions.map((type) => <option key={type}>{type}</option>)}
        </select>
      </div>

      {loading && (
        <div className="rounded-2xl border border-border bg-white p-8 text-center text-sm text-text-secondary shadow-sm shadow-slate-200/60">Loading rooms from database...</div>
      )}

      {loadError && !loading && (
        <div className="rounded-2xl border border-red-200 bg-white p-8 text-center text-sm font-medium text-red-600 shadow-sm shadow-slate-200/60">{loadError}</div>
      )}

      {!loading && !loadError && (
      <div className="max-w-full overflow-hidden rounded-2xl border border-border bg-white shadow-sm shadow-slate-200/60">
        <div className="w-full max-w-full overflow-x-auto overscroll-x-contain">
          <table className="w-full min-w-[780px] text-left text-sm md:min-w-[980px]">
            <thead className="sticky top-0 z-10 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-text-secondary">
              <tr>
                <th className="px-5 py-4">Room</th>
                <th className="px-5 py-4">Type</th>
                <th className="px-5 py-4">Price / Night</th>
                <th className="px-5 py-4">Capacity</th>
                <th className="px-5 py-4">Amenities</th>
                <th className="px-5 py-4">Status</th>
                <th className="px-5 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {filteredRooms.map((room) => (
                <tr key={room.id} className="transition hover:bg-slate-50/80">
                  <td className="px-5 py-4">
                    <div className="flex items-center gap-3">
                      <RoomImage src={room.image} name={room.room_name} />
                      <div>
                        <p className="font-semibold text-text-primary">{room.room_name}</p>
                        <p className="mt-0.5 text-xs text-text-secondary">Updated {formatDate(room.updated_at)}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-5 py-4 font-medium text-text-primary">{room.room_type}<span className="block text-xs font-normal capitalize text-text-secondary">{room.room_category}</span></td>
                  <td className="px-5 py-4 font-semibold text-text-primary">{formatCurrency(room.price_per_night)}</td>
                  <td className="px-5 py-4 text-text-secondary">{room.capacity} guests<span className="block text-xs">{room.bed_type} · {room.room_size} m²</span></td>
                  <td className="px-5 py-4">
                    <AmenitiesPreview amenities={room.amenities} />
                  </td>
                  <td className="px-5 py-4">
                    <Badge variant={statusVariant[room.status] || 'secondary'}>{room.status}</Badge>
                  </td>
                  <td className="px-5 py-4 text-right">
                    <RoomActionsDropdown
                      room={room}
                      isOpen={openActionsId === room.id}
                      onToggle={() => setOpenActionsId((current) => (current === room.id ? null : room.id))}
                      onView={(selected) => {
                        setOpenActionsId(null)
                        setViewRoom(selected)
                      }}
                      onEdit={openEditModal}
                      onDelete={(selected) => {
                        setOpenActionsId(null)
                        setDeleteRoom(selected)
                      }}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {filteredRooms.length === 0 && (
          <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-primary-600">
              <BedDouble className="h-7 w-7" />
            </div>
            <h3 className="mt-4 text-base font-bold text-text-primary">No rooms found</h3>
            <p className="mt-1 max-w-md text-sm text-text-secondary">
              Your filters did not match any rooms. Clear the filters or add a new room.
            </p>
            <Button className="mt-5" onClick={openAddModal}>
              <Plus className="h-4 w-4" />
              Add Room
            </Button>
          </div>
        )}
      </div>
      )}

      {formMode && (
        <RoomFormModal
          mode={formMode}
          room={selectedRoom}
          amenities={amenities}
          onClose={closeFormModal}
          onSubmit={handleSubmitRoom}
          onDeleteExistingImage={handleDeleteRoomImage}
        />
      )}

      {viewRoom && (
        <RoomDetailsModal
          room={viewRoom}
          amenities={viewRoom.amenities}
          image={viewRoom.image}
          onClose={() => setViewRoom(null)}
        />
      )}

      <DeleteDialog
        room={deleteRoom}
        onCancel={() => setDeleteRoom(null)}
        onConfirm={confirmDeleteRoom}
      />

      {manageAmenitiesOpen && (
        <ManageAmenitiesModal
          amenities={amenities}
          onChange={(nextAmenities) => {
            setAmenities(nextAmenities)
            loadRooms()
          }}
          onClose={() => setManageAmenitiesOpen(false)}
        />
      )}
    </div>
  )
}
