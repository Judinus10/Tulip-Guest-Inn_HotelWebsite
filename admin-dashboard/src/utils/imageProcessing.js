const DEFAULT_OPTIONS = {
  maxDimension: 2560,
  quality: 0.85,
  maxFiles: 12,
}

const acceptedTypes = new Set(['image/jpeg', 'image/png', 'image/webp'])

function readImage(file) {
  return new Promise((resolve, reject) => {
    const objectUrl = URL.createObjectURL(file)
    const image = new Image()

    image.onload = () => {
      URL.revokeObjectURL(objectUrl)
      resolve(image)
    }

    image.onerror = () => {
      URL.revokeObjectURL(objectUrl)
      reject(new Error(`${file.name} is not a readable image.`))
    }

    image.src = objectUrl
  })
}

function optimizedFilename(filename) {
  const basename = String(filename || 'room-image')
    .replace(/\.[^/.]+$/, '')
    .replace(/[^a-z0-9_-]+/gi, '-')
    .replace(/^-+|-+$/g, '')

  return `${basename || 'room-image'}-optimized.jpg`
}

function canvasToBlob(canvas, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (blob) resolve(blob)
        else reject(new Error('The browser could not optimize this image.'))
      },
      'image/jpeg',
      quality
    )
  })
}

export async function optimizeImage(file, options = {}) {
  const settings = { ...DEFAULT_OPTIONS, ...options }

  if (!(file instanceof File)) {
    throw new Error('An invalid image was selected.')
  }

  if (!acceptedTypes.has(file.type)) {
    throw new Error(`${file.name} must be a JPEG, PNG, or WebP image.`)
  }

  const image = await readImage(file)
  const originalWidth = image.naturalWidth
  const originalHeight = image.naturalHeight

  if (!originalWidth || !originalHeight) {
    throw new Error(`${file.name} has invalid dimensions.`)
  }

  const scale = Math.min(1, settings.maxDimension / Math.max(originalWidth, originalHeight))
  const width = Math.max(1, Math.round(originalWidth * scale))
  const height = Math.max(1, Math.round(originalHeight * scale))
  const canvas = document.createElement('canvas')
  const context = canvas.getContext('2d', { alpha: false })

  if (!context) {
    throw new Error('Your browser does not support image optimization.')
  }

  canvas.width = width
  canvas.height = height

  // Room photos do not require transparency. A white background prevents
  // transparent PNG areas from becoming black when converted to JPEG.
  context.fillStyle = '#ffffff'
  context.fillRect(0, 0, width, height)
  context.imageSmoothingEnabled = true
  context.imageSmoothingQuality = 'high'
  context.drawImage(image, 0, 0, width, height)

  const blob = await canvasToBlob(canvas, settings.quality)

  return new File([blob], optimizedFilename(file.name), {
    type: 'image/jpeg',
    lastModified: Date.now(),
  })
}

export async function optimizeImages(files, options = {}) {
  const settings = { ...DEFAULT_OPTIONS, ...options }
  const selectedFiles = Array.from(files || [])

  if (selectedFiles.length > settings.maxFiles) {
    throw new Error(`Select no more than ${settings.maxFiles} images at once.`)
  }

  return Promise.all(selectedFiles.map((file) => optimizeImage(file, settings)))
}

export const optimizeRoomImage = optimizeImage
export const optimizeRoomImages = optimizeImages
