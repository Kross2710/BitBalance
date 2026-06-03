<script setup>
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router';
import { computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

// Defence in depth: the router guard already blocks non-admins, but this area is
// sensitive, so bounce anyone who reaches the layout without the admin role.
onMounted(() => {
  if (auth.user && auth.user.role !== 'admin') router.replace('/dashboard');
});

// Sidebar nav for the admin sections.
const navItems = [
  { to: '/admin', icon: 'fa-gauge', labelKey: 'admin.nav.overview', enabled: true },
  { to: '/admin/users', icon: 'fa-users', labelKey: 'admin.nav.users', enabled: true },
  { to: '/admin/barcodes', icon: 'fa-barcode', labelKey: 'admin.nav.barcodes', enabled: true },
  { to: '/admin/logs', icon: 'fa-list-check', labelKey: 'admin.nav.logs', enabled: true },
];

// Explicit active matching: Overview is exact (so it doesn't light up on child
// routes); section items match their path prefix (so /users/:id keeps Users lit).
function isActive(item) {
  return item.to === '/admin' ? route.path === '/admin' : route.path.startsWith(item.to);
}

const adminName = computed(() => auth.user?.first_name || auth.user?.handle || 'Admin');
</script>

<template>
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <div class="admin-brand">
        <span class="brand-mark">B</span>
        <span>{{ $t('admin.title') }}</span>
      </div>
      <nav class="admin-nav">
        <template v-for="item in navItems" :key="item.to">
          <RouterLink v-if="item.enabled" :to="item.to" class="admin-nav-link" :class="{ active: isActive(item) }">
            <i class="fa-solid" :class="item.icon" />
            <span>{{ $t(item.labelKey) }}</span>
          </RouterLink>
          <span v-else class="admin-nav-link disabled" aria-disabled="true">
            <i class="fa-solid" :class="item.icon" />
            <span>{{ $t(item.labelKey) }}</span>
            <em class="soon">{{ $t('admin.coming_soon') }}</em>
          </span>
        </template>
      </nav>
      <RouterLink to="/dashboard" class="admin-back">
        <i class="fa-solid fa-arrow-left" />
        <span>{{ $t('admin.back_to_app') }}</span>
      </RouterLink>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <strong>{{ $t('admin.title') }}</strong>
        <span class="admin-who">{{ adminName }}</span>
      </header>
      <div class="admin-content">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-layout { min-height: 100vh; display: flex; }

.admin-sidebar {
  width: 220px;
  flex: none;
  background: var(--card);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  padding: 16px 12px;
  gap: 4px;
}
.admin-brand {
  display: flex; align-items: center; gap: 10px;
  font-weight: 800; padding: 4px 8px 16px;
}
.brand-mark {
  flex: none; width: 28px; height: 28px; border-radius: 8px;
  background: var(--accent); color: var(--on-accent); font-weight: 800;
  display: grid; place-items: center;
}
.admin-nav { display: flex; flex-direction: column; gap: 4px; }
.admin-nav-link {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; border-radius: 10px;
  color: var(--muted); text-decoration: none; font-weight: 600; font-size: 14px;
}
.admin-nav-link i { flex: none; width: 18px; text-align: center; }
.admin-nav-link:not(.disabled):hover { background: var(--inset); color: var(--text); }
.admin-nav-link.active:not(.disabled) { background: var(--inset); color: var(--accent); }
.admin-nav-link.disabled { opacity: 0.45; cursor: default; }
.soon {
  margin-left: auto; font-style: normal; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.04em;
  background: var(--border); color: var(--muted); padding: 2px 6px; border-radius: 999px;
}
.admin-back {
  margin-top: auto; display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 600;
  color: var(--muted); text-decoration: none;
}
.admin-back:hover { color: var(--text); background: var(--inset); }

.admin-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.admin-topbar {
  position: sticky; top: 0; z-index: 10;
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 24px;
  background: var(--glass); backdrop-filter: blur(8px);
  border-bottom: 1px solid var(--border);
}
.admin-who { color: var(--muted); font-size: 14px; }
.admin-content { padding: 24px; }

@media (max-width: 767px) {
  .admin-layout { flex-direction: column; }
  .admin-sidebar {
    width: auto; flex-direction: row; align-items: center; flex-wrap: wrap;
    padding: 10px 12px; gap: 8px;
  }
  .admin-brand { padding: 0 8px 0 0; }
  .admin-nav { flex-direction: row; flex-wrap: wrap; }
  .soon { display: none; }
  .admin-back { margin: 0 0 0 auto; }
  .admin-content { padding: 16px; }
}
</style>
