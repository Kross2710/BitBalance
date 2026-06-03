<script setup>
// Coach Hub. A segmented control switches between the instant AI assistant and
// the (human) personal trainer surface, keeping them visually distinct but in
// one place — Profile-style, the bottom nav stays a single "Coach" tab.
//
// Panels use v-show (not v-if) so switching segments preserves each panel's
// state: the AI conversation thread stays put when you peek at My Trainer.
import { ref } from 'vue';
import AiCoachPanel from '../components/AiCoachPanel.vue';
import MyTrainerPanel from '../components/MyTrainerPanel.vue';

const tab = ref('ai'); // 'ai' | 'trainer'
</script>

<template>
  <div class="coach-hub">
    <div class="hub-tabs" role="tablist">
      <button class="tab" :class="{ on: tab === 'ai' }" role="tab" :aria-selected="tab === 'ai'" @click="tab = 'ai'">
        <i class="fa-solid fa-robot" /> {{ $t('coach.tab.ai') }}
      </button>
      <button class="tab" :class="{ on: tab === 'trainer' }" role="tab" :aria-selected="tab === 'trainer'" @click="tab = 'trainer'">
        <i class="fa-solid fa-user-tie" /> {{ $t('coach.tab.trainer') }}
      </button>
    </div>

    <div class="hub-panel">
      <AiCoachPanel v-show="tab === 'ai'" />
      <MyTrainerPanel v-show="tab === 'trainer'" />
    </div>
  </div>
</template>

<style scoped>
.coach-hub {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 60px);
}

/* Segmented control — mirrors FriendsView .tabs so the app's toggles match. */
.hub-tabs {
  flex: none;
  display: flex;
  gap: 6px;
  max-width: 1000px;
  width: 100%;
  margin: 0 auto;
  padding: 6px 16px 8px;
}
.tab {
  flex: 1;
  min-height: 44px;
  padding: 7px 6px;
  border-radius: 10px;
  background: var(--card);
  border: 1px solid var(--border);
  color: var(--muted);
  font-weight: 700;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
}
.tab.on { color: var(--accent); border-color: var(--accent); background: var(--inset); }

.hub-panel {
  flex: 1;
  min-height: 0;
}
/* Direct children (the panels) fill the panel area. */
.hub-panel > * { height: 100%; }

@media (max-width: 767px) {
  .coach-hub { height: calc(100vh - 130px); }
  .hub-tabs { padding: 6px 12px 8px; }
}
</style>
