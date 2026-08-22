<template>
  <Teleport to="body">
    <div v-if="show" class="imagic-dialog-layer" role="presentation" @mousedown.self="cancel">
      <section
        ref="dialog"
        aria-labelledby="imagic-crop-title"
        aria-modal="true"
        class="imagic-dialog imagic-crop-dialog"
        role="dialog"
        tabindex="-1"
        @keydown.esc.prevent="cancel"
        @keydown.tab="trapFocus"
      >
        <header class="imagic-dialog__header">
          <div>
            <p class="imagic-eyebrow">{{ __('Image editor') }}</p>
            <h2 id="imagic-crop-title">{{ __('Crop image') }}</h2>
          </div>
          <button class="imagic-icon-button" type="button" :aria-label="__('Close')" @click="cancel">
            <ImagicIcon name="close" />
          </button>
        </header>

        <div class="imagic-crop-stage">
          <img ref="image" :src="source" :alt="file?.name || __('Image to crop')" />
        </div>

        <div class="imagic-crop-controls" aria-label="Crop controls">
          <button class="imagic-button imagic-button--quiet" type="button" @click="zoom(-0.1)">− {{ __('Zoom') }}</button>
          <button class="imagic-button imagic-button--quiet" type="button" @click="reset">{{ __('Reset') }}</button>
          <button class="imagic-button imagic-button--quiet" type="button" @click="zoom(0.1)">+ {{ __('Zoom') }}</button>
        </div>

        <footer class="imagic-dialog__footer">
          <button class="imagic-button imagic-button--quiet" type="button" @click="cancel">{{ __('Skip crop') }}</button>
          <button class="imagic-button imagic-button--primary" type="button" :disabled="processing || !ready" @click="apply">
            <span v-if="processing" class="imagic-spinner" aria-hidden="true"></span>
            {{ processing ? __('Applying…') : __('Apply crop') }}
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<script>
import Cropper from 'cropperjs'
import ImagicIcon from './ImagicIcon'

export default {
  components: { ImagicIcon },
  emits: ['apply', 'cancel'],
  props: {
    show: Boolean,
    file: Object,
    aspectRatio: { type: Number, default: NaN },
    outputWidth: Number,
    outputHeight: Number,
  },
  data: () => ({ cropper: null, source: '', processing: false, ready: false, previousFocus: null }),
  watch: {
    show(value) {
      value ? this.open() : this.destroy()
    },
    file(value, previous) {
      if (this.show && previous && value !== previous) {
        this.destroy()
        this.$nextTick(this.open)
      }
    },
  },
  beforeUnmount() {
    this.destroy()
  },
  methods: {
    open() {
      if (!this.file) return
      this.previousFocus = document.activeElement
      this.source = URL.createObjectURL(this.file)
      this.$nextTick(() => {
        this.cropper = new Cropper(this.$refs.image, {
          aspectRatio: Number.isFinite(this.aspectRatio) ? this.aspectRatio : NaN,
          autoCropArea: 0.92,
          background: false,
          responsive: true,
          viewMode: 1,
          ready: () => { this.ready = true },
        })
        this.$refs.dialog?.focus?.()
      })
    },
    destroy() {
      this.cropper?.destroy()
      this.cropper = null
      if (this.source?.startsWith('blob:')) URL.revokeObjectURL(this.source)
      this.source = ''
      this.processing = false
      this.ready = false
      this.previousFocus?.focus?.()
      this.previousFocus = null
    },
    zoom(amount) {
      this.cropper?.zoom(amount)
    },
    reset() {
      this.cropper?.reset()
    },
    cancel() {
      this.$emit('cancel')
    },
    apply() {
      if (!this.cropper || !this.ready || this.processing) return
      this.processing = true

      const options = {
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
      }
      if (this.outputWidth) options.width = this.outputWidth
      if (this.outputHeight) options.height = this.outputHeight

      const canvas = this.cropper.getCroppedCanvas(options)
      if (!canvas) {
        this.processing = false
        return
      }
      const type = this.file.type === 'image/png' ? 'image/png' : 'image/jpeg'
      canvas.toBlob(blob => {
        if (!blob) {
          this.processing = false
          return
        }
        const extension = type === 'image/png' ? 'png' : 'jpg'
        const base = this.file.name.replace(/\.[^.]+$/, '')
        this.$emit('apply', new File([blob], `${base}.${extension}`, { type, lastModified: Date.now() }))
      }, type, 0.92)
    },
    trapFocus(event) {
      const controls = Array.from(this.$refs.dialog?.querySelectorAll('button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), [tabindex]:not([tabindex="-1"])') || [])
      if (!controls.length) return
      const first = controls[0]; const last = controls[controls.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    },
  },
}
</script>
