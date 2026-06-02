<script setup>
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router';
import { reactive, watch, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { api } from '../lib/api.js';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

// Nav model shared by the desktop sidebar and the mobile tab bar. Icons mirror
// the PHP app's Font Awesome set (fa-solid).
const navItems = [
  { to: '/dashboard', icon: 'fa-house', label: 'Home', enabled: true },
  { to: '/intake', icon: 'fa-utensils', label: 'Intake', enabled: true },
  { to: '/coach', icon: 'fa-dumbbell', label: 'Coach', enabled: true },
  { to: '/friends', icon: 'fa-user-group', label: 'Friends', enabled: true },
  { to: '/profile', icon: 'fa-user', label: 'Profile', enabled: true },
];

// Per-tab numeric badges (FB-style):
//   /intake  → today's unlogged main meals (breakfast/lunch/dinner), a nudge
//              that clears as you log. From the existing dashboard summary.
//   /friends → incoming friend requests awaiting a response.
const badges = reactive({ '/intake': 0, '/friends': 0 });

async function refreshBadges() {
  // Independent sources; a failure on one must not blank the other.
  const [summary, pending] = await Promise.allSettled([
    api.get('/api/dashboard/summary'),
    api.get('/api/social/pending-count'),
  ]);
  if (summary.status === 'fulfilled') {
    const meals = summary.value.meal_categories || {};
    badges['/intake'] = ['Breakfast', 'Lunch', 'Dinner'].filter((m) => !(Number(meals[m]) > 0)).length;
  }
  if (pending.status === 'fulfilled') {
    badges['/friends'] = Number(pending.value.count) || 0;
  }
}

onMounted(refreshBadges);
// Re-check after the user has likely changed today's log (landing on these tabs).
watch(
  () => route.name,
  (name) => {
    if (name === 'dashboard' || name === 'intake' || name === 'friends') refreshBadges();
  }
);

async function onLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="layout">
    <!-- Desktop sidebar: collapsed to icons, expands on hover -->
    <aside class="sidebar">
      <div class="brand"><span class="brand-mark">B</span><span class="brand-text">BitBalance</span></div>
      <nav class="side-nav">
        <RouterLink v-for="item in navItems" :key="item.to" :to="item.to" class="nav-link">
          <span class="nav-ico">
            <img
              v-if="item.to === '/profile' && auth.user?.profile_image"
              :src="auth.user.profile_image"
              class="nav-avatar"
              alt=""
            />
            <i v-else class="fa-solid" :class="item.icon" />
            <span v-if="badges[item.to]" class="badge">{{ badges[item.to] }}</span>
          </span>
          <span class="nav-label">{{ item.label }}</span>
        </RouterLink>
      </nav>
    </aside>

    <!-- Main column -->
    <div class="main">
      <header class="topbar">
        <span class="greeting">Hi, {{ auth.user?.first_name || auth.user?.handle }}</span>
        <button class="logout" @click="onLogout"><i class="fa-solid fa-right-from-bracket" /> Log out</button>
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
          <img
            v-if="item.to === '/profile' && auth.user?.profile_image"
            :src="auth.user.profile_image"
            class="nav-avatar"
            alt=""
          />
          <i v-else class="fa-solid" :class="item.icon" />
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
  color: #04210f;
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
  background: #12151b;
  color: var(--text);
}
.nav-link.router-link-active {
  background: #12151b;
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
.nav-avatar {
  border-radius: 50%;
  object-fit: cover;
  display: block;
}
.nav-ico .nav-avatar { width: 22px; height: 22px; }
.tab-ico .nav-avatar { width: 24px; height: 24px; }
/* Ring the avatar on the active tab (FB-style). */
.nav-link.router-link-active .nav-avatar,
.tab.router-link-active .nav-avatar {
  box-shadow: 0 0 0 2px var(--accent);
}
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
  background: rgba(15, 17, 21, 0.85);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid var(--border);
  z-index: 30;
}
.greeting {
  font-weight: 700;
}
.logout {
  background: #2a2e37;
  color: var(--text);
  font-size: 13px;
}
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
