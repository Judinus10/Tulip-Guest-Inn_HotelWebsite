import { useEffect, useMemo, useState } from 'react'
import {
  Edit,
  Eye,
  ImagePlus,
  Clock,
  MapPin,
  Plus,
  Search,
  Sparkles,
  Trash2,
  X,
} from 'lucide-react'

import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Input, Label } from '@/components/ui/input'
import { useToastState } from '@/context/ToastContext'
import { optimizeImage } from '@/utils/imageProcessing'
import {
  createExperienceItem,
  deleteExperienceItem,
  fetchExperienceItems,
  updateExperienceItem,
} from '@/services/experienceApi'

const statuses = ['active', 'inactive']

const categories = [
  'Culture',
  'Food',
  'Beach',
  'Island',
  'Attraction',
  'Activity',
  'Heritage',
  'Nature',
]

const emptyForm = {
  id: null,
  title: '',
  category: 'Culture',
  location: '',
  distance: '',
  duration: '',
  description: '',
  image_path: '',
  image_file: null,
  status: 'active',
  sort_order: 1,
}

function titleCase(value) {
  return String(value || '')
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function statusVariant(status) {
  return status === 'active' ? 'success' : 'secondary'
}

export default function Experience() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [processingImage, setProcessingImage] = useState(false)
  const [error, setError] = useState('')
  const [toast, setToast] = useToastState('')
  const [searchTerm, setSearchTerm] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [modal, setModal] = useState(null)
  const [deleteTarget, setDeleteTarget] = useState(null)

  const filteredItems = useMemo(() => {
    const query = searchTerm.trim().toLowerCase()

    return items
      .filter((item) => {
        if (!query) return true
        return (
          item.title.toLowerCase().includes(query) ||
          item.description.toLowerCase().includes(query) ||
          item.location.toLowerCase().includes(query) ||
          item.distance.toLowerCase().includes(query) ||
          item.duration.toLowerCase().includes(query)
        )
      })
      .filter((item) => categoryFilter === 'all' || item.category === categoryFilter)
      .filter((item) => statusFilter === 'all' || item.status === statusFilter)
      .sort((a, b) => Number(a.sort_order) - Number(b.sort_order))
  }, [items, searchTerm, categoryFilter, statusFilter])

  const summary = useMemo(() => ({
    total: items.length,
    active: items.filter((item) => item.status === 'active').length,
    inactive: items.filter((item) => item.status === 'inactive').length,
    categories: new Set(items.map((item) => item.category)).size,
  }), [items])

  async function loadItems() {
    setLoading(true)
    setError('')

    try {
      const data = await fetchExperienceItems()
      setItems(data)
    } catch (err) {
      setError(err.message || 'Failed to load experience items.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadItems()
  }, [])

  function showToast(message) {
    setToast(message)
    window.setTimeout(() => setToast(''), 2500)
  }

  function openCreateModal() {
    setModal({
      mode: 'create',
      form: {
        ...emptyForm,
        sort_order: items.length + 1,
      },
    })
  }

  function openEditModal(item) {
    setModal({
      mode: 'edit',
      form: {
        ...emptyForm,
        ...item,
        distance: item.distance || '',
        duration: item.duration || '',
        location: item.location || '',
        description: item.description || '',
        image_file: null,
      },
    })
  }

  function updateForm(field, value) {
    setModal((prev) => ({
      ...prev,
      form: {
        ...prev.form,
        [field]: value,
      },
    }))
  }

  async function selectExperienceImage(event) {
    const original = event.target.files?.[0]
    event.target.value = ''
    if (!original) return

    setProcessingImage(true)
    setError('')
    try {
      updateForm('image_file', await optimizeImage(original))
    } catch (err) {
      setError(err.message || 'Unable to optimize the selected image.')
    } finally {
      setProcessingImage(false)
    }
  }

  async function saveItem(event) {
    event.preventDefault()

    if (!modal.form.title.trim()) return setError('Title is required.')
    if (!modal.form.category.trim()) return setError('Category is required.')
    if (!modal.form.description.trim()) return setError('Description is required.')

    setSaving(true)
    setError('')

    try {
      if (modal.mode === 'create') {
        await createExperienceItem(modal.form)
        showToast('Experience item created successfully.')
      } else {
        await updateExperienceItem(modal.form)
        showToast('Experience item updated successfully.')
      }

      setModal(null)
      await loadItems()
    } catch (err) {
      setError(err.message || 'Unable to save experience item.')
    } finally {
      setSaving(false)
    }
  }

  async function confirmDelete() {
    if (!deleteTarget) return

    setSaving(true)
    setError('')

    try {
      await deleteExperienceItem(deleteTarget.id)
      setDeleteTarget(null)
      showToast('Experience item deleted successfully.')
      await loadItems()
    } catch (err) {
      setError(err.message || 'Delete failed.')
    } finally {
      setSaving(false)
    }
  }

  const stats = [
    { label: 'Total Items', value: summary.total },
    { label: 'Active', value: summary.active },
    { label: 'Inactive', value: summary.inactive },
    { label: 'Categories', value: summary.categories },
  ]

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <PageHeader
          title="Experience Management"
          description="Manage public website experience content such as culture, food, attractions, beaches, and activities."
        />

        <Button type="button" onClick={openCreateModal}>
          <Plus className="h-4 w-4" />
          Add Experience
        </Button>
      </div>

      {toast ? (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {toast}
        </div>
      ) : null}

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {error}
        </div>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.label}>
            <CardContent className="flex items-center justify-between p-5">
              <div>
                <p className="text-sm font-medium text-text-secondary">{stat.label}</p>
                <p className="mt-2 text-2xl font-bold text-text-primary">{stat.value}</p>
              </div>
              <div className="rounded-xl bg-blue-50 p-3 text-blue-700">
                <Sparkles className="h-5 w-5" />
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardContent className="grid gap-3 p-4 lg:grid-cols-[1fr_180px_180px]">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              placeholder="Search title, description, location..."
              className="pl-9"
            />
          </div>

          <select
            value={categoryFilter}
            onChange={(event) => setCategoryFilter(event.target.value)}
            className="h-10 rounded-lg border border-border bg-white px-3 text-sm"
          >
            <option value="all">All Categories</option>
            {categories.map((category) => (
              <option key={category} value={category}>{category}</option>
            ))}
          </select>

          <select
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
            className="h-10 rounded-lg border border-border bg-white px-3 text-sm"
          >
            <option value="all">All Status</option>
            {statuses.map((status) => (
              <option key={status} value={status}>{titleCase(status)}</option>
            ))}
          </select>
        </CardContent>
      </Card>

      {loading ? (
        <Card>
          <CardContent className="p-8 text-sm text-text-secondary">
            Loading experience items...
          </CardContent>
        </Card>
      ) : null}

      {!loading && filteredItems.length === 0 ? (
        <Card>
          <CardContent className="p-8 text-center text-sm text-text-secondary">
            No experience items found.
          </CardContent>
        </Card>
      ) : null}

      {!loading && filteredItems.length > 0 ? (
        <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
          {filteredItems.map((item) => (
            <Card key={item.id} className="overflow-hidden">
              <div className="relative h-48 bg-slate-100">
                {item.image_path ? (
                  <img
                    src={item.image_path}
                    alt={item.title}
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <div className="flex h-full items-center justify-center text-slate-400">
                    <ImagePlus className="h-10 w-10" />
                  </div>
                )}

                <div className="absolute left-3 top-3">
                  <Badge variant={statusVariant(item.status)}>
                    {item.status}
                  </Badge>
                </div>
              </div>

              <CardContent className="p-5">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-primary-600">
                      {item.category}
                    </p>
                    <h3 className="mt-1 text-base font-semibold text-text-primary">
                      {item.title}
                    </h3>
                  </div>

                  <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                    #{item.sort_order}
                  </span>
                </div>

                {item.location || item.distance ? (
                  <p className="mt-3 flex items-center gap-1.5 text-sm text-text-secondary">
                    <MapPin className="h-4 w-4 shrink-0" />
                    {item.location ? <span>{item.location}</span> : null}
                    {item.location && item.distance ? <span>•</span> : null}
                    {item.distance ? <span>{item.distance}</span> : null}
                  </p>
                ) : null}

                {item.duration ? (
                  <p className="mt-2 flex items-center gap-1.5 text-sm text-text-secondary">
                    <Clock className="h-4 w-4 shrink-0" />
                    <span>{item.duration}</span>
                  </p>
                ) : null}

                <p className="mt-3 line-clamp-3 text-sm leading-6 text-text-secondary">
                  {item.description}
                </p>

                <div className="mt-5 flex flex-wrap gap-2">
                  <Button type="button" size="sm" variant="outline" onClick={() => openEditModal(item)}>
                    <Edit className="h-4 w-4" />
                    Edit
                  </Button>

                  <Button type="button" size="sm" variant="destructive" onClick={() => setDeleteTarget(item)}>
                    <Trash2 className="h-4 w-4" />
                    Delete
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      ) : null}

      {modal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" onClick={() => setModal(null)}>
          <div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-start justify-between gap-4 border-b border-border p-6">
              <div>
                <h2 className="text-xl font-bold text-text-primary">
                  {modal.mode === 'create' ? 'Add Experience Item' : 'Edit Experience Item'}
                </h2>
                <p className="mt-1 text-sm text-text-secondary">
                  This content appears on the public Experience page.
                </p>
              </div>

              <button
                type="button"
                onClick={() => setModal(null)}
                className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={saveItem} className="space-y-5 p-6">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label>Title</Label>
                  <Input
                    value={modal.form.title}
                    onChange={(event) => updateForm('title', event.target.value)}
                    placeholder="Nallur Temple Visit"
                  />
                </div>

                <div className="space-y-2">
                  <Label>Category</Label>
                  <select
                    value={modal.form.category}
                    onChange={(event) => updateForm('category', event.target.value)}
                    className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm"
                  >
                    {categories.map((category) => (
                      <option key={category} value={category}>{category}</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-2">
                  <Label>Location optional</Label>
                  <Input
                    value={modal.form.location}
                    onChange={(event) => updateForm('location', event.target.value)}
                    placeholder="Jaffna"
                  />
                </div>

                <div className="space-y-2">
                  <Label>Distance</Label>
                  <Input
                    value={modal.form.distance}
                    onChange={(e) => updateForm('distance', e.target.value)}
                    placeholder="5 km"
                  />
                </div>

                <div className="space-y-2">
                  <Label>Time</Label>
                  <Input
                    value={modal.form.duration}
                    onChange={(event) => updateForm('duration', event.target.value)}
                    placeholder="5 min drive"
                  />
                </div>

                <div className="space-y-2">
                  <Label>Sort order</Label>
                  <Input
                    type="number"
                    min="1"
                    value={modal.form.sort_order}
                    onChange={(event) => updateForm('sort_order', Number(event.target.value))}
                  />
                </div>

                <div className="space-y-2">
                  <Label>Status</Label>
                  <select
                    value={modal.form.status}
                    onChange={(event) => updateForm('status', event.target.value)}
                    className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm"
                  >
                    {statuses.map((status) => (
                      <option key={status} value={status}>{titleCase(status)}</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-2">
                  <Label>Image</Label>
                  <Input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={selectExperienceImage}
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label>Description</Label>
                <textarea
                  value={modal.form.description}
                  onChange={(event) => updateForm('description', event.target.value)}
                  rows={5}
                  className="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100"
                  placeholder="Describe the experience..."
                />
              </div>

              {modal.form.image_path || modal.form.image_file ? (
                <div className="rounded-xl border border-border p-3">
                  <p className="mb-2 text-sm font-medium text-text-secondary">Preview</p>
                  <img
                    src={
                      modal.form.image_file
                        ? URL.createObjectURL(modal.form.image_file)
                        : modal.form.image_path
                    }
                    alt="Experience preview"
                    className="h-44 w-full rounded-lg object-cover"
                  />
                </div>
              ) : null}

              <div className="flex justify-end gap-3 border-t border-border pt-5">
                <Button type="button" variant="outline" onClick={() => setModal(null)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={saving || processingImage}>
                  {processingImage ? 'Optimizing...' : saving ? 'Saving...' : 'Save Experience'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      {deleteTarget ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" onClick={() => setDeleteTarget(null)}>
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" onClick={(event) => event.stopPropagation()}>
            <h2 className="text-lg font-bold text-text-primary">Delete Experience Item</h2>
            <p className="mt-2 text-sm text-text-secondary">
              This will permanently delete this item from the database.
            </p>

            <div className="mt-6 flex justify-end gap-3">
              <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
                Cancel
              </Button>
              <Button type="button" variant="destructive" onClick={confirmDelete} disabled={saving}>
                {saving ? 'Deleting...' : 'Delete'}
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  )
}
