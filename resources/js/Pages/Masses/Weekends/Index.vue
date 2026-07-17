<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  weekends: { type: Object, required: true },
  search: { type: String, default: '' },
});

const searchTerm = ref(props.search);
let debounce = null;

watch(searchTerm, (value) => {
  clearTimeout(debounce);
  debounce = setTimeout(() => {
    router.get(
      '/fines-semana-misas',
      { search: value || undefined },
      { preserveState: true, replace: true },
    );
  }, 400);
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('weekends.create'));
const canUpdate = computed(() => permissions.value.includes('weekends.update'));
const canDelete = computed(() => permissions.value.includes('weekends.delete'));

const destroyWeekend = (weekend) => {
  if (!confirm(`Eliminar el fin de semana ${weekend.name || weekend.starts_at}?`)) return;
  router.delete(`/fines-semana-misas/${weekend.id}`, { preserveScroll: true });
};
</script>

<template>
  <AppShell :page-title="'Fines de semana de misas'">
    <CatalogHeader
      title="Fines de semana de misas"
      subtitle="Gestión de fines de semana por parroquia"
      back-href="/"
      :count="weekends.total"
      :icon="CalendarDays"
    />

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <label
        class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 sm:w-96 dark:border-slate-700 dark:bg-slate-900 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40"
      >
        <Search class="h-4 w-4 shrink-0 text-slate-400" />
        <input
          v-model="searchTerm"
          type="text"
          placeholder="Buscar por nombre o parroquia..."
          class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
        />
        <button
          v-if="searchTerm"
          type="button"
          class="text-slate-400 hover:text-slate-600"
          @click="searchTerm = ''"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </label>

      <Link
        v-if="canCreate"
        href="/fines-semana-misas/create"
        class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
      >
        <Plus class="h-4 w-4" />
        Nuevo
      </Link>
    </div>

    <div
      class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <table class="table w-full">
        <thead>
          <tr
            class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950"
          >
            <th>Nombre</th>
            <th>Parroquia</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Estatus</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="weekend in weekends.data" :key="weekend.id">
            <td class="font-semibold">{{ weekend.name || 'Sin nombre' }}</td>
            <td>{{ weekend.church }}</td>
            <td>{{ weekend.starts_at }}</td>
            <td>{{ weekend.ends_at }}</td>
            <td>
              <span
                class="badge badge-sm"
                :class="
                  weekend.status === 'in_progress'
                    ? 'badge-warning'
                    : weekend.status === 'completed'
                      ? 'badge-success'
                      : 'badge-info'
                "
              >
                {{ weekend.status }}
              </span>
            </td>
            <td class="text-right">
              <Link
                v-if="canUpdate"
                :href="`/fines-semana-misas/${weekend.id}/edit`"
                class="btn btn-ghost btn-xs text-sky-600"
              >
                <Pencil class="h-3.5 w-3.5" />
              </Link>
              <button
                v-if="canDelete"
                type="button"
                class="btn btn-ghost btn-xs text-red-600"
                @click="destroyWeekend(weekend)"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </td>
          </tr>
          <tr v-if="weekends.data.length === 0">
            <td colspan="6" class="py-10 text-center text-sm text-slate-400">
              No hay fines de semana registrados.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="weekends.links"
      :from="weekends.from"
      :to="weekends.to"
      :total="weekends.total"
    />
  </AppShell>
</template>
