import { useEffect, useMemo, useState } from 'react'
import { ArrowLeft, Eye, Folder, Images, Pencil, Plus, Search, SlidersHorizontal, Trash2, Upload, X } from 'lucide-react'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Input, Label } from '@/components/ui/input'
import {
  createGalleryFolder,
  deleteGalleryFolder,
  deleteGalleryImage,
  fetchGallery,
  updateGalleryFolder,
  updateGalleryImage,
  uploadGalleryImages,
} from '@/services/galleryApi'

const fallbackImage = 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?auto=format&fit=crop&w=900&q=80'
const statuses = ['active', 'inactive']

function titleCase(value) {
  return String(value || '')
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function generateTitleFromFileName(fileName) {
  return titleCase(String(fileName || 'Gallery Image').replace(/\.[^/.]+$/, ''))
}

function formatDate(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric' }).format(new Date(value))
}

function statusVariant(status) {
  return status === 'active' ? 'success' : 'secondary'
}

function StatCard({ title, value, description, icon: Icon }) {
  return (
    <Card>
      <CardContent className="p-5">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-sm font-medium text-text-secondary">{title}</p>
            <p className="mt-2 text-2xl font-bold tracking-tight text-text-primary">{value}</p>
            <p className="mt-1 text-xs text-text-secondary">{description}</p>
          </div>
          <div className="rounded-xl bg-blue-50 p-3 text-blue-700">
            <Icon className="h-5 w-5" />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

export default function Gallery() {
  const [folders, setFolders] = useState([])
  const [images, setImages] = useState([])
  const [activeFolderId, setActiveFolderId] = useState(null)
  const [searchTerm, setSearchTerm] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [toast, setToast] = useState('')
  const [folderModal, setFolderModal] = useState(null)
  const [imageModal, setImageModal] = useState(null)
  const [deleteTarget, setDeleteTarget] = useState(null)

  const activeFolder = folders.find((folder) => folder.id === activeFolderId) || null

  const summary = useMemo(() => ({
    folders: folders.length,
    images: images.length,
    activeImages: images.filter((image) => image.status === 'active').length,
    inactiveImages: images.filter((image) => image.status === 'inactive').length,
  }), [folders, images])

  const folderStats = useMemo(() => folders.map((folder) => {
    const folderImages = images.filter((image) => image.folder_id === folder.id)
    return {
      ...folder,
      image_count: folderImages.length,
      active_count: folderImages.filter((image) => image.status === 'active').length,
      inactive_count: folderImages.filter((image) => image.status === 'inactive').length,
      cover_image: folderImages.sort((a, b) => Number(a.sort_order) - Number(b.sort_order))[0]?.image_path || folder.cover_image || fallbackImage,
    }
  }), [folders, images])

  const filteredImages = useMemo(() => {
    const query = searchTerm.trim().toLowerCase()
    return images
      .filter((image) => image.folder_id === activeFolderId)
      .filter((image) => !query || image.title.toLowerCase().includes(query))
      .filter((image) => statusFilter === 'all' || image.status === statusFilter)
      .sort((a, b) => Number(a.sort_order) - Number(b.sort_order))
  }, [images, activeFolderId, searchTerm, statusFilter])

  async function loadGallery() {
    setLoading(true)
    setError('')
    try {
      const data = await fetchGallery()
      setFolders(data.folders || [])
      setImages(data.images || [])
    } catch (err) {
      setError(err.message || 'Failed to load gallery.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadGallery()
  }, [])

  function showToast(message) {
    setToast(message)
    window.setTimeout(() => setToast(''), 2500)
  }

  function openAddFolder() {
    setFolderModal({ mode: 'add', id: null, name: '', status: 'active' })
  }

  function openEditFolder(folder) {
    setFolderModal({ mode: 'edit', id: folder.id, name: folder.name, status: folder.status })
  }

  function openAddImages(folderId = activeFolderId || folders[0]?.id) {
    if (!folderId) {
      setError('Create a folder first, then upload images.')
      return
    }
    const nextSort = images.filter((image) => image.folder_id === folderId).length + 1
    setImageModal({ mode: 'add', folder_id: folderId, status: 'active', sort_order: nextSort, images: [] })
  }

  function openEditImage(image) {
    setImageModal({
      mode: 'edit',
      id: image.id,
      title: image.title,
      folder_id: image.folder_id,
      status: image.status,
      sort_order: image.sort_order,
      image_path: image.image_path,
      image_file_name: image.image_file_name,
      image_file: null,
    })
  }

  async function saveFolder(event) {
    event.preventDefault()
    if (!folderModal?.name?.trim()) return setError('Folder name is required.')
    setSaving(true)
    setError('')
    try {
      if (folderModal.mode === 'add') {
        await createGalleryFolder({ name: folderModal.name, status: folderModal.status })
        showToast('Folder added successfully.')
      } else {
        await updateGalleryFolder({ id: folderModal.id, name: folderModal.name, status: folderModal.status })
        showToast('Folder updated successfully.')
      }
      setFolderModal(null)
      await loadGallery()
    } catch (err) {
      setError(err.message || 'Unable to save folder.')
    } finally {
      setSaving(false)
    }
  }

  async function saveImage(event) {
    event.preventDefault()
    setSaving(true)
    setError('')
    try {
      if (imageModal.mode === 'add') {
        if (!imageModal.images.length) throw new Error('Select at least one image.')
        await uploadGalleryImages(imageModal)
        showToast('Images uploaded successfully.')
      } else {
        if (!imageModal.title.trim()) throw new Error('Image title is required.')
        await updateGalleryImage(imageModal)
        showToast('Image updated successfully.')
      }
      setImageModal(null)
      await loadGallery()
    } catch (err) {
      setError(err.message || 'Unable to save image.')
    } finally {
      setSaving(false)
    }
  }

  async function confirmDelete() {
    if (!deleteTarget) return
    setSaving(true)
    setError('')
    try {
      if (deleteTarget.type === 'folder') {
        await deleteGalleryFolder(deleteTarget.item.id)
        if (activeFolderId === deleteTarget.item.id) setActiveFolderId(null)
        showToast('Folder deleted successfully.')
      } else {
        await deleteGalleryImage(deleteTarget.item.id)
        showToast('Image deleted successfully.')
      }
      setDeleteTarget(null)
      await loadGallery()
    } catch (err) {
      setError(err.message || 'Delete failed.')
    } finally {
      setSaving(false)
    }
  }

  const stats = [
    { title: 'Gallery Folders', value: summary.folders, description: 'Managed categories', icon: Folder },
    { title: 'Total Images', value: summary.images, description: 'Across all folders', icon: Images },
    { title: 'Active Images', value: summary.activeImages, description: 'Visible on website', icon: Eye },
    { title: 'Inactive Images', value: summary.inactiveImages, description: 'Hidden items', icon: SlidersHorizontal },
  ]

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <PageHeader
          title={activeFolder ? `${activeFolder.name} Gallery` : 'Gallery Management'}
          description={activeFolder ? 'Manage images inside this gallery folder from database.' : 'Manage public website gallery folders and images from database.'}
        />
        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-row">
          {activeFolder ? (
            <Button type="button" variant="outline" onClick={() => setActiveFolderId(null)}>
              <ArrowLeft className="h-4 w-4" /> Back to Folders
            </Button>
          ) : (
            <Button type="button" variant="outline" onClick={openAddFolder}>
              <Folder className="h-4 w-4" /> Add Folder
            </Button>
          )}
          <Button type="button" onClick={() => openAddImages()} disabled={!folders.length}>
            <Plus className="h-4 w-4" /> Add Images
          </Button>
        </div>
      </div>

      {toast ? <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{toast}</div> : null}
      {error ? <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{error}</div> : null}

      {loading ? <Card><CardContent className="p-8 text-sm text-text-secondary">Loading gallery...</CardContent></Card> : null}

      {!loading && !activeFolder ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {stats.map((stat) => <StatCard key={stat.title} {...stat} />)}
          </div>

          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            {folderStats.map((folder) => (
              <Card
                key={folder.id}
                className="cursor-pointer overflow-hidden transition-all hover:-translate-y-0.5 hover:shadow-md sm:cursor-default"
                onClick={() => setActiveFolderId(folder.id)}
              >
                <div className="relative h-44 bg-slate-100">
                  <img src={folder.cover_image || fallbackImage} alt={folder.name} className="h-full w-full object-cover" />
                  <div className="absolute left-3 top-3"><Badge variant={statusVariant(folder.status)}>{folder.status}</Badge></div>
                </div>
                <CardContent className="p-5">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <h3 className="text-base font-semibold text-text-primary">{folder.name}</h3>
                      <p className="mt-1 text-sm text-text-secondary">{folder.image_count} images</p>
                    </div>
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">{folder.slug}</span>
                  </div>
                  <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div className="rounded-xl bg-slate-50 p-3"><p className="text-xs text-text-secondary">Active</p><p className="mt-1 font-semibold">{folder.active_count}</p></div>
                    <div className="rounded-xl bg-slate-50 p-3"><p className="text-xs text-text-secondary">Inactive</p><p className="mt-1 font-semibold">{folder.inactive_count}</p></div>
                  </div>
                  <p className="mt-4 text-xs text-text-secondary">Last updated {formatDate(folder.updated_at)}</p>
                  <div className="mt-4 flex gap-2 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible">
                    <Button
                      type="button"
                      size="sm"
                      className="hidden shrink-0 sm:inline-flex"
                      onClick={(event) => {
                        event.stopPropagation()
                        setActiveFolderId(folder.id)
                      }}
                    >
                      <Eye className="h-4 w-4" /> Open
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      className="shrink-0"
                      onClick={(event) => {
                        event.stopPropagation()
                        openAddImages(folder.id)
                      }}
                    >
                      <Upload className="h-4 w-4" /> Upload
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      className="shrink-0"
                      onClick={(event) => {
                        event.stopPropagation()
                        openEditFolder(folder)
                      }}
                    >
                      <Pencil className="h-4 w-4" /> Edit
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="destructive"
                      className="shrink-0"
                      onClick={(event) => {
                        event.stopPropagation()
                        setDeleteTarget({ type: 'folder', item: folder })
                      }}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </>
      ) : null}

      {!loading && activeFolder ? (
        <>
          <Card>
            <CardContent className="p-4">
              <div className="grid gap-3 lg:grid-cols-[1fr_180px]">
                <div className="relative">
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <Input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} placeholder="Search image title..." className="pl-9" />
                </div>
                <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className="h-10 rounded-lg border border-border bg-white px-3 text-sm">
                  <option value="all">All Status</option>
                  {statuses.map((status) => <option key={status} value={status}>{titleCase(status)}</option>)}
                </select>
              </div>
            </CardContent>
          </Card>

          {filteredImages.length === 0 ? (
            <Card><CardContent className="p-8 text-center text-sm text-text-secondary">No images found in this folder.</CardContent></Card>
          ) : (
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {filteredImages.map((image) => (
                <Card key={image.id} className="overflow-hidden">
                  <div className="relative h-52 bg-slate-100">
                    <img src={image.image_path} alt={image.title} className="h-full w-full object-cover" />
                    <div className="absolute left-3 top-3"><Badge variant={statusVariant(image.status)}>{image.status}</Badge></div>
                  </div>
                  <CardContent className="p-5">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <h3 className="font-semibold text-text-primary">{image.title}</h3>
                        <p className="mt-1 text-xs text-text-secondary">Sort #{image.sort_order}</p>
                      </div>
                      <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{image.folder_slug}</span>
                    </div>
                    <div className="mt-4 flex gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => openEditImage(image)}><Pencil className="h-4 w-4" /> Edit</Button>
                      <Button type="button" size="sm" variant="destructive" onClick={() => setDeleteTarget({ type: 'image', item: image })}><Trash2 className="h-4 w-4" /> Delete</Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </>
      ) : null}

      {folderModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" onClick={() => setFolderModal(null)}>
          <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-start justify-between gap-4 border-b border-border p-6">
              <div><h2 className="text-xl font-bold">{folderModal.mode === 'add' ? 'Add Gallery Folder' : 'Edit Gallery Folder'}</h2><p className="mt-1 text-sm text-text-secondary">Saved directly to database.</p></div>
              <button type="button" onClick={() => setFolderModal(null)} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={saveFolder} className="space-y-4 p-6">
              <div className="space-y-2"><Label>Folder name</Label><Input value={folderModal.name} onChange={(e) => setFolderModal((prev) => ({ ...prev, name: e.target.value }))} placeholder="Rooms" /></div>
              <div className="space-y-2"><Label>Status</Label><select value={folderModal.status} onChange={(e) => setFolderModal((prev) => ({ ...prev, status: e.target.value }))} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm">{statuses.map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}</select></div>
              <div className="flex justify-end gap-3 border-t border-border pt-5"><Button type="button" variant="outline" onClick={() => setFolderModal(null)}>Cancel</Button><Button type="submit" disabled={saving}>{saving ? 'Saving...' : 'Save Folder'}</Button></div>
            </form>
          </div>
        </div>
      ) : null}

      {imageModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" onClick={() => setImageModal(null)}>
          <div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-start justify-between gap-4 border-b border-border p-6">
              <div><h2 className="text-xl font-bold">{imageModal.mode === 'add' ? 'Upload Gallery Images' : 'Edit Gallery Image'}</h2><p className="mt-1 text-sm text-text-secondary">Images are stored in api/uploads/gallery/.</p></div>
              <button type="button" onClick={() => setImageModal(null)} className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={saveImage} className="space-y-5 p-6">
              <div className="grid gap-4 md:grid-cols-2">
                {imageModal.mode === 'edit' ? <div className="space-y-2"><Label>Image title</Label><Input value={imageModal.title} onChange={(e) => setImageModal((prev) => ({ ...prev, title: e.target.value }))} /></div> : null}
                <div className="space-y-2"><Label>Folder</Label><select value={imageModal.folder_id} onChange={(e) => setImageModal((prev) => ({ ...prev, folder_id: Number(e.target.value) }))} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm">{folders.map((folder) => <option key={folder.id} value={folder.id}>{folder.name}</option>)}</select></div>
                <div className="space-y-2"><Label>Status</Label><select value={imageModal.status} onChange={(e) => setImageModal((prev) => ({ ...prev, status: e.target.value }))} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm">{statuses.map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}</select></div>
                <div className="space-y-2"><Label>{imageModal.mode === 'add' ? 'Starting sort order' : 'Sort order'}</Label><Input type="number" min="1" value={imageModal.sort_order} onChange={(e) => setImageModal((prev) => ({ ...prev, sort_order: Number(e.target.value) }))} /></div>
              </div>

              {imageModal.mode === 'add' ? (
                <div className="space-y-3">
                  <Label>Images</Label>
                  <label className="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-border bg-slate-50 px-4 py-6 text-center hover:bg-blue-50/60">
                    <Upload className="h-7 w-7 text-primary-600" />
                    <span className="mt-2 text-sm font-semibold">Choose image files</span>
                    <span className="mt-1 text-xs text-text-secondary">JPG, PNG, WEBP. Max 8MB each.</span>
                    <input type="file" accept="image/*" multiple className="hidden" onChange={(event) => {
                      const files = Array.from(event.target.files || [])
                      const nextImages = files.filter((file) => file.type.startsWith('image/')).map((file) => ({ file, title: generateTitleFromFileName(file.name), preview: URL.createObjectURL(file) }))
                      setImageModal((prev) => ({ ...prev, images: [...prev.images, ...nextImages] }))
                      event.target.value = ''
                    }} />
                  </label>
                  {imageModal.images.length ? <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{imageModal.images.map((image, index) => <div key={`${image.file.name}-${index}`} className="overflow-hidden rounded-xl border border-border"><img src={image.preview} alt={image.title} className="h-28 w-full object-cover" /><div className="p-3"><Input value={image.title} onChange={(e) => setImageModal((prev) => ({ ...prev, images: prev.images.map((item, itemIndex) => itemIndex === index ? { ...item, title: e.target.value } : item) }))} /></div></div>)}</div> : null}
                </div>
              ) : (
                <div className="space-y-3">
                  <Label>Replace image optional</Label>
                  <div className="flex gap-3 rounded-xl border border-border bg-white p-3">
                    <img src={imageModal.image_file ? URL.createObjectURL(imageModal.image_file) : imageModal.image_path} alt="Selected gallery" className="h-24 w-32 rounded-lg object-cover" />
                    <div className="flex-1"><p className="text-sm font-semibold">{imageModal.image_file_name || 'Existing image'}</p><Input className="mt-3" type="file" accept="image/*" onChange={(event) => setImageModal((prev) => ({ ...prev, image_file: event.target.files?.[0] || null, image_file_name: event.target.files?.[0]?.name || prev.image_file_name }))} /></div>
                  </div>
                </div>
              )}

              <div className="flex justify-end gap-3 border-t border-border pt-5"><Button type="button" variant="outline" onClick={() => setImageModal(null)}>Cancel</Button><Button type="submit" disabled={saving}>{saving ? 'Saving...' : imageModal.mode === 'add' ? 'Upload Images' : 'Save Changes'}</Button></div>
            </form>
          </div>
        </div>
      ) : null}

      {deleteTarget ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" onClick={() => setDeleteTarget(null)}>
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" onClick={(event) => event.stopPropagation()}>
            <h2 className="text-lg font-bold">Delete {deleteTarget.type === 'folder' ? 'Folder' : 'Image'}</h2>
            <p className="mt-2 text-sm text-text-secondary">This will delete it from database{deleteTarget.type === 'folder' ? ' and remove images inside that folder' : ' and remove the uploaded file'}.</p>
            <div className="mt-6 flex justify-end gap-3"><Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>Cancel</Button><Button type="button" variant="destructive" onClick={confirmDelete} disabled={saving}>{saving ? 'Deleting...' : 'Delete'}</Button></div>
          </div>
        </div>
      ) : null}
    </div>
  )
}
