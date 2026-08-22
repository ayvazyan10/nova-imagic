<template>
  <DefaultField
    :field="currentField"
    :label-for="labelFor"
    :errors="errors"
    :show-help-text="!isReadonly && showHelpText"
    :full-width-content="fullWidthContent"
  >
    <template #field>
      <div class="imagic-field" :class="{ 'has-error': hasAnyError }">
        <div v-if="items.length" ref="imageList" class="imagic-field__grid" role="list" :aria-label="__('Selected images')">
          <article v-for="item in items" :key="item.key" :data-key="item.key" class="imagic-selection-card" role="listitem">
            <div class="imagic-selection-card__preview">
              <img :src="item.thumbnailUrl || item.url" :alt="item.name" width="240" height="180" />
              <span v-if="item.kind === 'upload'" class="imagic-selection-card__badge">{{ __('New') }}</span>
              <div v-if="!currentlyIsReadonly" class="imagic-selection-card__actions">
                <button v-if="canCrop(item)" class="imagic-icon-button imagic-icon-button--light" type="button" :aria-label="__('Crop') + ' ' + item.name" @click="startCrop(item)"><ImagicIcon name="crop" /></button>
                <button class="imagic-icon-button imagic-icon-button--light" type="button" :aria-label="__('Remove') + ' ' + item.name" @click="removeItem(item)"><ImagicIcon name="trash" /></button>
              </div>
            </div>
            <div class="imagic-selection-card__meta">
              <span v-if="field.multiple && !currentlyIsReadonly" class="imagic-drag-handle" :title="__('Drag to reorder')"><ImagicIcon name="grip" /></span>
              <div><strong :title="item.name">{{ item.name }}</strong><span>{{ formatBytes(item.size) || (item.kind === 'upload' ? __('Ready to upload') : __('Saved image')) }}</span></div>
              <span v-if="field.multiple && !currentlyIsReadonly" class="imagic-order-buttons">
                <button type="button" :disabled="items.indexOf(item) === 0" :aria-label="__('Move earlier') + ' ' + item.name" @click="moveItem(item, -1)"><ImagicIcon name="up" /></button>
                <button type="button" :disabled="items.indexOf(item) === items.length - 1" :aria-label="__('Move later') + ' ' + item.name" @click="moveItem(item, 1)"><ImagicIcon name="down" /></button>
              </span>
            </div>
          </article>
        </div>

        <div v-else-if="currentlyIsReadonly" class="imagic-inline-empty">&mdash;</div>

        <div v-if="!currentlyIsReadonly" class="imagic-field__actions">
          <label
            class="imagic-dropzone"
            :class="{ 'is-dragging': dragging, 'is-disabled': atLimit }"
            :for="labelFor"
            @dragenter.prevent="dragging = true"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="handleDrop"
          >
            <input
              :id="labelFor"
              ref="fileInput"
              class="sr-only"
              type="file"
              :accept="acceptedTypes"
              :multiple="Boolean(field.multiple)"
              :disabled="atLimit"
              :dusk="field.attribute"
              @change="handleInput"
            />
            <span class="imagic-dropzone__icon"><ImagicIcon name="upload" /></span>
            <span><strong>{{ field.multiple ? __('Drop images here or browse') : __('Drop an image here or browse') }}</strong><small>{{ uploadHint }}</small></span>
          </label>

          <button v-if="mediaLibraryEnabled" class="imagic-library-trigger" type="button" @click="libraryOpen = true">
            <span><ImagicIcon name="image" /></span>
            <span><strong>{{ __('Choose from media') }}</strong><small>{{ __('Reuse an image already uploaded') }}</small></span>
            <ImagicIcon name="chevron-right" />
          </button>
        </div>

        <p v-if="clientError" class="imagic-form-error" role="alert">{{ clientError }}</p>
        <p v-else-if="hasAnyError" class="imagic-form-error" role="alert">{{ firstAvailableError }}</p>

        <CropModal
          :show="Boolean(cropItem)"
          :file="cropItem?.file"
          :aspect-ratio="cropAspectRatio"
          :output-width="field.cropWidth || undefined"
          :output-height="field.cropHeight || undefined"
          @apply="applyCrop"
          @cancel="skipCrop"
        />

        <Teleport to="body">
          <div v-if="libraryOpen" class="imagic-dialog-layer imagic-dialog-layer--library" @mousedown.self="libraryOpen = false">
            <section ref="libraryDialog" class="imagic-dialog imagic-library-dialog" role="dialog" aria-modal="true" tabindex="-1" :aria-label="__('Choose from media')" @keydown.esc.prevent="libraryOpen = false" @keydown.tab="trapLibraryFocus">
              <MediaBrowser :picker="true" :multiple="Boolean(field.multiple)" :api-base="mediaApiBase" :accepted-types="acceptedTypes" @close="libraryOpen = false" @select="addFromLibrary" />
            </section>
          </div>
        </Teleport>
      </div>
    </template>
  </DefaultField>
