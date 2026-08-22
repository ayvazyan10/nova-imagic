<template>
  <PanelItem :index="index" :field="field">
    <template #value>
      <div v-if="items.length" class="imagic-detail-gallery">
        <figure v-for="item in items" :key="item.key" class="imagic-detail-image">
          <a :href="item.url" target="_blank" rel="noopener noreferrer" :aria-label="__('Open image') + ' ' + item.name">
            <img :src="item.thumbnailUrl || item.url" :alt="item.name" loading="lazy" width="220" height="170" />
          </a>
          <figcaption>
            <span :title="item.name">{{ item.name }}</span>
            <a v-if="field.downloadable" :href="downloadUrl(item)" :download="item.name" :aria-label="__('Download') + ' ' + item.name"><ImagicIcon name="download" /></a>
          </figcaption>
        </figure>
      </div>
      <span v-else>&mdash;</span>
    </template>
  </PanelItem>
</template>

<script>
import ImagicIcon from './ImagicIcon'
import { normalizeValue } from '../media'

export default {
  components: { ImagicIcon },
  props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],
  computed: { items() { return normalizeValue(this.field.value, this.field) } },
  methods: {
    downloadUrl(item) {
      if (this.items.length > 1 || item.raw?.id) return item.url
      return `/nova-api/${encodeURIComponent(this.resourceName)}/${encodeURIComponent(this.resourceId)}/download/${encodeURIComponent(this.field.attribute)}`
    },
  },
}
</script>
