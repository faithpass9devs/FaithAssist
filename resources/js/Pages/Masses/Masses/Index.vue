<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Church, Filter, Pencil, Plus, QrCode, RotateCcw, Search, Trash2 } from 'lucide-vue-next';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  masses: { type: Object, required: true },
  weekends: { type: Array, default: () => [] },
  search: { type: String, default: '' },
  filters: { type: Object, default: () => ({ weekend_id: null }) },
});

const searchTerm = ref(props.search);
const selectedWeekend = ref(props.filters.weekend_id);
let debounce = null;

const activeFilters = computed(() => !!searchTerm.value || !!selectedWeekend.value);

const reload = () => {
  router.get(
    '/misas',
    {
      search: searchTerm.value || undefined,
      weekend_id: selectedWeekend.value || undefined,
    },
    { preserveState: true, replace: true },
  );
};

watch([searchTerm, selectedWeekend], () => {
  clearTimeout(debounce);
  debounce = setTimeout(reload, 400);
});

const clearFilters = () => {
  searchTerm.value = '';
  selectedWeekend.value = null;
};

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('masses.create'));
const canUpdate = computed(() => permissions.value.includes('masses.update'));
const canDelete = computed(() => permissions.value.includes('masses.delete'));
const canOpenAttendance = computed(
  () =>
    permissions.value.includes('mass_attendance.read') ||
    permissions.value.includes('mass_attendance.scan'),
);

const destroyMass = (mass) => {
  if (!confirm(`Eliminar la misa ${mass.name}?`)) return;
  router.delete(`/misas/${mass.id}`, { preserveScroll: true });
};
</script>

<template>
  <AppShell :page-title="'Misas'">
    <CatalogHeader
      title="Misas"
      subtitle="Gestión de misas por fin de semana y ubicación"
      back-href="/"
      :count="masses.total"
      :icon="Church"
    >
      <template #actions>
        <Link
          v-if="canCreate"
          href="/misas/create"
          class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
        >
          <Plus class="h-4 w-4" />
          Nueva misa
        </Link>
      </template>
    </CatalogHeader>

    <section
      class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="mb-4 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h2
          class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300"
        >
          <Filter class="h-4 w-4 text-rose-700 dark:text-rose-400" />
          Filtros
        </h2>

        <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
          <button
            v-if="activeFilters"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 transition hover:border-sky-300 hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-900/30 dark:text-sky-200 dark:hover:bg-sky-900/50"
            @click="clearFilters"
          >
            <RotateCcw class="h-3.5 w-3.5" />
            Limpiar filtros
          </button>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-[1.65fr_1fr]">
        <label
          class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40"
        >
          <Search class="h-4 w-4 shrink-0 text-slate-400" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Buscar por misa, parroquia o capilla..."
            class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
          />
        </label>

        <select
          v-model="selectedWeekend"
          class="select select-bordered h-10 w-full rounded-xl bg-white dark:bg-slate-950"
        >
          <option :value="null">Todos los fines de semana</option>
          <option v-for="weekend in weekends" :key="weekend.id" :value="weekend.id">
            {{ weekend.name }} · {{ weekend.church }}
          </option>
        </select>
      </div>
    </section>

    <div
      class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <table class="table w-full">
        <thead>
          <tr
            class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950"
          >
            <th>Misa</th>
            <th>Fin de semana</th>
            <th>Ubicación</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Captura</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="mass in masses.data" :key="mass.id">
            <td class="font-semibold">{{ mass.name }}</td>
            <td>{{ mass.weekend }}</td>
            <td>{{ mass.location }}</td>
            <td>{{ mass.starts_at }}</td>
            <td>{{ mass.ends_at || 'Pendiente' }}</td>
            <td>
              <span
                class="badge badge-sm"
                :class="
                  mass.attendance_status === 'in_progress'
                    ? 'badge-warning'
                    : mass.attendance_status === 'completed'
                      ? 'badge-success'
                      : 'badge-info'
                "
              >
                {{ mass.attendance_status }}
              </span>
            </td>
            <td class="whitespace-nowrap text-right">
              <div class="inline-flex items-center justify-end gap-1">
                <Link
                  v-if="canOpenAttendance"
                  :href="`/misas/${mass.id}/asistencias`"
                  class="btn btn-ghost btn-xs text-purple-600"
                  title="Asistencias"
                >
                  <QrCode class="h-3.5 w-3.5" />
                </Link>
                <Link
                  v-if="canUpdate"
                  :href="`/misas/${mass.id}/edit`"
                  class="btn btn-ghost btn-xs text-sky-600"
                  title="Editar"
                >
                  <Pencil class="h-3.5 w-3.5" />
                </Link>
                <button
                  v-if="canDelete"
                  type="button"
                  class="btn btn-ghost btn-xs text-red-600"
                  title="Eliminar"
                  @click="destroyMass(mass)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="masses.data.length === 0">
            <td colspan="7" class="py-10 text-center text-sm text-slate-400">
              No hay misas registradas.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="masses.links"
      :from="masses.from"
      :to="masses.to"
      :total="masses.total"
    />
  </AppShell>
</template>
