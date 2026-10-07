<script setup lang="ts">
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="h-full" @contextmenu.prevent>
    <RouterView />
  </div>
</template>
