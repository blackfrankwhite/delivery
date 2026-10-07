<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuth } from '../stores/auth';
import { errorMessage } from '../api';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';
import Icon from '../components/Icon.vue';
import FormField from '../components/FormField.vue';
const email = ref(''),
  password = ref(''),
  busy = ref(false),
  error = ref(''),
  errors = ref({}),
  auth = useAuth(),
  router = useRouter(),
  route = useRoute();
async function submit() {
  busy.value = true;
  error.value = '';
  errors.value = {};
  try {
    await auth.login(email.value, password.value);
    const redirect = route.query.redirect;
    await router.push(
      typeof redirect === 'string' &&
        redirect.startsWith('/') &&
        !redirect.startsWith('//') &&
        redirect !== '/login'
        ? redirect
        : auth.driver
          ? '/driver/today'
          : '/dashboard',
    );
  } catch (e) {
    error.value = errorMessage(e);
    errors.value = e.response?.data.errors || {};
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <div class="grid min-h-dvh lg:grid-cols-2">
    <div
      class="hidden flex-col justify-between bg-brand-700 p-14 text-white lg:flex"
    >
      <div class="flex items-center gap-3 text-2xl font-bold">
        <Icon name="Truck" :size="32" />
        dispatch.
      </div>
      <div>
        <p class="mb-5 text-xs uppercase tracking-widest text-emerald-200">
          {{ $t('A clearer day ahead') }}
        </p>
        <h1 class="max-w-lg text-5xl leading-tight font-semibold !text-white">
          {{ $t('Less chasing.') }}
          <br />
          {{ $t('More delivering.') }}
        </h1>
        <p class="mt-6 max-w-md text-base leading-7 text-emerald-100/80">
          {{
            $t(
              'Bring your orders, drivers, and daily routes together in one simple workspace.',
            )
          }}
        </p>
        <div class="mt-10 flex gap-4">
          <div class="rounded-xl border border-white/20 bg-white/5 p-5">
            <Icon name="PackageCheck" :size="26" />
            <p class="mt-3 text-sm">{{ $t('Clear order progress') }}</p>
          </div>
          <div class="rounded-xl border border-white/20 bg-white/5 p-5">
            <Icon name="Route" :size="26" />
            <p class="mt-3 text-sm">{{ $t('Organized daily routes') }}</p>
          </div>
        </div>
      </div>
      <p class="text-xs text-emerald-200/70">
        {{ $t('Order operations, without the guesswork.') }}
      </p>
    </div>
    <div
      class="flex items-center justify-center px-4 py-6 [padding-top:max(1.5rem,env(safe-area-inset-top))] [padding-bottom:max(1.5rem,env(safe-area-inset-bottom))] sm:px-6"
    >
      <div class="w-full max-w-sm">
        <div class="mb-6 flex justify-end"><LanguageSwitcher /></div>
        <div
          class="mb-10 flex items-center gap-2 text-xl font-bold text-brand-600 lg:hidden"
        >
          <Icon name="Truck" />
          dispatch.
        </div>
        <p class="eyebrow mb-3">{{ $t('Welcome to Dispatch') }}</p>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
          {{ $t('Sign in to your workspace') }}
        </h1>
        <p class="mt-3 mb-8 text-slate-500">
          {{ $t('Your orders are waiting. Let’s get moving.') }}
        </p>
        <form class="space-y-5" @submit.prevent="submit">
          <FormField :label="$t('Email address')" :error="errors.email">
            <input
              v-model="email"
              class="field"
              type="email"
              autocomplete="username"
              required
              placeholder="you@company.com"
            />
          </FormField>
          <FormField :label="$t('Password')" :error="errors.password">
            <input
              v-model="password"
              class="field"
              type="password"
              autocomplete="current-password"
              required
              :placeholder="$t('Enter your password')"
            />
          </FormField>
          <p v-if="error" class="text-sm text-red-600" role="alert">
            {{ $t(error) }}
          </p>
          <button class="btn btn-primary w-full" :disabled="busy">
            {{ $t(busy ? 'Signing in…' : 'Sign in') }}
            <Icon name="ArrowRight" :size="16" />
          </button>
        </form>
        <p class="mt-6 text-center text-xs text-slate-400">
          {{ $t('Need access? Contact your administrator.') }}
        </p>
      </div>
    </div>
  </div>
</template>