</template>

<script>
import { DependentFormField, HandlesValidationErrors } from 'laravel-nova'
import Sortable from 'sortablejs'
import CropModal from './CropModal'
import ImagicIcon from './ImagicIcon'
import MediaBrowser from './MediaBrowser'
import { formatBytes, localMediaItem, normalizeMediaItem, normalizeValue, revokeLocalItem } from '../media'

export default {
  components: { CropModal, ImagicIcon, MediaBrowser },
  mixins: [HandlesValidationErrors, DependentFormField],
  props: ['resourceId', 'relatedResourceName', 'relatedResourceId', 'viaRelationship'],
  data: () => ({ items: [], dragging: false, clientError: '', sortable: null, libraryOpen: false, libraryPreviousFocus: null, cropItem: null, cropQueue: [] }),
  computed: {
    labelFor() {
      const relation = this.relatedResourceName ? `-${this.relatedResourceName}` : ''
      return `imagic-${this.resourceName}${relation}-${this.field.attribute}`
    },
    acceptedTypes() { return Array.isArray(this.field.acceptedTypes) ? this.field.acceptedTypes.join(',') : (this.field.acceptedTypes || 'image/jpeg,image/png,image/webp,image/gif') },
    maximumFiles() { return Number(this.field.maxFiles || (this.field.multiple ? 20 : 1)) },
    maximumBytes() { return Number(this.field.maxFileSize || this.field.maxFileSizeBytes || 10 * 1024 * 1024) },
    atLimit() { return this.items.length >= this.maximumFiles },
    uploadHint() {
      const size = formatBytes(this.maximumBytes)
      const limit = this.field.multiple ? `${this.maximumFiles} ${this.__('images maximum')}` : this.__('one image')
      return `${this.acceptedTypes.replaceAll('image/', '').toUpperCase()} · ${size} · ${limit}`
    },
    mediaLibraryEnabled() { return Boolean(this.field.mediaLibrary || this.field.mediaLibraryEnabled) },
    mediaApiBase() { return this.field.mediaApiBase || '/nova-vendor/imagic' },
    liveCropEnabled() { return Boolean(this.field.liveCrop || this.field.cropper) },
    cropAspectRatio() {
      const explicit = Number(this.field.cropAspectRatio)
      if (Number.isFinite(explicit) && explicit > 0) return explicit
      const width = Number(this.field.cropWidth); const height = Number(this.field.cropHeight)
      return width > 0 && height > 0 ? width / height : NaN
    },
    hasAnyError() { return Boolean(this.clientError || this.errors?.has?.(this.field.attribute)) },
    firstAvailableError() { return this.errors?.first?.(this.field.attribute) || '' },
  },
  watch: {
    'currentField.value'(value) {
      if (!this.items.some(item => item.kind === 'upload')) this.items = normalizeValue(value, this.currentField)
    },
    libraryOpen(value) {
      if (value) {
        this.libraryPreviousFocus = document.activeElement
        this.$nextTick(() => this.$refs.libraryDialog?.focus())
      } else {
        this.libraryPreviousFocus?.focus?.()
        this.libraryPreviousFocus = null
      }
    },
  },
  mounted() {
    this.items = normalizeValue(this.currentField.value, this.currentField)
    const fill = formData => this.fill(formData)
    this.field.fill = fill
    this.currentField.fill = fill
    this.$nextTick(this.createSortable)
  },
  updated() { this.$nextTick(this.createSortable) },
  beforeUnmount() {
    this.sortable?.destroy()
    this.items.forEach(revokeLocalItem)
  },
  methods: {
    formatBytes,
    createSortable() {
      if (!this.field.multiple || this.currentlyIsReadonly || !this.$refs.imageList || this.sortable) return
      this.sortable = Sortable.create(this.$refs.imageList, {
        animation: 150,
        handle: '.imagic-drag-handle',
        ghostClass: 'is-sorting',
        onEnd: event => {
          const moved = this.items.splice(event.oldIndex, 1)[0]
          this.items.splice(event.newIndex, 0, moved)
        },
      })
    },
    fill(formData) {
      const attribute = this.field.attribute
      const existing = this.items.filter(item => item.kind === 'existing').map(item => item.reference || item.path)
      const uploads = this.items.filter(item => item.kind === 'upload')
      formData.append(`${attribute}_existing`, JSON.stringify(existing))
      formData.append(`${attribute}_order`, JSON.stringify(this.items.map(item => item.kind === 'upload' ? 'upload' : 'existing')))
      if (this.field.multiple) uploads.forEach(item => formData.append(`${attribute}[]`, item.file, item.file.name))
      else if (uploads[0]) formData.append(attribute, uploads[0].file, uploads[0].file.name)
    },
    handleInput(event) {
      this.acceptFiles(Array.from(event.target.files || []))
      event.target.value = ''
    },
    handleDrop(event) {
      this.dragging = false
      if (!this.atLimit) this.acceptFiles(Array.from(event.dataTransfer?.files || []))
    },
    acceptFiles(files) {
      this.clientError = ''
      const allowed = this.acceptedTypes.split(',').map(type => type.trim()).filter(Boolean)
      const remaining = Math.max(0, this.maximumFiles - this.items.length)
      const accepted = files.filter(file => {
        const typeAllowed = allowed.some(type => type === 'image/*' ? file.type.startsWith('image/') : type === file.type || (type.startsWith('.') && file.name.toLowerCase().endsWith(type.toLowerCase())))
        if (!typeAllowed) { this.clientError = this.__('One or more files use an unsupported image type.'); return false }
        if (file.size > this.maximumBytes) { this.clientError = `${file.name} ${this.__('is larger than')} ${formatBytes(this.maximumBytes)}.`; return false }
        return true
      }).slice(0, this.field.multiple ? remaining : 1)
      if (files.length > accepted.length && !this.clientError) this.clientError = this.__('Some files were skipped because the image limit was reached.')
      if (!accepted.length) return

      if (!this.field.multiple) this.items.forEach(revokeLocalItem), this.items = []
      const additions = accepted.map(localMediaItem)
      if (this.liveCropEnabled) { this.cropQueue.push(...additions); this.cropItem ||= this.cropQueue.shift() }
      else this.items.push(...additions)
    },
    removeItem(item) {
      revokeLocalItem(item)
      this.items = this.items.filter(candidate => candidate.key !== item.key)
    },
    moveItem(item, offset) {
      const index = this.items.findIndex(candidate => candidate.key === item.key)
      const target = index + offset
      if (index < 0 || target < 0 || target >= this.items.length) return
      this.items.splice(target, 0, this.items.splice(index, 1)[0])
    },
    canCrop(item) { return this.liveCropEnabled && item.kind === 'upload' && item.mimeType !== 'image/gif' && item.mimeType !== 'image/svg+xml' },
    startCrop(item) { this.cropItem = item },
    applyCrop(file) {
      const original = this.cropItem
      const replacement = localMediaItem(file)
      const index = this.items.findIndex(item => item.key === original.key)
      if (index >= 0) this.items.splice(index, 1, replacement)
      else this.items.push(replacement)
      revokeLocalItem(original)
      this.advanceCropQueue()
    },
    skipCrop() {
      if (!this.items.some(item => item.key === this.cropItem.key)) this.items.push(this.cropItem)
      this.advanceCropQueue()
    },
    advanceCropQueue() { this.cropItem = this.cropQueue.shift() || null },
    addFromLibrary(media) {
      const additions = media.map((item, index) => normalizeMediaItem(item.raw || item, index + this.items.length, this.currentField))
      const known = new Set(this.items.map(item => item.reference || item.path || item.key))
      const unique = additions.filter(item => !known.has(item.reference || item.path || item.key))
      if (this.field.multiple) {
        const remaining = Math.max(0, this.maximumFiles - this.items.length)
        this.items.push(...unique.slice(0, remaining))
        if (unique.length > remaining) this.clientError = this.__('Some images were skipped because the image limit was reached.')
      }
      else { this.items.forEach(revokeLocalItem); this.items = unique.slice(0, 1) }
      this.libraryOpen = false
    },
    trapLibraryFocus(event) {
      const controls = Array.from(this.$refs.libraryDialog?.querySelectorAll('button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), [tabindex]:not([tabindex="-1"])') || [])
      if (!controls.length) return
      const first = controls[0]; const last = controls[controls.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    },
  },
}
</script>
