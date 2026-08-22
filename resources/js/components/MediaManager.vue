<template>
  <div class="imagic-page"><MediaBrowser :api-base="apiBase" /></div>
</template>

<script>
import MediaBrowser from './MediaBrowser'
export default {
  components: { MediaBrowser },
  props: { apiBase: { type: String, default: '/nova-vendor/imagic' } },
}
</script>
