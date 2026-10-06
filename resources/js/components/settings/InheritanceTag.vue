<script setup>
import { RotateCcw } from 'lucide-vue-next';

const props = defineProps({
  origin: { type: String, default: 'global' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['reset']);

const labels = {
  church: 'Guardado en esta parroquia',
  diocese: 'Heredado de la diócesis',
  global: 'Valor global',
};

const styles = {
  church: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  diocese: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
  global: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};
</script>

<template>
  <span class="flex items-center gap-2">
    <span
      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
      :class="styles[origin] ?? styles.global"
    >
      {{ labels[origin] ?? labels.global }}
    </span>
    <button
      v-if="origin !== 'global' && !disabled"
      type="button"
      title="Restablecer a valor global"
      class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-2 py-0.5 text-xs text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
      @click="emit('reset')"
    >
      <RotateCcw class="h-3 w-3" />
      Restablecer
    </button>
  </span>
</template>
