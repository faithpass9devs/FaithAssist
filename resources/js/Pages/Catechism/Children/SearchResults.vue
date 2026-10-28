<script setup>
import { Link } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';

defineProps({
  query: { type: String, required: true },
  results: { type: Array, default: () => [] },
});
</script>

<template>
  <AppShell page-title="Resultados de búsqueda">
    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <h1 class="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
        <Search class="h-5 w-5 text-sky-600 dark:text-sky-400" />
        Coincidencias para "{{ query }}"
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Se encontraron {{ results.length }} niño{{ results.length !== 1 ? 's' : '' }}.
      </p>
    </section>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <table class="table w-full">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
            <th class="px-4 py-3 font-semibold">Código</th>
            <th class="px-4 py-3 font-semibold">Nombre</th>
            <th class="px-4 py-3 font-semibold">Iglesia</th>
            <th class="px-4 py-3 font-semibold">Comunidad</th>
            <th class="px-4 py-3 font-semibold">Niveles</th>
            <th class="px-4 py-3 text-right font-semibold">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="child in results"
            :key="child.id"
            class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
          >
            <td class="px-4 py-3">
              <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                {{ child.code }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ child.full_name }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ child.church || '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ child.community || '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              <span v-if="child.levels?.length">{{ child.levels.map((level) => level.name).join(', ') }}</span>
              <span v-else>Sin nivel</span>
            </td>
            <td class="px-4 py-3 text-right">
              <Link
                :href="`/children/${child.id}/panel`"
                class="btn btn-sm rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
              >
                Ver panel
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppShell>
</template>
