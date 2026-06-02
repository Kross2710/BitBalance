import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth.js';

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/login', name: 'login', component: () => import('./views/LoginView.vue') },
  { path: '/signup', name: 'signup', component: () => import('./views/SignupView.vue') },
  {
    path: '/onboarding',
    name: 'onboarding',
    component: () => import('./views/OnboardingView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('./views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Client-side guard. Waits for the one-time /me bootstrap, then gates protected
// routes. This is what makes navigation feel seamless — no full page reloads.
router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.ready) await auth.bootstrap();

  if (to.meta.requiresAuth && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }
  if ((to.name === 'login' || to.name === 'signup') && auth.user) {
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

export default router;
