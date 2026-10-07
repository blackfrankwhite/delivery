import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from './stores/auth';
import LoginPage from './pages/LoginPage.vue';
const DashboardPage = () => import('./pages/DashboardPage.vue');
const DeliveriesPage = () => import('./pages/DeliveriesPage.vue');
const DeliveryFormPage = () => import('./pages/DeliveryFormPage.vue');
const DeliveryDetailPage = () => import('./pages/DeliveryDetailPage.vue');
const DirectoryPage = () => import('./pages/DirectoryPage.vue');
const CustomerDetailPage = () => import('./pages/CustomerDetailPage.vue');
const DriverDetailPage = () => import('./pages/DriverDetailPage.vue');
const RidesPage = () => import('./pages/RidesPage.vue');
const RideDetailPage = () => import('./pages/RideDetailPage.vue');
const DriverTodayPage = () => import('./pages/DriverTodayPage.vue');
const StorageLocationsPage = () => import('./pages/StorageLocationsPage.vue');
const NotFoundPage = () => import('./pages/NotFoundPage.vue');
const dispatch = { roles: ['superadministrator', 'operator'] };
const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { top: 0 };
  },
  routes: [
    { path: '/login', component: LoginPage, meta: { public: true } },
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', component: DashboardPage, meta: dispatch },
    {
      path: '/orders',
      alias: '/deliveries',
      component: DeliveriesPage,
      meta: dispatch,
    },
    {
      path: '/orders/create',
      alias: '/deliveries/create',
      component: DeliveryFormPage,
      meta: dispatch,
    },
    {
      path: '/orders/:id/edit',
      alias: '/deliveries/:id/edit',
      component: DeliveryFormPage,
      meta: dispatch,
    },
    {
      path: '/orders/:id',
      alias: '/deliveries/:id',
      component: DeliveryDetailPage,
      meta: dispatch,
    },
    ...['customers', 'items', 'drivers', 'operators', 'users'].map((kind) => ({
      path: `/${kind}`,
      component: DirectoryPage,
      props: { kind },
      meta: kind === 'users' ? { roles: ['superadministrator'] } : dispatch,
    })),
    {
      path: '/customers/:id',
      component: CustomerDetailPage,
      meta: dispatch,
    },
    {
      path: '/storage-locations',
      component: StorageLocationsPage,
      meta: { roles: ['superadministrator'] },
    },
    { path: '/drivers/:id', component: DriverDetailPage, meta: dispatch },
    { path: '/rides', component: RidesPage, meta: dispatch },
    { path: '/rides/:id', component: RideDetailPage, meta: dispatch },
    {
      path: '/driver/today',
      component: DriverTodayPage,
      meta: { roles: ['driver'] },
    },
    {
      path: '/driver/orders',
      alias: '/driver/deliveries',
      component: DeliveriesPage,
      meta: { roles: ['driver'] },
    },
    {
      path: '/driver/orders/:id',
      alias: '/driver/tasks/:id',
      component: DeliveryDetailPage,
      meta: { roles: ['driver'] },
    },
    {
      path: '/driver/rides',
      component: RidesPage,
      meta: { roles: ['driver'] },
    },
    {
      path: '/driver/rides/:id',
      component: RideDetailPage,
      meta: { roles: ['driver'] },
    },
    { path: '/:pathMatch(.*)*', component: NotFoundPage },
  ],
});
router.beforeEach(async (to) => {
  const auth = useAuth();
  if (!auth.initialized) {
    try {
      await auth.restore();
    } catch {
      /* Login displays connectivity errors on submit. */
    }
  }
  if (!to.meta.public && !auth.user)
    return { path: '/login', query: { redirect: to.fullPath } };
  if (to.meta.public && auth.user)
    return auth.driver ? '/driver/today' : '/dashboard';
  if (to.meta.roles && !to.meta.roles.includes(auth.user?.role))
    return auth.driver ? '/driver/today' : '/dashboard';
});
export default router;
