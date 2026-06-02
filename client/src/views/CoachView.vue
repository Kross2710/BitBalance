<script setup>
// Coach route. For now it renders the AI Coach panel directly; this is where the
// Coach Hub segmented control ([AI Coach] [My Trainer]) will live in a later
// slice, swapping panels without touching the surrounding app shell.
import AiCoachPanel from '../components/AiCoachPanel.vue';
</script>

<template>
  <AiCoachPanel />
</template>
