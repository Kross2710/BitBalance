<script setup>
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { computed, ref, watch, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { useBadgesStore } from '../stores/badges.js';

const auth = useAuthStore();
const route = useRoute();
const badgesStore = useBadgesStore();

// Avatar dropdown (Profile / Trainer workspace / …). Trainer workspace is the
// only entry point to /trainer — it stays off the bottom nav by design.
const menuOpen = ref(false);
watch(() => route.fullPath, () => (menuOpen.value = false));

// Nav model shared by the desktop sidebar and the mobile tab bar. Icons mirror
// the PHP app's Font Awesome set (fa-solid). Profile is intentionally NOT here:
// it's reached via the avatar in the topbar, keeping the bottom bar to 4 tabs.
// labelKey resolves through $t in the template so the labels re-render on a
// language switch (a plain string would freeze at its initial locale).
const navItems = [
  { to: '/dashboard', icon: 'fa-house', labelKey: 'nav.home', enabled: true },
  { to: '/intake', icon: 'fa-utensils', labelKey: 'nav.intake', enabled: true },
  { to: '/coach', icon: 'fa-dumbbell', labelKey: 'nav.coach', enabled: true },
  { to: '/friends', icon: 'fa-user-group', labelKey: 'nav.friends', enabled: true },
];

// Initial for the avatar fallback when the user has no profile image.
const userInitial = computed(() => (auth.user?.first_name || auth.user?.handle || 'U').trim().charAt(0).toUpperCase());

// Per-tab numeric badges (FB-style) live in a shared store so Profile (after
// saving reminder prefs) can refresh them instantly — see stores/badges.js.
const badges = badgesStore.counts;

onMounted(() => badgesStore.refresh());
// Re-check after the user has likely changed today's log (landing on these tabs).
watch(
  () => route.name,
  (name) => {
    if (name === 'dashboard' || name === 'intake' || name === 'friends') badgesStore.refresh();
  }
);

</script>

<template>
  <div class="layout">
    <!-- Desktop sidebar: collapsed to icons, expands on hover -->
    <aside class="sidebar">
      <div class="brand"><span class="brand-mark">B</span><span class="brand-text">BitBalance</span></div>
      <nav class="side-nav">
        <RouterLink v-for="item in navItems" :key="item.to" :to="item.to" class="nav-link">
          <span class="nav-ico">
            <i class="fa-solid" :class="item.icon" />
            <span v-if="badges[item.to]" class="badge">{{ badges[item.to] }}</span>
          </span>
          <span class="nav-label">{{ $t(item.labelKey) }}</span>
        </RouterLink>
      </nav>
    </aside>

    <!-- Main column -->
    <div class="main">
      <header class="topbar">
        <RouterLink to="/dashboard" class="brand-link" :aria-label="$t('nav.brand_home')">
          <span class="brand-mark">B</span>
          <span class="brand-name">BitBalance</span>
        </RouterLink>
        <div class="avatar-wrap">
          <button
            class="avatar-link"
            :class="{ active: ['profile', 'settings', 'trainer', 'progress'].includes(route.name) }"
            :aria-label="$t('nav.account_menu')"
            aria-haspopup="true"
            :aria-expanded="menuOpen"
            @click="menuOpen = !menuOpen"
          >
            <img v-if="auth.user?.profile_image" :src="auth.user.profile_image" class="topbar-avatar" alt="" />
            <span v-else class="topbar-avatar fallback">{{ userInitial }}</span>
          </button>
          <div v-if="menuOpen" class="avatar-backdrop" @click="menuOpen = false" />
          <div v-if="menuOpen" class="avatar-menu" role="menu">
            <div class="am-head">
              <strong>{{ auth.user?.first_name || auth.user?.handle }}</strong>
              <span class="am-handle">@{{ auth.user?.handle }}</span>
            </div>
            <RouterLink v-if="auth.user?.role === 'pt'" to="/trainer" class="am-item" role="menuitem">
              <i class="fa-solid fa-user-tie" /> {{ $t('nav.trainer_workspace') }}
            </RouterLink>
            <RouterLink v-if="auth.user?.role === 'admin'" to="/admin" class="am-item" role="menuitem">
              <i class="fa-solid fa-shield-halved" /> {{ $t('nav.admin') }}
            </RouterLink>
            <RouterLink to="/progress" class="am-item" role="menuitem">
              <i class="fa-solid fa-medal" /> {{ $t('nav.progress') }}
            </RouterLink>
            <RouterLink to="/profile" class="am-item" role="menuitem">
              <i class="fa-solid fa-user" /> {{ $t('nav.profile') }}
            </RouterLink>
            <RouterLink to="/settings" class="am-item" role="menuitem">
              <i class="fa-solid fa-gear" /> {{ $t('nav.settings') }}
            </RouterLink>
          </div>
        </div>
      </header>
      <div class="content">
        <RouterView v-slot="{ Component }">
          <Transition name="fade" mode="out-in">
            <component :is="Component" />
          </Transition>
        </RouterView>
      </div>
    </div>

    <!-- Mobile bottom tab bar -->
    <nav class="tabbar">
      <RouterLink v-for="item in navItems" :key="item.to" :to="item.to" class="tab">
        <span class="tab-ico">
          <i class="fa-solid" :class="item.icon" />
          <span v-if="badges[item.to]" class="badge">{{ badges[item.to] }}</span>
        </span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>
  </div>
</template>

<style scoped>
.layout {
  min-height: 100vh;
}

/* ---------- Desktop sidebar ---------- */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 64px;
  background: var(--card);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  padding: 12px 0;
  overflow: hidden;
  transition: width 0.18s ease;
  z-index: 40;
}
.sidebar:hover {
  width: 220px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 18px 18px;
  white-space: nowrap;
}
.brand-mark {
  flex: none;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--accent);
  color: var(--on-accent);
  font-weight: 800;
  display: grid;
  place-items: center;
}
.brand-text {
  font-weight: 700;
  opacity: 0;
  transition: opacity 0.18s ease;
}
.sidebar:hover .brand-text {
  opacity: 1;
}

