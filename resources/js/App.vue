<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from './stores/auth';
import { useToasts } from './stores/toasts';
import { errorMessage } from './api';
import { initials } from './utils';
import LanguageSwitcher from './components/LanguageSwitcher.vue';
import Icon from './components/Icon.vue';
const auth = useAuth(),
  toasts = useToasts(),
  route = useRoute(),
  router = useRouter(),
  open = ref(false),
  mobile = ref(false),
  drawer = ref(null),
  menuButton = ref(null);
let media, previousFocus, previousOverflow;
const bottomNav = computed(() =>
  auth.driver ? nav.value : nav.value.slice(1, 4),
);
function breakpoint() {
  mobile.value = media.matches;
  if (!mobile.value) open.value = false;
}
function drawerKey(event) {
  if (!open.value || !mobile.value) return;
  if (event.key === 'Escape') open.value = false;
  if (event.key !== 'Tab') return;
  const targets = [
    ...drawer.value.querySelectorAll('a[href], button, select'),
  ].filter((el) => !el.disabled && el.getClientRects().length);
  const first = targets[0],
    last = targets.at(-1);
  if (
    event.shiftKey &&
    (document.activeElement === first ||
      !drawer.value.contains(document.activeElement))
  ) {
    event.preventDefault();
    last?.focus();
  } else if (
    !event.shiftKey &&
    (document.activeElement === last ||
      !drawer.value.contains(document.activeElement))
  ) {
    event.preventDefault();
    first?.focus();
  }
}
watch(open, async (value) => {
  if (value) {
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    await nextTick();
    drawer.value?.querySelector('button')?.focus();
  } else {
    document.body.style.overflow = previousOverflow || '';
    await nextTick();
    if (mobile.value)
      (previousFocus || menuButton.value)?.focus({ preventScroll: true });
  }
});
watch(
  () => route.fullPath,
  () => {
    open.value = false;
  },
);
onMounted(() => {
  media = window.matchMedia('(max-width: 1023px)');
  breakpoint();
  media.addEventListener('change', breakpoint);
  document.addEventListener('keydown', drawerKey);
});
onUnmounted(() => {
  media?.removeEventListener('change', breakpoint);
  document.removeEventListener('keydown', drawerKey);
  if (open.value) document.body.style.overflow = previousOverflow || '';
});
const nav = computed(() =>
  auth.driver
    ? [
        ['Today', '/driver/today', 'Sun'],
        ['My rides', '/driver/rides', 'Route'],
        ['My orders', '/driver/orders', 'Package'],
      ]
    : [
        ['Dashboard', '/dashboard', 'LayoutDashboard'],
        ['Orders', '/orders', 'Package'],
        ['Rides', '/rides', 'Route'],
        ['Customers', '/customers', 'Users'],
        ['Items', '/items', 'Boxes'],
        ['Drivers', '/drivers', 'Truck'],
        ['Operators', '/operators', 'Headset'],
        ...(auth.admin
          ? [
              ['Storage locations', '/storage-locations', 'Boxes'],
              ['Users', '/users', 'UserRoundCog'],
            ]
          : []),
      ],
);
const section = computed(
  () => nav.value.find((n) => route.path.startsWith(n[1]))?.[0] || 'Operations',
);
async function logout() {
  try {
    await auth.logout();
    await router.push('/login');
  } catch (e) {
    toasts.show(errorMessage(e), 'error');
  }
}
</script>
<template>
  <div v-if="route.meta.public"><RouterView /></div>
  <div v-else class="min-h-screen">
    <div
      v-if="open"
      class="fixed inset-0 z-30 bg-slate-900/30 lg:hidden"
      @click="open = false"
    ></div>
    <aside
      id="workspace-navigation"
      ref="drawer"
      :inert="mobile && !open ? true : undefined"
      :aria-hidden="mobile && !open ? true : undefined"
      :aria-label="$t('Navigation')"
      :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
      class="workspace-sidebar fixed inset-y-0 left-0 z-40 flex w-[min(300px,85vw)] lg:w-[228px] flex-col border-r border-slate-200/80 bg-white transition-transform"
    >
      <button
        class="absolute right-3 top-[calc(0.75rem+env(safe-area-inset-top))] flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-500 lg:hidden"
        :aria-label="$t('Close navigation')"
        @click="open = false"
      >
        <Icon name="X" />
      </button>
      <RouterLink
        :to="auth.driver ? '/driver/today' : '/dashboard'"
        class="flex h-[76px] shrink-0 items-center gap-2.5 px-6"
      >
        <span
          class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white"
        >
          <Icon name="Truck" :size="21" />
        </span>
        <span class="text-xl font-bold tracking-tight text-slate-900">
          dispatch
          <span class="text-brand-600">.</span>
        </span>
      </RouterLink>
      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pt-4">
        <p class="eyebrow mb-4 px-3">
          {{ $t(auth.driver ? 'Driver workspace' : 'Workspace') }}
        </p>
        <nav class="space-y-1">
          <RouterLink
            v-for="[label, path, icon] in nav"
            :key="path"
            :to="path"
            :class="
              route.path.startsWith(path)
                ? 'bg-brand-50 text-brand-600 font-semibold'
                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
            "
            class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-[13px]"
            @click="open = false"
          >
            <Icon :name="icon" :size="18" />
            {{ $t(label) }}
            <span
              v-if="route.path.startsWith(path)"
              class="ml-auto h-1.5 w-1.5 rounded-full bg-brand-600"
            ></span>
          </RouterLink>
        </nav>
      </div>
      <div class="shrink-0 p-5">
        <div
          class="mb-5 hidden rounded-xl lg:block border border-brand-100 bg-brand-50/50 p-4"
        >
          <div
            class="mb-2 flex items-center gap-2 text-xs font-semibold text-brand-700"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span>
            {{ $t('All in one place') }}
          </div>
          <p class="text-xs leading-5 text-slate-500">
            {{ $t('Every order. Every driver.') }}
            <br />
            {{ $t('A clearer day ahead.') }}
          </p>
        </div>
        <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
          <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700"
          >
            {{ initials(auth.user?.name) }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-semibold text-slate-700">
              {{ auth.user?.name }}
            </p>
            <p class="mt-0.5 text-[10px] capitalize text-slate-400">
              {{
                $t(
                  auth.user?.role === 'superadministrator'
                    ? 'Administrator'
                    : auth.user?.role,
                )
              }}
            </p>
          </div>
          <button
            class="p-2 text-slate-400 hover:text-red-600"
            :aria-label="$t('Log out')"
            @click="logout"
          >
            <Icon name="LogOut" :size="16" />
          </button>
        </div>
      </div>
    </aside>
    <div :inert="open && mobile ? true : undefined" class="lg:ml-[228px]">
      <header
        class="workspace-header sticky top-0 z-20 flex min-h-16 items-center justify-between gap-2 lg:static lg:h-[76px] border-b border-slate-200/80 bg-white px-3 sm:px-8"
      >
        <div class="flex min-w-0 items-center gap-2 sm:gap-3">
          <button
            ref="menuButton"
            aria-controls="workspace-navigation"
            :aria-expanded="open"
            class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg lg:hidden"
            :aria-label="$t('Open navigation')"
            @click="open = true"
          >
            <Icon name="Menu" />
          </button>
          <span class="hidden text-xs text-slate-400 sm:inline">
            {{ $t('Workspace') }}
          </span>
          <span class="hidden text-slate-300 sm:inline">/</span>
          <span class="truncate text-xs font-medium text-slate-700">
            {{ $t(section) }}
          </span>
        </div>
        <div class="flex items-center gap-2 sm:gap-4">
          <LanguageSwitcher />
          <span
            class="hidden rounded-full bg-brand-50 px-3 py-1.5 text-[11px] font-medium text-brand-700 sm:inline-flex items-center gap-1.5"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span>
            {{ $t('Operations workspace') }}
          </span>
          <span
            class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[11px] font-semibold"
          >
            {{ initials(auth.user?.name) }}
          </span>
        </div>
      </header>
      <main
        class="workspace-main mx-auto max-w-[1600px] px-3 py-5 pb-[calc(6rem+env(safe-area-inset-bottom))] sm:px-8 sm:pt-8 lg:pb-8"
      >
        <RouterView :key="route.path" />
      </main>
      <footer
        class="hidden justify-between px-8 pb-5 lg:flex text-[10px] text-slate-400"
      >
        <span>{{ $t('Dispatch · Order operations') }}</span>
        <span>{{ $t('Made for a smoother day.') }}</span>
      </footer>
    </div>
  </div>
  <nav
    v-if="!route.meta.public"
    :inert="open ? true : undefined"
    :aria-label="$t('Quick navigation')"
    class="mobile-bottom-nav fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-slate-200 bg-white/95 px-1 backdrop-blur lg:hidden"
  >
    <RouterLink
      v-for="[label, path, icon] in bottomNav"
      :key="path"
      :to="path"
      :aria-current="route.path.startsWith(path) ? 'page' : undefined"
      class="flex min-h-16 min-w-0 flex-col items-center justify-center gap-1 rounded-lg px-1 text-center text-[10px] leading-tight"
      :class="
        route.path.startsWith(path)
          ? 'text-brand-700 bg-brand-50 font-semibold'
          : 'text-slate-500'
      "
    >
      <Icon :name="icon" :size="21" />
      <span>{{ $t(label) }}</span>
    </RouterLink>
    <button
      class="flex min-h-16 flex-col items-center justify-center gap-1 rounded-lg text-[10px] text-slate-500"
      aria-controls="workspace-navigation"
      :aria-expanded="open"
      @click="open = true"
    >
      <Icon name="Menu" :size="21" />
      {{ $t('More') }}
    </button>
  </nav>
  <div
    class="toast-stack fixed right-3 left-3 sm:left-auto sm:right-4 z-[60] flex sm:max-w-sm flex-col gap-2"
    aria-live="polite"
  >
    <div
      v-for="toast in toasts.messages"
      :key="toast.id"
      class="flex items-start gap-3 rounded-xl border bg-white p-4 shadow-lg"
      :class="
        toast.type === 'error'
          ? 'border-red-200 text-red-700'
          : 'border-brand-100 text-brand-700'
      "
    >
      <Icon :name="toast.type === 'error' ? 'CircleAlert' : 'CircleCheck'" />
      <span>{{ $t(toast.message) }}</span>
    </div>
  </div>
</template>
