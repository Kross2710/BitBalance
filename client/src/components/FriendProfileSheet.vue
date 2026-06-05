<script setup>
// Public profile peek — opened by tapping a row on the /friends page. Shows only
// non-sensitive "flex" data (level, XP, streak, achievement badges, favorite
// food, bio); calories/macros/weight are never returned by the API. Gated by the
// target's profile_visibility: a locked profile renders just the identity + an
// Add CTA. Wraps the shared BottomSheet. Add is handled here; Remove is delegated
// to the parent (which owns the ConfirmDialog) to avoid stacking it under the
// sheet.
import { ref, watch, computed } from 'vue';
import { api } from '../lib/api.js';
import { t } from '../i18n/index.js';
import BottomSheet from './BottomSheet.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  userId: { type: [Number, null], default: null },
});
const emit = defineEmits(['close', 'changed', 'remove']);

const loading = ref(false);
const error = ref('');
const profile = ref(null);
const acting = ref(false);

// Mirror of AchievementCard.vue's tone -> {color, soft bg} map (kept flat/dark).
const TONES = {
  primary: { c: '#4ade80', bg: 'rgba(74, 222, 128, 0.14)' },
  secondary: { c: '#60a5fa', bg: 'rgba(96, 165, 250, 0.14)' },
  accent: { c: '#a78bfa', bg: 'rgba(167, 139, 250, 0.14)' },
  success: { c: '#34d399', bg: 'rgba(52, 211, 153, 0.14)' },
  warning: { c: '#fbbf24', bg: 'rgba(251, 191, 36, 0.14)' },
};
const toneOf = (tone) => TONES[tone] || TONES.primary;
const initials = (name) => (name || '?').slice(0, 1).toUpperCase();

async function load() {
  if (!props.userId) return;
  loading.value = true;
  error.value = '';
  profile.value = null;
  try {
    profile.value = await api.get(`/api/social/profile/${props.userId}`);
  } catch (e) {
    error.value = e.message || t('friends.profile.error');
  } finally {
    loading.value = false;
  }
}

// Refetch whenever the sheet opens on a (new) user.
watch(
  () => [props.open, props.userId],
  ([open]) => {
    if (open && props.userId) load();
  },
  { immediate: true }
);

async function add() {
  if (acting.value || !profile.value) return;
  acting.value = true;
  try {
    await api.post('/api/social/send', { target_id: profile.value.user_id });
    emit('changed'); // parent re-polls its lists / leaderboard
    await load(); // reflect the new pending_out relationship in the sheet
  } catch (e) {
    error.value = e.message || '';
  } finally {
    acting.value = false;
  }
}

function requestRemove() {
  if (!profile.value) return;
  emit('remove', { user_id: profile.value.user_id, user_name: profile.value.user_name });
}

const locked = computed(() => profile.value && !profile.value.can_view_full);
const lockedMsg = computed(() =>
  profile.value?.visibility === 'private' ? 'friends.profile.locked_private' : 'friends.profile.locked_friends'
);
</script>

