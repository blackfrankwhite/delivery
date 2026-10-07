<script>
const modalStack = [];
let originalOverflow;
</script>
<script setup>
import { onMounted, onUnmounted, ref, nextTick } from 'vue';
import Icon from './Icon.vue';
defineProps({ title: String });
const emit = defineEmits(['close']);
const panel = ref(null),
  viewportStyle = ref(undefined);
function resizeViewport() {
  const viewport = window.visualViewport;
  viewportStyle.value =
    window.innerWidth < 768 && viewport
      ? {
          height: `${viewport.height}px`,
          top: `${viewport.offsetTop}px`,
          bottom: 'auto',
        }
      : undefined;
}
let previous, ownPanel;
function key(e) {
  if (modalStack.at(-1) !== ownPanel) return;
  if (e.key === 'Escape') emit('close');
  if (e.key === 'Tab') {
    const els = [
      ...panel.value.querySelectorAll('button,input,select,textarea,a[href]'),
    ].filter((el) => !el.disabled && el.getClientRects().length > 0);
    if (!els.length) {
      e.preventDefault();
      panel.value.focus();
      return;
    }
    const first = els[0],
      last = els.at(-1);
    if (
      e.shiftKey &&
      (document.activeElement === first ||
        document.activeElement === panel.value)
    ) {
      e.preventDefault();
      last?.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first?.focus();
    }
  }
}
onMounted(async () => {
  resizeViewport();
  window.visualViewport?.addEventListener('resize', resizeViewport);
  window.visualViewport?.addEventListener('scroll', resizeViewport);
  window.addEventListener('resize', resizeViewport);
  previous = document.activeElement;
  if (!modalStack.length) originalOverflow = document.body.style.overflow;
  ownPanel = panel.value;
  modalStack.push(ownPanel);
  document.body.style.overflow = 'hidden';
  document.addEventListener('keydown', key);
  await nextTick();
  // Avoid opening the phone keyboard before the user chooses a field.
  panel.value.focus({ preventScroll: true });
});
onUnmounted(() => {
  window.visualViewport?.removeEventListener('resize', resizeViewport);
  window.visualViewport?.removeEventListener('scroll', resizeViewport);
  window.removeEventListener('resize', resizeViewport);
  const index = modalStack.indexOf(ownPanel);
  if (index !== -1) modalStack.splice(index, 1);
  if (!modalStack.length) document.body.style.overflow = originalOverflow;
  document.removeEventListener('keydown', key);
  previous?.focus();
});
</script>
<template>
  <Teleport to="body">
    <div
      :style="viewportStyle"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 backdrop-blur-sm md:p-4"
      @click.self="$emit('close')"
    >
      <section
        ref="panel"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        :aria-label="$t(title)"
        class="flex h-full w-full max-w-xl flex-col bg-white shadow-xl md:h-auto md:max-h-[90dvh] md:rounded-2xl"
      >
        <div
          class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 [padding-top:max(0.75rem,env(safe-area-inset-top))] md:px-6 md:py-4"
        >
          <h2 class="text-lg font-semibold">{{ $t(title) }}</h2>
          <button
            class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100"
            :aria-label="$t('Close dialog')"
            @click="$emit('close')"
          >
            <Icon name="X" />
          </button>
        </div>
        <div
          class="modal-body min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 md:p-6"
        >
          <slot />
        </div>
      </section>
    </div>
  </Teleport>
</template>
