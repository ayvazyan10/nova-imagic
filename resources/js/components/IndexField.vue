<template>
  <div v-if="items.length" class="imagic-index-preview" :title="items.map(item => item.name).join(', ')">
    <span v-for="(item, index) in visibleItems" :key="item.key" class="imagic-index-preview__image" :style="{ zIndex: visibleItems.length - index }">
      <img :src="item.thumbnailUrl || item.url" :alt="item.name" loading="lazy" width="48" height="48" />
    </span>
    <span v-if="items.length > visibleItems.length" class="imagic-index-preview__count">+{{ items.length - visibleItems.length }}</span>
  </div>
  <span v-else aria-label="No image">&mdash;</span>
</template>

<script>
import { normalizeValue } from '../media'

export default {
  props: ['resourceName', 'field'],
  computed: {
    items() { return normalizeValue(this.field.displayedAs || this.field.value, this.field) },
    visibleItems() { return this.items.slice(0, 3) },
  },
}
</script>
