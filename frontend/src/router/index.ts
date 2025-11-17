import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import BlankLayout from '@/layouts/blank/BlankLayout.vue';
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: BlankLayout,
      meta: { requiresGuest: true },
      children: [
        {
          path: '',
          name: 'login',
          component: () => import('@/views/auth/Login.vue'),
        },
        {
          path: 'register',
          name: 'register',
          component: () => import('@/views/auth/Register.vue'),
        },
        {
          path: 'forgot-password',
          name: 'forgot-password',
          component: () => import('@/views/auth/ForgotPassword.vue'),
        },
      ],
    },
    {
      path: '/dashboard',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/dashboard/Dashboard.vue'),
          meta: { title: 'Dashboard' },
        },
      ],
    },
    {
      path: '/transfer',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'transfer',
          component: () => import('@/views/transfer/Transfer.vue'),
          meta: { title: 'Send Money' },
        },
      ],
    },
    {
      path: '/transactions',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'transactions',
          component: () => import('@/views/transactions/Transactions.vue'),
          meta: { title: 'Transaction History' },
        },
      ],
    },
  ],
});

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  // Wait for auth initialization if token exists but user not loaded
  if (!authStore.user && authStore.token) {
    await authStore.initialize();
  }

  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
  const requiresGuest = to.matched.some(record => record.meta.requiresGuest);

  if (requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login' });
  } else if (requiresGuest && authStore.isAuthenticated) {
    next({ name: 'dashboard' });
  } else {
    next();
  }
});

export default router;
