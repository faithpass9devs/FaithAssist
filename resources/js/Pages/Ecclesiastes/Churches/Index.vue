<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { Filter, Home, Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import { useCatalogCrud } from '../../../composables/useCatalogCrud';

const props = defineProps({
  churches: { type: Object, required: true },
  search: { type: String, default: '' },
  filters: { type: Object, default: () => ({ municipality_id: null, deanery_id: null, status: null }) },
  municipalities: { type: Array, default: () => [] },
  deaneries: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
});

const page = usePage();
const rows = ref([...props.churches.data]);
const searchTerm = ref(props.search);
const selectedMunicipality = ref(props.filters.municipality_id);
const selectedDeanery = ref(props.filters.deanery_id);
const selectedStatus = ref(props.filters.status);
const debounce = ref(null);

const { loading, deleteRow } = useCatalogCrud({
  baseUrl: '/parroquias',
  storeUrl: '/parroquias',
});

const permissions = computed(() => page.props.auth?.permissions ?? []);
const fullScopeAccess = computed(() => page.props.auth?.scope?.full_access ?? {});
const activeFilters = computed(
  () => !!searchTerm.value || !!selectedMunicipality.value || !!selectedDeanery.value || !!selectedStatus.value,
);
const hasPermission = (action) => permissions.value.includes(`parroquias.${action}`);
const canCreate = computed(
  () => hasPermission('create') && fullScopeAccess.value?.parroquias === true,
);
const canUpdate = computed(() => hasPermission('update'));
const canDelete = computed(() => hasPermission('delete'));
const showActions = computed(() => canUpdate.value || canDelete.value);

const statusLabels = {
  active: 'Activo',
  inactive: 'Inactivo',
};

watch(
  () => props.churches.data,
  (newData) => {
    rows.value = [...newData];
  },
);

const reload = () => {
  router.get(
    '/parroquias',
    {
      search: searchTerm.value || undefined,
      municipality_id: selectedMunicipality.value || undefined,
      deanery_id: selectedDeanery.value || undefined,
      status: selectedStatus.value || undefined,
      page: 1,
    },
    { preserveState: true, replace: true },
  );
};

watch([searchTerm, selectedMunicipality, selectedDeanery, selectedStatus], () => {
  clearTimeout(debounce.value);
  debounce.value = setTimeout(reload, 400);
});

const clearFilters = () => {
  searchTerm.value = '';
  selectedMunicipality.value = null;
  selectedDeanery.value = null;
  selectedStatus.value = null;
};

const destroyChurch = async (church) => {
  const deleted = await deleteRow(church);

  if (deleted) {
    rows.value = rows.value.filter((row) => row.id !== church.id);
  }
};
</script>

<template>
  <AppShell :page-title="'Parroquias'">
    <CatalogHeader
      title="Parroquias"
      subtitle="Catálogo de parroquias"
      back-href="/"
      :count="churches.total"
      :icon="Home"
    >
      <template #actions>
        <Link v-if="canCreate" href="/parroquias/create" class="btn btn-primary btn-sm gap-1.5">
          <Plus class="h-4 w-4" />
          Nueva parroquia
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
        <button
          v-if="activeFilters"
          type="button"
          class="inline-flex items-center gap-1.5 self-start rounded-xl border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 transition hover:border-sky-300 hover:bg-sky-100 sm:self-auto dark:border-sky-900/60 dark:bg-sky-900/30 dark:text-sky-200 dark:hover:bg-sky-900/50"
          @click="clearFilters"
        >
          <RotateCcw class="h-3.5 w-3.5" />
          Limpiar filtros
        </button>
      </div>

      <div class="grid gap-4">
        <label
          class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40"
        >
          <Search class="h-4 w-4 shrink-0 text-slate-400" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Buscar por nombre de parroquia..."
            class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
          />
        </label>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <select
            v-model="selectedMunicipality"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todos los municipios</option>
            <option v-for="municipality in municipalities" :key="municipality.id" :value="municipality.id">
              {{ municipality.name }}
            </option>
          </select>

          <select
            v-model="selectedDeanery"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todos los decanatos</option>
            <option v-for="deanery in deaneries" :key="deanery.id" :value="deanery.id">
              {{ deanery.name }}
            </option>
          </select>

          <select
            v-model="selectedStatus"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todos los estados</option>
            <option v-for="status in statuses" :key="status.value" :value="status.value">
              {{ status.label }}
            </option>
          </select>
        </div>
      </div>
    </section>

    <div
      class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <table class="table w-full">
        <thead>
          <tr
            class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400"
          >
            <th class="px-4 py-3 font-semibold">Nombre</th>
            <th class="px-4 py-3 font-semibold">Municipio</th>
            <th class="px-4 py-3 font-semibold">Decanato</th>
            <th class="px-4 py-3 font-semibold">Contacto</th>
            <th class="px-4 py-3 font-semibold">Estado</th>
            <th v-if="showActions" class="px-4 py-3 text-right font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="church in rows"
            :key="church.id"
            class="border-b border-slate-100 align-top transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
          >
            <td class="px-4 py-3">
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {{ church.name }}
              </p>
              <p class="text-xs text-slate-400">
                {{ church.alias || 'Sin alias' }}
              </p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ church.municipality || 'Sin municipio' }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ church.deanery || 'Sin decanato' }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              <p>{{ church.email || 'Sin correo' }}</p>
              <p>{{ church.phone || 'Sin teléfono' }}</p>
            </td>
            <td class="px-4 py-3">
              <span
                class="badge"
                :class="church.status === 'active' ? 'badge-success' : 'badge-error'"
              >
                {{ statusLabels[church.status] ?? church.status }}
              </span>
            </td>
            <td v-if="showActions" class="px-4 py-3">
              <div class="flex justify-end gap-2">
                <Link
                  v-if="canUpdate"
                  :href="`/parroquias/${church.id}/edit`"
                  class="btn btn-ghost btn-xs gap-1"
                >
                  <Pencil class="h-3.5 w-3.5" />
                  Editar
                </Link>
                <button
                  v-if="canDelete"
                  type="button"
                  class="btn btn-error btn-outline btn-xs gap-1"
                  :disabled="loading"
                  @click="destroyChurch(church)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                  Eliminar
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="rows.length === 0">
            <td
              :colspan="showActions ? 6 : 5"
              class="px-4 py-12 text-center text-sm text-slate-400 dark:text-slate-500"
            >
              <span v-if="activeFilters"
                >No se encontraron parroquias con los filtros seleccionados.</span
              >
              <span v-else>No hay parroquias registradas.</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="churches.links"
      :from="churches.from"
      :to="churches.to"
      :total="churches.total"
    />
  </AppShell>
</template>
