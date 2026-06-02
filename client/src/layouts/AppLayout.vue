<script setup>
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

// Nav model shared by the desktop sidebar and the mobile tab bar. Items that
// aren't ported yet are marked disabled (rendered greyed, non-clickable) so the
// intended structure is visible without dead links. Icons mirror the PHP app's
// Font Awesome set (fa-solid).
const navItems = [
  { to: '/dashboard', icon: 'fa-house', label: 'Home', enabled: true },
  { to: '/coach', icon: 'fa-dumbbell', label: 'Coach', enabled: true },
  { to: '/friends', icon: 'fa-user-group', label: 'Friends', enabled: false },
  { to: '/profile', icon: 'fa-user', label: 'Profile', enabled: true },
];

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
        <template v-for="item in navItems" :key="item.to">
          <RouterLink v-if="item.enabled" :to="item.to" class="nav-link">
            <i class="fa-solid" :class="item.icon" />
            <span class="nav-label">{{ item.label }}</span>
          </RouterLink>
          <span v-else class="nav-link disabled" title="Coming soon">
            <i class="fa-solid" :class="item.icon" />
            <span class="nav-label">{{ item.label }}</span>
          </span>
        </template>
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
      <template v-for="item in navItems" :key="item.to">
        <RouterLink v-if="item.enabled" :to="item.to" class="tab">
          <i class="fa-solid" :class="item.icon" />
          <span>{{ item.label }}</span>
        </RouterLink>
        <span v-else class="tab disabled" title="Coming soon">
          <i class="fa-solid" :class="item.icon" />
          <span>{{ item.label }}</span>
        </span>
      </template>
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
    /* >= 44px tap target (icon + label + padding). */
    min-height: 52px;
    padding: 8px 0;
    color: var(--muted);
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
  }
  .tab i {
    font-size: 18px;
  }
  .tab.router-link-active {
    color: var(--accent);
  }
  .tab.disabled {
    opacity: 0.4;
  }
}
</style>
