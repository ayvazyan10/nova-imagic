<template>
  <div class="imagic-library" :class="{ 'imagic-library--picker': picker }">
    <header class="imagic-library__header">
      <div>
        <p class="imagic-eyebrow">{{ __('Imagic') }}</p>
        <h1>{{ picker ? __('Choose from media') : __('Media library') }}</h1>
        <p class="imagic-library__summary">{{ __('Organize, find, and reuse every upload from one place.') }}</p>
      </div>
      <button v-if="picker" class="imagic-icon-button" type="button" :aria-label="__('Close')" @click="$emit('close')">
        <ImagicIcon name="close" />
      </button>
    </header>

    <div class="imagic-library__toolbar">
      <label class="imagic-search">
        <ImagicIcon name="search" />
        <span class="sr-only">{{ __('Search media') }}</span>
        <input v-model="searchInput" type="search" :placeholder="__('Search by filename…')" @input="searchLater" />
      </label>

      <label class="imagic-select-control">
        <span>{{ __('Type') }}</span>
        <select v-model="mimeType" @change="setPage(1)">
          <option value="">{{ __('All images') }}</option>
          <option value="image/jpeg">JPEG</option>
          <option value="image/png">PNG</option>
          <option value="image/webp">WebP</option>
          <option value="image/gif">GIF</option>
        </select>
      </label>

      <label class="imagic-select-control">
        <span>{{ __('Sort') }}</span>
        <select v-model="sortKey" @change="setPage(1)">
          <option value="created_at:desc">{{ __('Newest') }}</option>
          <option value="created_at:asc">{{ __('Oldest') }}</option>
          <option value="updated_at:desc">{{ __('Recently updated') }}</option>
          <option value="name:asc">{{ __('Name A–Z') }}</option>
          <option value="name:desc">{{ __('Name Z–A') }}</option>
          <option value="size:desc">{{ __('Largest') }}</option>
          <option value="size:asc">{{ __('Smallest') }}</option>
        </select>
      </label>

      <div class="imagic-view-toggle" role="group" :aria-label="__('View')">
        <button type="button" :aria-pressed="view === 'grid'" :title="__('Grid view')" @click="view = 'grid'"><ImagicIcon name="grid" /></button>
        <button type="button" :aria-pressed="view === 'list'" :title="__('List view')" @click="view = 'list'"><ImagicIcon name="list" /></button>
      </div>

      <label class="imagic-button imagic-button--primary imagic-upload-button" :class="{ 'is-disabled': uploading }">
        <ImagicIcon name="upload" />
        {{ uploading ? __('Uploading…') : __('Upload') }}
        <input type="file" multiple :accept="acceptedTypes" :disabled="uploading" @change="uploadFiles" />
      </label>
    </div>

    <div v-if="notice" class="imagic-notice" role="status">{{ notice }}</div>
    <div v-if="error" class="imagic-alert" role="alert">
      <span>{{ error }}</span>
      <button type="button" @click="load">{{ __('Retry') }}</button>
    </div>

    <div class="imagic-library__body">
      <aside class="imagic-folders" aria-label="Media folders">
        <div class="imagic-folders__heading">
          <h2>{{ __('Folders') }}</h2>
          <button class="imagic-icon-button imagic-icon-button--small" type="button" :title="__('New folder')" @click="openFolderDialog">
            <ImagicIcon name="plus" />
          </button>
        </div>
        <nav>
          <button :class="{ 'is-active': folderId === 'all' }" type="button" @click="chooseFolder('all')">
            <ImagicIcon name="image" /><span>{{ __('All media') }}</span><strong>{{ meta.total }}</strong>
          </button>
          <button :class="{ 'is-active': folderId === 'root' }" type="button" @click="chooseFolder('root')">
            <ImagicIcon name="folder" /><span>{{ __('Unfiled') }}</span>
          </button>
          <button v-for="folder in folders" :key="folder.id" :class="{ 'is-active': folderId === folder.id }" type="button" @click="chooseFolder(folder.id)">
            <ImagicIcon name="folder" /><span :title="folder.path || folder.name">{{ folder.path || folder.name }}</span>
          </button>
        </nav>
      </aside>

      <main class="imagic-assets" :aria-busy="loading">
        <div class="imagic-assets__context">
          <div class="imagic-assets__title">
            <h2>{{ currentFolderName }}</h2>
            <p>{{ meta.total }} {{ meta.total === 1 ? __('item') : __('items') }}</p>
            <span v-if="currentFolder" class="imagic-folder-actions">
              <button class="imagic-icon-button imagic-icon-button--small" type="button" :aria-label="__('Rename folder')" :title="__('Rename folder')" @click="openFolderRenameDialog"><ImagicIcon name="edit" /></button>
              <button class="imagic-icon-button imagic-icon-button--small" type="button" :aria-label="__('Delete folder')" :title="__('Delete folder')" @click="openFolderDeleteDialog"><ImagicIcon name="trash" /></button>
            </span>
          </div>
          <div v-if="selectedIds.length" class="imagic-selection-actions">
            <span>{{ selectedIds.length }} {{ __('selected') }}</span>
            <button class="imagic-button imagic-button--quiet" type="button" @click="openMoveDialog"><ImagicIcon name="move" />{{ __('Move') }}</button>
            <button class="imagic-button imagic-button--danger" type="button" @click="openBulkDelete"><ImagicIcon name="trash" />{{ __('Delete') }}</button>
          </div>
        </div>

        <div v-if="loading" class="imagic-skeleton-grid" aria-label="Loading media">
          <div v-for="index in 8" :key="index" class="imagic-skeleton"></div>
        </div>

        <div v-else-if="!items.length" class="imagic-empty">
          <span class="imagic-empty__art"><ImagicIcon :name="search ? 'search' : 'image'" /></span>
          <h3>{{ search ? __('No matching media') : __('This space is ready for images') }}</h3>
          <p>{{ search ? __('Try another filename or clear the filters.') : __('Upload images here, then reuse them across your Nova resources.') }}</p>
          <button v-if="search || mimeType" class="imagic-button imagic-button--quiet" type="button" @click="clearFilters">{{ __('Clear filters') }}</button>
        </div>

        <div v-else-if="view === 'grid'" class="imagic-media-grid">
          <article v-for="item in items" :key="item.id" class="imagic-media-card" :class="{ 'is-selected': isSelected(item) }">
            <button class="imagic-media-card__preview" type="button" :aria-label="selectionLabel(item)" :aria-pressed="isSelected(item)" @click="toggle(item)">
              <img :src="item.thumbnailUrl || item.url" :alt="item.name" loading="lazy" width="280" height="220" />
              <span class="imagic-media-card__check"><ImagicIcon name="check" /></span>
            </button>
            <div class="imagic-media-card__meta">
              <div><strong :title="item.name">{{ item.name }}</strong><span>{{ formatBytes(item.size) }}<template v-if="item.width"> · {{ item.width }}×{{ item.height }}</template></span></div>
              <button class="imagic-icon-button imagic-icon-button--small" type="button" :aria-label="__('More actions for') + ' ' + item.name" @click="toggleMenu(item.id)">•••</button>
            </div>
            <div v-if="menuId === item.id" class="imagic-item-menu">
              <button type="button" @click="copyUrl(item)"><ImagicIcon name="copy" />{{ __('Copy URL') }}</button>
              <button type="button" @click="openRenameDialog(item)"><ImagicIcon name="edit" />{{ __('Rename') }}</button>
              <button type="button" @click="openMoveDialog(item)"><ImagicIcon name="move" />{{ __('Move') }}</button>
              <button class="is-danger" type="button" @click="confirmSingleDelete(item)"><ImagicIcon name="trash" />{{ __('Delete') }}</button>
            </div>
          </article>
        </div>

        <div v-else class="imagic-media-list">
          <div class="imagic-media-list__head" aria-hidden="true"><span></span><span>{{ __('File') }}</span><span>{{ __('Type') }}</span><span>{{ __('Size') }}</span><span>{{ __('Updated') }}</span><span></span></div>
          <article v-for="item in items" :key="item.id" :class="{ 'is-selected': isSelected(item) }">
            <input type="checkbox" :checked="isSelected(item)" :aria-label="selectionLabel(item)" @change="toggle(item)" />
            <button class="imagic-media-list__name" type="button" @click="toggle(item)"><img :src="item.thumbnailUrl || item.url" alt="" loading="lazy" width="52" height="52" /><strong>{{ item.name }}</strong></button>
            <span>{{ item.extension?.toUpperCase() || '—' }}</span><span>{{ formatBytes(item.size) }}</span><span>{{ formatDate(item.updatedAt) }}</span>
            <button class="imagic-icon-button imagic-icon-button--small" type="button" @click="toggleMenu(item.id)">•••</button>
            <div v-if="menuId === item.id" class="imagic-item-menu imagic-item-menu--list">
              <button type="button" @click="copyUrl(item)"><ImagicIcon name="copy" />{{ __('Copy URL') }}</button>
              <button type="button" @click="openRenameDialog(item)"><ImagicIcon name="edit" />{{ __('Rename') }}</button>
              <button type="button" @click="openMoveDialog(item)"><ImagicIcon name="move" />{{ __('Move') }}</button>
              <button class="is-danger" type="button" @click="confirmSingleDelete(item)"><ImagicIcon name="trash" />{{ __('Delete') }}</button>
            </div>
          </article>
        </div>

        <footer v-if="meta.lastPage > 1" class="imagic-pagination">
          <button class="imagic-icon-button" type="button" :disabled="meta.currentPage <= 1" :aria-label="__('Previous page')" @click="setPage(meta.currentPage - 1)"><ImagicIcon name="chevron-left" /></button>
          <span>{{ __('Page') }} {{ meta.currentPage }} {{ __('of') }} {{ meta.lastPage }}</span>
          <button class="imagic-icon-button" type="button" :disabled="meta.currentPage >= meta.lastPage" :aria-label="__('Next page')" @click="setPage(meta.currentPage + 1)"><ImagicIcon name="chevron-right" /></button>
        </footer>

        <footer v-if="picker" class="imagic-picker-footer">
          <span>{{ selectedIds.length ? selectedIds.length + ' ' + __('selected') : __('Select one or more images') }}</span>
          <div><button class="imagic-button imagic-button--quiet" type="button" @click="$emit('close')">{{ __('Cancel') }}</button><button class="imagic-button imagic-button--primary" type="button" :disabled="!selectedIds.length" @click="useSelected">{{ __('Use selected') }}</button></div>
        </footer>
      </main>
    </div>

    <Teleport to="body">
      <div v-if="activeDialog" class="imagic-dialog-layer" @mousedown.self="closeDialog">
        <section ref="activeDialog" class="imagic-dialog imagic-dialog--small" role="dialog" aria-modal="true" tabindex="-1" :aria-labelledby="'imagic-' + activeDialog + '-title'" @keydown.esc.prevent="closeDialog" @keydown.tab="trapDialogFocus">
          <header class="imagic-dialog__header"><h2 :id="'imagic-' + activeDialog + '-title'">{{ dialogTitle }}</h2><button class="imagic-icon-button" type="button" :aria-label="__('Close')" @click="closeDialog"><ImagicIcon name="close" /></button></header>
          <div class="imagic-dialog__content">
            <template v-if="['rename', 'folder', 'rename-folder'].includes(activeDialog)">
              <label class="imagic-stack-label"><span>{{ activeDialog.includes('folder') ? __('Folder name') : __('File name') }}</span><input ref="dialogInput" v-model.trim="dialogValue" type="text" maxlength="180" @keydown.enter.prevent="submitDialog" /></label>
            </template>
            <template v-else-if="activeDialog === 'move'">
              <label class="imagic-stack-label"><span>{{ __('Destination') }}</span><select v-model="dialogValue"><option value="root">{{ __('Unfiled') }}</option><option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.path || folder.name }}</option></select></label>
            </template>
            <template v-else-if="activeDialog === 'delete' || activeDialog === 'delete-folder'">
              <p>{{ activeDialog === 'delete-folder' ? __('Delete this folder? Only empty folders can be deleted; its name cannot be recovered.') : __('Delete the selected media permanently? Existing resource references may stop displaying.') }}</p>
            </template>
            <p v-if="dialogError" class="imagic-form-error" role="alert">{{ dialogError }}</p>
          </div>
          <footer class="imagic-dialog__footer"><button class="imagic-button imagic-button--quiet" type="button" @click="closeDialog">{{ __('Cancel') }}</button><button class="imagic-button" :class="activeDialog.includes('delete') ? 'imagic-button--danger' : 'imagic-button--primary'" type="button" :disabled="dialogBusy || (!dialogValue && !activeDialog.includes('delete'))" @click="submitDialog">{{ dialogBusy ? __('Working…') : dialogSubmitLabel }}</button></footer>
        </section>
      </div>
    </Teleport>
  </div>
