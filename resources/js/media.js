export function parseValue(value) {
  if (value === null || value === undefined || value === '') return []

  if (Array.isArray(value)) return value

  if (typeof value === 'object') return [value]

  if (typeof value !== 'string') return []

  const trimmed = value.trim()

  if (!trimmed) return []

  try {
    const parsed = JSON.parse(trimmed)
    return Array.isArray(parsed) ? parsed : [parsed]
  } catch (_) {
    return [trimmed]
  }
}

export function publicUrl(value, fallback = '') {
  if (!value) return fallback

  if (/^(https?:)?\/\//i.test(value) || /^(blob:|data:)/i.test(value)) {
    return value
  }

  const base = window?.location?.origin || ''
  return `${base}/${String(value).replace(/^\/+/, '')}`
}

export function fileName(path, fallback = 'Image') {
  if (!path) return fallback

  try {
    const pathname = new URL(path, window.location.origin).pathname
    return decodeURIComponent(pathname.split('/').filter(Boolean).pop() || fallback)
  } catch (_) {
    return String(path).split('/').filter(Boolean).pop() || fallback
  }
}

export function normalizeMediaItem(item, index = 0, field = {}) {
  const source = typeof item === 'string' ? { path: item } : item || {}
  const legacyPath = source.path || source.value || source.url || source.original_url || ''
  const reference = source.reference || (source.id ? `media:${source.id}` : legacyPath)
  const unresolvedReference = typeof legacyPath === 'string' && legacyPath.startsWith('media:')
  const url = source.url || source.preview_url || source.thumbnail_url || (unresolvedReference ? '' : legacyPath)

  return {
    id: source.id ?? `existing-${index}-${reference}`,
    key: `existing:${source.id ?? reference}`,
    kind: 'existing',
    path: reference,
    reference,
    url: publicUrl(url, field.previewUrl || field.thumbnailUrl || ''),
    thumbnailUrl: publicUrl(source.thumbnail_url || source.thumbnailUrl || url),
    name: source.name || source.file_name || fileName(legacyPath || reference),
    mimeType: source.mime_type || source.mimeType || '',
    extension: source.extension || fileName(legacyPath).split('.').pop() || '',
    size: source.size || null,
    width: source.width || null,
    height: source.height || null,
    folder: source.folder || '',
    folderId: source.folder_id || source.folderId || null,
    createdAt: source.created_at || source.createdAt || null,
    raw: source,
  }
}

export function normalizeValue(value, field = {}) {
  return parseValue(value)
    .map((item, index) => normalizeMediaItem(item, index, field))
    .filter(item => item.reference || item.url)
}

export function localMediaItem(file) {
  const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`

  return {
    id,
    key: `upload:${id}`,
    kind: 'upload',
    file,
    path: '',
    url: URL.createObjectURL(file),
    thumbnailUrl: '',
    name: file.name,
    mimeType: file.type,
    size: file.size,
  }
}

export function revokeLocalItem(item) {
  if (item?.kind === 'upload' && item.url?.startsWith('blob:')) {
    URL.revokeObjectURL(item.url)
  }
}

export function formatBytes(value) {
  const bytes = Number(value)
  if (!Number.isFinite(bytes) || bytes < 0) return ''
  if (bytes === 0) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const amount = bytes / Math.pow(1024, index)

  return `${amount >= 10 || index === 0 ? amount.toFixed(0) : amount.toFixed(1)} ${units[index]}`
}

export function apiMessage(error, fallback = 'Something went wrong. Please try again.') {
  const data = error?.response?.data
  const errors = data?.errors

  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat()[0]
    if (first) return first
  }

  return data?.message || error?.message || fallback
}

export function debounce(callback, wait = 300) {
  let timer

  return (...args) => {
    window.clearTimeout(timer)
    timer = window.setTimeout(() => callback(...args), wait)
  }
}