.side-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 0 10px;
}
.nav-link {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 11px 12px;
  border-radius: 10px;
  color: var(--muted);
  text-decoration: none;
  white-space: nowrap;
  font-weight: 600;
  font-size: 14px;
}
.nav-link i {
  flex: none;
  width: 20px;
  text-align: center;
  font-size: 17px;
}
.nav-link:hover:not(.disabled) {
  background: var(--inset);
  color: var(--text);
}
.nav-link.router-link-active {
  background: var(--inset);
  color: var(--accent);
}
.nav-link.disabled {
  opacity: 0.4;
  cursor: default;
}
.nav-label {
  opacity: 0;
  transition: opacity 0.18s ease;
}
.sidebar:hover .nav-label {
  opacity: 1;
}

/* ---------- Nav icon slot: avatar + badge (shared sidebar/tab bar) ---------- */
.nav-ico,
.tab-ico {
  position: relative;
  display: grid;
  place-items: center;
}
.nav-ico { flex: none; width: 20px; }
.badge {
  position: absolute;
  top: -6px;
  right: -10px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
  display: grid;
  place-items: center;
  border: 2px solid var(--card); /* cutout against the bar background */
}

/* ---------- Main column ---------- */
.main {
  margin-left: 64px;
  min-height: 100vh;
}
.topbar {
  position: sticky;
  top: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 24px;
  background: var(--glass);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid var(--border);
  z-index: 30;
}
/* Topbar brand (left) — app identity, links home. */
.brand-link {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: var(--text);
}
.brand-link .brand-mark {
  flex: none;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: var(--accent);
  color: var(--on-accent);
  font-weight: 800;
  display: grid;
  place-items: center;
}
.brand-name { font-weight: 800; font-size: 17px; letter-spacing: 0.01em; }

/* Topbar avatar (right) — the entry point to Profile / settings / logout.
   44x44 hit area around the 36px avatar for a comfortable tap target. */
.avatar-wrap { position: relative; }
.avatar-link {
  flex: none;
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  /* It's a <button> now — strip the global button skin. */
  background: transparent;
  border: none;
  padding: 0;
  min-height: 0;
}
.topbar-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
  display: grid;
  place-items: center;
  background: var(--accent);
  color: var(--on-accent);
  font-weight: 800;
  font-size: 15px;
}
.avatar-link.active .topbar-avatar { box-shadow: 0 0 0 2px var(--accent); }

/* Avatar dropdown menu */
.avatar-backdrop { position: fixed; inset: 0; z-index: 45; }
.avatar-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 46;
  min-width: 200px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 6px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}
.am-head { padding: 8px 10px 10px; display: flex; flex-direction: column; gap: 1px; border-bottom: 1px solid var(--border); margin-bottom: 6px; }
.am-head strong { font-size: 14px; }
.am-handle { font-size: 12px; color: var(--muted); }
.am-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 10px; border-radius: 8px;
  color: var(--text); text-decoration: none; font-size: 14px; font-weight: 600;
}
.am-item i { width: 18px; text-align: center; color: var(--muted); }
.am-item:hover { background: var(--inset); }
.am-item.router-link-active { color: var(--accent); }
.am-item.router-link-active i { color: var(--accent); }

.content {
  padding: 4px 0 32px;
}

/* ---------- Mobile bottom tab bar ---------- */
.tabbar {
  display: none;
}

@media (max-width: 767px) {
  .sidebar {
    display: none;
  }
  .main {
    margin-left: 0;
  }
  .content {
    /* Clear the fixed tab bar (height + bottom safe-area inset). */
    padding-bottom: calc(64px + env(safe-area-inset-bottom));
  }
  .tabbar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    background: var(--card);
    border-top: 1px solid var(--border);
    z-index: 40;
    /* Keep tabs above the iOS home indicator / Android gesture bar. */
    padding-bottom: env(safe-area-inset-bottom);
  }
  .tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    /* >= 44px tap target; 56px gives comfortable icon + label spacing. */
    min-height: 56px;
    padding: 8px 0;
    color: var(--muted);
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
  }
  .tab i {
    font-size: 18px;
  }
  .tab-ico {
    transition: transform 0.15s ease;
  }
  /* Stronger active state (FB-style): accent colour, larger icon, bolder label. */
  .tab.router-link-active {
    color: var(--accent);
  }
  .tab.router-link-active .tab-ico {
    transform: scale(1.14);
  }
  .tab.router-link-active span:last-child {
    font-weight: 800;
  }
}
</style>