</template>

<script>
import ImagicIcon from './ImagicIcon'
import { apiMessage, debounce, formatBytes, normalizeMediaItem } from '../media'

export default {
  components: { ImagicIcon },
  emits: ['close', 'select'],
  props: {
    picker: Boolean,
    multiple: { type: Boolean, default: true },
    apiBase: { type: String, default: '/nova-vendor/imagic' },
    acceptedTypes: { type: String, default: 'image/jpeg,image/png,image/webp,image/gif' },
    initialSelection: { type: Array, default: () => [] },
  },
  data: () => ({
    items: [], folders: [], selected: [], loading: true, uploading: false, error: '', notice: '', menuId: null, requestId: 0,
    search: '', searchInput: '', mimeType: '', sortKey: 'created_at:desc', folderId: 'all', page: 1, view: 'grid',
    meta: { currentPage: 1, lastPage: 1, perPage: 24, total: 0 },
    activeDialog: '', dialogValue: '', dialogItem: null, dialogBusy: false, dialogError: '', deleteIds: [],
  }),
  computed: {
    selectedIds() { return this.selected.map(item => item.id) },
    currentFolderName() {
      if (this.folderId === 'all') return this.__('All media')
      if (this.folderId === 'root') return this.__('Unfiled')
      return this.folders.find(folder => folder.id === this.folderId)?.path || this.__('Folder')
    },
    currentFolder() { return this.folders.find(folder => folder.id === this.folderId) || null },
    dialogTitle() {
      return { rename: this.__('Rename media'), move: this.__('Move media'), folder: this.__('Create folder'), delete: this.__('Delete media'), 'rename-folder': this.__('Rename folder'), 'delete-folder': this.__('Delete folder') }[this.activeDialog] || ''
    },
    dialogSubmitLabel() {
      return { rename: this.__('Save name'), move: this.__('Move'), folder: this.__('Create folder'), delete: this.__('Delete permanently'), 'rename-folder': this.__('Save name'), 'delete-folder': this.__('Delete folder') }[this.activeDialog] || this.__('Save')
    },
  },
  created() {
    if (!this.picker) {
      const query = new URLSearchParams(window.location.search)
      this.search = query.get('search') || ''
      this.searchInput = this.search
      this.folderId = query.get('folder') || 'all'
      this.mimeType = query.get('type') || ''
      this.page = Math.max(1, Number(query.get('page') || 1))
      this.view = query.get('view') === 'list' ? 'list' : 'grid'
      const sort = query.get('sort'); const direction = query.get('direction')
      if (['name', 'size', 'created_at', 'updated_at'].includes(sort) && ['asc', 'desc'].includes(direction)) this.sortKey = `${sort}:${direction}`
    }
    this.searchLater = debounce(() => { this.search = this.searchInput.trim(); this.setPage(1) }, 320)
    this.selected = [...this.initialSelection]
  },
  mounted() { this.load() },
  watch: {
    view() { const [sort, direction] = this.sortKey.split(':'); this.syncUrl(sort, direction) },
  },
  methods: {
    formatBytes,
    formatDate(value) { return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : '—' },
    async load() {
      const requestId = ++this.requestId
      this.loading = true; this.error = ''; this.menuId = null
      const [sort, direction] = this.sortKey.split(':')
      this.syncUrl(sort, direction)
      try {
        const response = await Nova.request().get(`${this.apiBase}/media`, { params: { search: this.search || undefined, folder_id: this.folderId, mime_type: this.mimeType || undefined, sort, direction, page: this.page, per_page: 24 } })
        if (requestId !== this.requestId) return
        const payload = response.data || {}
        this.items = (payload.data || []).map((item, index) => normalizeMediaItem(item, index))
        this.folders = payload.folders || this.folders
        this.meta = { currentPage: payload.meta?.current_page || 1, lastPage: payload.meta?.last_page || 1, perPage: payload.meta?.per_page || 24, total: payload.meta?.total || 0 }
      } catch (error) { if (requestId === this.requestId) this.error = apiMessage(error, this.__('The media library could not be loaded.')) }
      finally { if (requestId === this.requestId) this.loading = false }
    },
    syncUrl(sort, direction) {
      if (this.picker) return
      const url = new URL(window.location.href)
      const values = { search: this.search, folder: this.folderId === 'all' ? '' : this.folderId, type: this.mimeType, sort: sort === 'created_at' ? '' : sort, direction: sort === 'created_at' && direction === 'desc' ? '' : direction, page: this.page > 1 ? this.page : '', view: this.view === 'grid' ? '' : this.view }
      Object.entries(values).forEach(([key, value]) => value ? url.searchParams.set(key, value) : url.searchParams.delete(key))
      window.history.replaceState(window.history.state, '', url)
    },
    chooseFolder(id) { this.folderId = id; this.setPage(1) },
    setPage(page) { this.page = Math.max(1, page); this.load() },
    clearFilters() { this.search = ''; this.searchInput = ''; this.mimeType = ''; this.setPage(1) },
    isSelected(item) { return this.selectedIds.includes(item.id) },
    selectionLabel(item) { return `${this.isSelected(item) ? this.__('Deselect') : this.__('Select')} ${item.name}` },
    toggle(item) {
      if (this.isSelected(item)) this.selected = this.selected.filter(selected => selected.id !== item.id)
      else this.selected = this.multiple ? [...this.selected, item] : [item]
    },
    useSelected() { this.$emit('select', this.selected) },
    toggleMenu(id) { this.menuId = this.menuId === id ? null : id },
    async uploadFiles(event) {
      const files = Array.from(event.target.files || [])
      event.target.value = ''
      if (!files.length) return
      this.uploading = true; this.error = ''
      const data = new FormData()
      files.forEach(file => data.append('files[]', file, file.name))
      if (!['all', 'root'].includes(this.folderId)) data.append('folder_id', this.folderId)
      try { await Nova.request().post(`${this.apiBase}/media`, data); this.notice = this.__('Upload complete.'); await this.load() }
      catch (error) { this.error = apiMessage(error, this.__('Upload failed.')) }
      finally { this.uploading = false; window.setTimeout(() => { this.notice = '' }, 3500) }
    },
    async copyUrl(item) {
      this.menuId = null
      try {
        if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(item.url)
        else { const input = document.createElement('textarea'); input.value = item.url; document.body.appendChild(input); input.select(); document.execCommand('copy'); input.remove() }
        this.notice = this.__('URL copied to clipboard.')
      } catch (_) { this.error = this.__('The URL could not be copied.') }
      window.setTimeout(() => { this.notice = '' }, 2500)
    },
    openRenameDialog(item) { this.menuId = null; this.activeDialog = 'rename'; this.dialogItem = item; this.dialogValue = item.name; this.focusDialog() },
    openMoveDialog(item = null) { this.menuId = null; this.activeDialog = 'move'; this.dialogItem = item; this.dialogValue = item?.folderId || 'root'; this.focusDialog() },
    openFolderDialog() { this.activeDialog = 'folder'; this.dialogValue = ''; this.dialogItem = null; this.focusDialog() },
    openFolderRenameDialog() { this.activeDialog = 'rename-folder'; this.dialogItem = this.currentFolder; this.dialogValue = this.currentFolder.name; this.focusDialog() },
    openFolderDeleteDialog() { this.activeDialog = 'delete-folder'; this.dialogItem = this.currentFolder; this.dialogValue = ''; this.dialogError = ''; this.focusDialog() },
    confirmSingleDelete(item) { this.menuId = null; this.deleteIds = [item.id]; this.activeDialog = 'delete'; this.focusDialog() },
    openBulkDelete() { this.deleteIds = [...this.selectedIds]; this.activeDialog = 'delete'; this.focusDialog() },
    closeDialog(force = false) { if (this.dialogBusy && !force) return; this.activeDialog = ''; this.dialogValue = ''; this.dialogItem = null; this.dialogError = ''; this.deleteIds = [] },
    focusDialog() { this.$nextTick(() => (this.$refs.dialogInput || this.$refs.activeDialog)?.focus()) },
    trapDialogFocus(event) {
      const controls = Array.from(this.$refs.activeDialog?.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), [tabindex]:not([tabindex="-1"])') || [])
      if (!controls.length) return
      const first = controls[0]; const last = controls[controls.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    },
    async submitDialog() {
      this.dialogBusy = true; this.dialogError = ''
      try {
        if (this.activeDialog === 'rename') await Nova.request().patch(`${this.apiBase}/media/${this.dialogItem.id}`, { name: this.dialogValue })
        if (this.activeDialog === 'folder') await Nova.request().post(`${this.apiBase}/folders`, { name: this.dialogValue, parent_id: ['all', 'root'].includes(this.folderId) ? null : this.folderId })
        if (this.activeDialog === 'rename-folder') await Nova.request().patch(`${this.apiBase}/folders/${this.dialogItem.id}`, { name: this.dialogValue })
        if (this.activeDialog === 'move') {
          const ids = this.dialogItem ? [this.dialogItem.id] : this.selectedIds
          await Promise.all(ids.map(id => Nova.request().patch(`${this.apiBase}/media/${id}`, { folder_id: this.dialogValue === 'root' ? null : this.dialogValue })))
        }
        if (this.activeDialog === 'delete') {
          const ids = this.deleteIds.length ? this.deleteIds : this.selectedIds
          if (ids.length === 1) await Nova.request().delete(`${this.apiBase}/media/${ids[0]}`)
          else await Nova.request().post(`${this.apiBase}/media/bulk-delete`, { ids })
          this.selected = this.selected.filter(item => !ids.includes(item.id))
        }
        if (this.activeDialog === 'delete-folder') {
          await Nova.request().delete(`${this.apiBase}/folders/${this.dialogItem.id}`)
          this.folderId = 'all'
        }
        this.dialogBusy = false; this.closeDialog(true); await this.load()
      } catch (error) { this.dialogError = apiMessage(error) }
      finally { this.dialogBusy = false }
    },
  },
}
</script>
