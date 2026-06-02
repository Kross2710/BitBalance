<script setup>
import { RouterView } from 'vue-router';
</script>

<template>
  <!-- The whole app lives under one RouterView. Switching routes swaps the view
       in place (no browser reload), with a small fade transition. -->
  <RouterView v-slot="{ Component }">
    <Transition name="fade" mode="out-in">
      <component :is="Component" />
    </Transition>
  </RouterView>
</template>
