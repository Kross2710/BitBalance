import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth.js';
import { navStart, navDone } from './lib/loadingBar.js';

const routes = [
  // Public welcome / app intro for logged-out visitors (ported from PHP index.php).
  // Owns '/'; logged-in users are bounced to /dashboard by the guard below.
  { path: '/', name: 'landing', component: () => import('./views/LandingView.vue') },
  { path: '/login', name: 'login', component: () => import('./views/LoginView.vue') },
  { path: '/signup', name: 'signup', component: () => import('./views/SignupView.vue') },
  // Onboarding is full-screen (no app chrome).
  {
    path: '/onboarding',
    name: 'onboarding',
    component: () => import('./views/OnboardingView.vue'),
    meta: { requiresAuth: true },
  },
  // Admin panel — its own layout + sidebar, gated by role at the router guard
  // below (requiresAdmin) AND server-side on every /api/admin call. Reached only
  // from the avatar menu when role === 'admin'.
  {
    path: '/admin',
    component: () => import('./layouts/AdminLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      { path: '', name: 'admin', component: () => import('./views/admin/AdminHomeView.vue') },
      { path: 'users', name: 'admin-users', component: () => import('./views/admin/AdminUsersView.vue') },
      // 'users/new' MUST precede 'users/:id' so it isn't captured as an id.
      { path: 'users/new', name: 'admin-user-new', component: () => import('./views/admin/AdminUserCreateView.vue') },
      { path: 'users/:id', name: 'admin-user', component: () => import('./views/admin/AdminUserDetailView.vue') },
      { path: 'logs', name: 'admin-logs', component: () => import('./views/admin/AdminLogsView.vue') },
      { path: 'barcodes', name: 'admin-barcodes', component: () => import('./views/admin/AdminBarcodesView.vue') },
      // ':barcode' is digits-only (validated server-side), so no clash with a sub-route.
      { path: 'barcodes/:barcode', name: 'admin-barcode', component: () => import('./views/admin/AdminBarcodeDetailView.vue') },
    ],
  },
  // Authenticated app shell: persistent nav (sidebar/tab bar) wraps the pages,
  // so switching tabs never remounts the chrome — navigation stays seamless.
  // Parent path is '/app' (a never-visited prefix) so the public landing can own
  // '/'; children use ABSOLUTE paths, so their URLs stay '/dashboard', '/intake', …
  {
    path: '/app',
    component: () => import('./layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: '/dashboard', name: 'dashboard', component: () => import('./views/DashboardView.vue') },
      { path: '/intake', name: 'intake', component: () => import('./views/IntakeView.vue') },
      { path: '/profile', name: 'profile', component: () => import('./views/ProfileView.vue') },
      { path: '/settings', name: 'settings', component: () => import('./views/SettingsView.vue') },
      // Progress / achievements — reached via the avatar menu, not the bottom
      // nav (gamification isn't a core daily flow yet). Mirrors how /trainer sits.
      { path: '/progress', name: 'progress', component: () => import('./views/ProgressView.vue') },
      { path: '/coach', name: 'coach', component: () => import('./views/CoachView.vue') },
      { path: '/friends', name: 'friends', component: () => import('./views/FriendsView.vue') },
      // PT workspace — reached via the topbar avatar menu, not the bottom nav.
      { path: '/trainer', name: 'trainer', component: () => import('./views/TrainerView.vue') },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Client-side guard. Waits for the one-time /me bootstrap, then gates protected
// routes. This is what makes navigation feel seamless — no full page reloads.
router.beforeEach(async (to) => {
  // Show the top loading bar for the whole navigation (incl. lazy-chunk download
  // + the guard's one-time bootstrap). navActive is a boolean, so redirect hops
  // collapse to a single bar; afterEach/onError clear it once.
  navStart();
  const auth = useAuthStore();
  if (!auth.ready) {
    await auth.bootstrap();
    // First load only: adopt the logged-in user's stored language. The cookie /
    // navigator already set a pre-paint default; persist:false because we're
    // reflecting the server value, not echoing it back. Gated to the initial
    // bootstrap so it never overrides an in-session manual switch.
    if (auth.user?.language_preference) {
      const { setLocale } = await import('./i18n/index.js');
      setLocale(auth.user.language_preference, { persist: false });
    }
    // Same for the stored theme: reflect the server value once on first load.
    if (auth.user?.theme_preference) {
      const { setTheme } = await import('./lib/theme.js');
      setTheme(auth.user.theme_preference, { persist: false });
    }
  }

  if (to.meta.requiresAuth && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }
  // Admin area is role-gated at the router level (not just hidden in the UI).
  if (to.meta.requiresAdmin && auth.user?.role !== 'admin') {
    return { name: 'dashboard' };
  }
  if ((to.name === 'login' || to.name === 'signup' || to.name === 'landing') && auth.user) {
    return { name: 'dashboard' };
  }
  // New accounts must finish onboarding before reaching the dashboard.
  if (auth.user?.needs_onboarding && to.name !== 'onboarding') {
    return { name: 'onboarding' };
  }
  if (!auth.user?.needs_onboarding && to.name === 'onboarding') {
    return { name: 'dashboard' };
  }
});

// afterEach fires for normal, aborted, and the final hop of a redirected
// navigation; onError covers a lazy-chunk load failure. Either clears the bar.
router.afterEach(() => navDone());
router.onError(() => navDone());

export default router;
