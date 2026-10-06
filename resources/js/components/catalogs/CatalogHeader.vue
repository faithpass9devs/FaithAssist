<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, LayoutGrid } from 'lucide-vue-next';

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  backHref: { type: String, default: '/' },
  count: { type: Number, default: null },
  icon: { type: [Object, Function], default: () => LayoutGrid },
});
</script>

<template>
  <div class="mb-6 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex min-w-0 items-center gap-3">
      <Link
        :href="backHref"
        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        aria-label="Regresar"
      >
        <ChevronLeft class="h-4 w-4" />
      </Link>

      <div class="min-w-0">
        <div class="flex min-w-0 flex-wrap items-center gap-2.5">
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-300">
            <component :is="icon" class="h-4 w-4" />
          </span>

          <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-slate-100 sm:text-2xl">
            {{ title }}
          </h1>

          <span
            v-if="count !== null"
            class="inline-flex items-center rounded-full border border-sky-200 bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-300"
          >
            {{ count }}
          </span>
        </div>

        <p v-if="subtitle" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {{ subtitle }}
        </p>
      </div>
    </div>

      <div class="flex w-full items-center gap-2 self-start sm:w-auto sm:self-auto">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
