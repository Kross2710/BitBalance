<script setup>
import { RouterView } from 'vue-router';
import LoadingBar from './components/LoadingBar.vue';
</script>

<template>
  <!-- Global top progress bar — reflects route navigation + foreground API calls. -->
  <LoadingBar />
  <!-- The whole app lives under one RouterView. Switching routes swaps the view
       in place (no browser reload), with a small fade transition. -->
  <RouterView v-slot="{ Component }">
    <Transition name="fade" mode="out-in">
      <component :is="Component" />
    </Transition>
  </RouterView>
</template>
