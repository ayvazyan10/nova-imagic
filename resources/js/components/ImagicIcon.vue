<template>
  <svg
    aria-hidden="true"
    class="imagic-icon"
    fill="none"
    viewBox="0 0 24 24"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <path v-if="name === 'upload'" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v5h14v-5" />
    <path v-else-if="name === 'image'" d="M4 5h16v14H4zM4 16l4.5-4.5 3.5 3 2.5-2.5 5.5 5M15.5 9h.01" />
    <path v-else-if="name === 'trash'" d="M4.5 7h15M9 7V4.5h6V7m2.5 0-.75 13h-9.5L6.5 7M10 11v5m4-5v5" />
    <path v-else-if="name === 'crop'" d="M7 3v14h14M3 7h14v14" />
    <path v-else-if="name === 'grip'" d="M9 5h.01M15 5h.01M9 12h.01M15 12h.01M9 19h.01M15 19h.01" stroke-width="3" />
    <path v-else-if="name === 'search'" d="m20 20-4.6-4.6m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
    <path v-else-if="name === 'folder'" d="M3 6.5h7l2 2h9v10H3z" />
    <path v-else-if="name === 'grid'" d="M4 4h6v6H4zm10 0h6v6h-6zM4 14h6v6H4zm10 0h6v6h-6z" />
    <path v-else-if="name === 'list'" d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01" />
    <path v-else-if="name === 'copy'" d="M9 8h11v12H9zM4 4h11v4M4 4v12h5" />
    <path v-else-if="name === 'edit'" d="m4 20 4.5-1 10-10-3.5-3.5-10 10L4 20Zm9-12.5 3.5 3.5" />
    <path v-else-if="name === 'move'" d="M12 3v18m0-18-3 3m3-3 3 3m-3 15-3-3m3 3 3-3M3 12h18M3 12l3-3m-3 3 3 3m15-3-3-3m3 3-3 3" />
    <path v-else-if="name === 'check'" d="m5 12 4 4L19 6" />
    <path v-else-if="name === 'close'" d="M6 6l12 12M18 6 6 18" />
    <path v-else-if="name === 'chevron-left'" d="m15 5-7 7 7 7" />
    <path v-else-if="name === 'chevron-right'" d="m9 5 7 7-7 7" />
    <path v-else-if="name === 'up'" d="m6 15 6-6 6 6" />
    <path v-else-if="name === 'down'" d="m6 9 6 6 6-6" />
    <path v-else-if="name === 'download'" d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14" />
    <path v-else d="M12 4v16M4 12h16" />
  </svg>
</template>

<script>
export default { props: { name: { type: String, required: true } } }
</script>