<template>
  <BottomSheet :open="open" :title="profile?.user_name || ''" @close="emit('close')">
    <p v-if="loading" class="muted pad">{{ $t('common.loading') }}</p>
    <p v-else-if="error" class="error pad">{{ $t('friends.profile.error') }}</p>

    <template v-else-if="profile">
      <!-- Identity -->
      <div class="id">
        <span class="avatar">
          <img v-if="profile.profile_image" :src="profile.profile_image" alt="" width="64" height="64" decoding="async" />
          <span v-else>{{ initials(profile.user_name) }}</span>
        </span>
        <span class="lv">{{ $t('friends.card.level_short') }} {{ profile.current_level }}</span>
      </div>

      <!-- Locked: friends-only or private profile of someone we can't see -->
      <p v-if="locked" class="muted locked">{{ $t(lockedMsg) }}</p>

      <!-- Full profile -->
      <template v-else>
        <div class="stats">
          <div class="stat"><strong>{{ profile.current_level }}</strong><small>{{ $t('friends.metric.level') }}</small></div>
          <div class="stat"><strong>{{ profile.weekly_xp }}</strong><small>{{ $t('friends.metric.weekly_xp') }}</small></div>
          <div class="stat">
            <strong><i class="fa-solid fa-fire" :class="{ lit: profile.current_streak > 0 }" /> {{ profile.current_streak }}</strong>
            <small>{{ $t('friends.metric.streak') }}</small>
          </div>
        </div>

        <ul class="recs">
          <li>
            <span class="rec-ic"><i class="fa-solid fa-fire" :class="{ lit: profile.longest_streak > 0 }" /></span>
            <span class="rec-l">{{ $t('friends.profile.longest_streak') }}</span>
            <strong>{{ profile.longest_streak }}{{ $t('friends.card.day_short') }}</strong>
          </li>
          <li v-if="profile.favorite_food">
            <span class="rec-ic"><i class="fa-solid fa-star" /></span>
            <span class="rec-l">{{ $t('friends.profile.favorite_food') }}</span>
            <strong class="fav">{{ profile.favorite_food.food }}
              <small class="muted">{{ $t('friends.profile.favorite_logs', { n: profile.favorite_food.logs }) }}</small>
            </strong>
          </li>
        </ul>

        <div class="sec-head">
          <span>{{ $t('friends.profile.badges') }}</span>
          <small class="muted">{{ $t('friends.profile.badges_count', { unlocked: profile.unlocked, total: profile.total_achievements }) }}</small>
        </div>
        <div v-if="profile.top_badges && profile.top_badges.length" class="badges">
          <span v-for="b in profile.top_badges" :key="b.id" class="badge">
            <span class="badge-ic" :style="{ color: toneOf(b.tone).c, background: toneOf(b.tone).bg }">
              <i class="fa-solid" :class="b.icon" />
            </span>
            <small>{{ b.name }}</small>
          </span>
        </div>

        <div class="sec-head"><span>{{ $t('friends.profile.bio_title') }}</span></div>
        <p class="bio" :class="{ muted: !profile.bio }">{{ profile.bio || $t('friends.profile.no_bio') }}</p>
      </template>

      <!-- Contextual CTA (also shown on a locked profile so strangers can add) -->
      <div v-if="profile.relationship !== 'self'" class="cta">
        <button v-if="profile.relationship === 'none'" class="btn primary" :disabled="acting" @click="add">
          {{ $t('friends.card.btn_add') }}
        </button>
        <span v-else-if="profile.relationship === 'pending_out' || profile.relationship === 'pending_in'" class="tag muted">
          {{ $t('friends.card.hint_pending') }}
        </span>
        <!-- For simplicity, we only allow removing a friend from the parent list (which shows an extra "Remove" button on each row that opens this sheet). Avoids stacking a ConfirmDialog inside the sheet. -->
        <!-- <button v-else-if="profile.relationship === 'friends'" class="btn ghost danger" :disabled="acting" @click="requestRemove">
          {{ $t('friends.menu.remove') }}
        </button> -->
      </div>
    </template>
  </BottomSheet>
</template>

<style scoped>
.pad { padding: 18px 4px; }
.muted { color: var(--muted); }
.error { color: var(--danger); }

.id { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 4px 0 14px; }
.avatar {
  width: 64px; height: 64px; border-radius: 50%; overflow: hidden;
  background: var(--surface-2); color: var(--text); font-weight: 800; font-size: 24px;
  display: grid; place-items: center;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.lv {
  font-size: 12px; font-weight: 700; color: var(--muted);
  background: var(--inset); padding: 3px 10px; border-radius: 999px;
}
.locked { text-align: center; padding: 8px 4px 16px; }

.stats { display: flex; gap: 8px; }
.stat {
  flex: 1; background: var(--inset); border: 1px solid var(--border); border-radius: 10px;
  padding: 10px 6px; text-align: center; display: flex; flex-direction: column; gap: 2px;
}
.stat strong { font-size: 18px; font-weight: 800; }
.stat small { color: var(--muted); font-size: 11px; }

.recs { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.recs li {
  display: flex; align-items: center; gap: 10px;
  background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px;
}
.rec-ic { flex: none; width: 20px; text-align: center; color: var(--muted); }
.rec-l { flex: 1; min-width: 0; font-size: 13px; color: var(--muted); }
.recs strong { font-size: 14px; font-weight: 700; }
.fav { max-width: 60%; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.lit { color: var(--streak); }

.sec-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin: 16px 0 8px; }
.sec-head > span { font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }

.badges { display: flex; flex-wrap: wrap; gap: 10px; }
.badge { display: flex; flex-direction: column; align-items: center; gap: 4px; width: 64px; }
.badge-ic { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 16px; }
.badge small { font-size: 10px; color: var(--muted); text-align: center; line-height: 1.2; overflow: hidden; }

.bio { font-size: 14px; line-height: 1.5; margin: 0; white-space: pre-line; word-break: break-word; }

.cta { display: flex; justify-content: center; margin-top: 18px; }
.btn {
  min-height: 44px; padding: 0 20px; border-radius: 10px; font-weight: 700; font-size: 14px;
  border: 1px solid transparent; white-space: nowrap;
}
.btn.primary { background: var(--accent); color: var(--on-accent); }
.btn.ghost { background: var(--surface-2); color: var(--text); }
.btn.ghost.danger { color: var(--danger); }
.btn:disabled { opacity: 0.5; }
.tag { font-size: 13px; font-weight: 700; padding: 8px 14px; border-radius: 8px; color: var(--muted); }
</style>
