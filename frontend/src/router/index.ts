import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import apiService from '@/services/apiService'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/HomeView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { requiresGuest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/auth/RegisterView.vue'),
    meta: { requiresGuest: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/communication',
    name: 'communication',
    component: () => import('@/views/communication/CommunicationView.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: 'services',
        name: 'communication-services',
        component: () => import('@/views/communication/ServicesView.vue'),
      },
      {
        path: 'preferences',
        name: 'communication-preferences',
        component: () => import('@/views/communication/PreferencesView.vue'),
      },
    ],
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('@/views/SettingsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  try {
    const authStore = useAuthStore()

    // Initialize auth store
    if (!authStore.isAuthenticated && apiService.getToken()) {
      await authStore.initialize()
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
      next({ name: 'login', query: { redirect: to.fullPath } })
    } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
      next({ name: 'dashboard' })
    } else {
      next()
    }
  } catch (error) {
    console.error('Navigation guard error:', error)
    next() // Continue navigation even if there's an error
  }
})

export default router
