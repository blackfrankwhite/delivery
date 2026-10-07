<template>
  <div class="card p-12 text-center">
    <h1 class="text-2xl font-semibold">{{ $t('Page not found') }}</h1>
    <p class="mt-3 text-slate-500">{{ $t('This page doesn’t exist.') }}</p>
    <RouterLink class="btn btn-primary mt-6" to="/">
      {{ $t('Back to workspace') }}
    </RouterLink>
  </div>
</template>
