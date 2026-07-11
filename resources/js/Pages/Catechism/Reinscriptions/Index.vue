<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, Download, Filter, RotateCcw, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage();
const canCreate = computed(() =>
  (page.props.auth?.permissions ?? []).includes('reinscripciones.create'),
);
const canExport = computed(() =>
  (page.props.auth?.permissions ?? []).includes('reinscripciones.export'),
);
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  children: { type: Object, required: true },
  search: { type: String, default: '' },
  filters: { type: Object, default: () => ({ community_id: null, level_id: null }) },
  communityOptions: { type: Array, default: () => [] },
  levelOptions: { type: Array, default: () => [] },
});

const searchTerm = ref(props.search);
const selectedCommunity = ref(props.filters.community_id);
const selectedLevel = ref(props.filters.level_id);
const debounce = ref(null);

const activeFilters = computed(() => !!searchTerm.value || !!selectedCommunity.value || !!selectedLevel.value);

const reload = () => {
  router.get(
    '/reinscripciones',
    {
      search: searchTerm.value || undefined,
      community_id: selectedCommunity.value || undefined,
      level_id: selectedLevel.value || undefined,
    },
    { preserveState: true, replace: true },
  );
};

watch([searchTerm, selectedCommunity, selectedLevel], () => {
  clearTimeout(debounce.value);
  debounce.value = setTimeout(reload, 400);
});

const clearFilters = () => {
  searchTerm.value = '';
  selectedCommunity.value = null;
  selectedLevel.value = null;
};

const exportReinscriptions = () => {
  if (!canExport.value) return;

  const url = new URL('/reinscripciones/export', window.location.origin);

  if (searchTerm.value) url.searchParams.set('search', searchTerm.value);
  if (selectedCommunity.value) url.searchParams.set('community_id', selectedCommunity.value);
  if (selectedLevel.value) url.searchParams.set('level_id', selectedLevel.value);

  window.location.assign(url.toString());
};
</script>

<template>
  <AppShell :page-title="'Reinscripciones'">
    <CatalogHeader
      title="Reinscripciones"
      subtitle="Movimiento de niños de un nivel a otro durante el periodo activo"
      back-href="/"
      :count="children.total"
      :icon="ArrowLeftRight"
    >
      <template #actions>
        <button
          v-if="canExport"
          type="button"
          class="btn btn-outline btn-sm gap-1.5"
          @click="exportReinscriptions"
        >
          <Download class="h-4 w-4" />
          Exportar Excel
        </button>
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
            placeholder="Buscar por código, nombre o parroquia..."
            class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
          />
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
          <select
            v-model="selectedCommunity"
            class="select select-bordered h-11 w-full rounded-2xl bg-white pr-10 dark:bg-slate-950"
          >
            <option :value="null">Todas las comunidades</option>
            <option v-for="community in communityOptions" :key="community.id" :value="community.id">
              {{ community.name }}
            </option>
          </select>

          <select
            v-model="selectedLevel"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todos los niveles</option>
            <option v-for="level in levelOptions" :key="level.id" :value="level.id">
              {{ level.name }}
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
            <th class="px-4 py-3 font-semibold">Código</th>
            <th class="px-4 py-3 font-semibold">Niño</th>
            <th class="px-4 py-3 font-semibold">Parroquia</th>
            <th class="px-4 py-3 font-semibold">Niveles actuales</th>
            <th v-if="canCreate" class="px-4 py-3 text-right font-semibold">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="child in children.data"
            :key="child.id"
            class="border-b border-slate-100 align-top transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
          >
            <td class="px-4 py-3">
              <span
                class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
              >
                {{ child.code }}
              </span>
            </td>
            <td class="px-4 py-3">
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {{ child.full_name }}
              </p>
              <p class="text-xs text-slate-400">{{ child.community }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ child.church }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ child.levels.map((level) => level.name).join(', ') }}
            </td>
            <td v-if="canCreate" class="px-4 py-3 text-right">
              <Link
                :href="`/reinscripciones/${child.id}/create`"
                class="btn btn-primary btn-xs"
              >
                Reinscribir
              </Link>
            </td>
          </tr>

          <tr v-if="children.data.length === 0">
            <td
              :colspan="canCreate ? 5 : 4"
              class="px-4 py-12 text-center text-sm text-slate-400 dark:text-slate-500"
            >
              <span v-if="activeFilters">No se encontraron niños con los filtros seleccionados.</span>
              <span v-else>No hay niños con niveles activos para reinscribir.</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="children.links"
      :from="children.from"
      :to="children.to"
      :total="children.total"
    />
  </AppShell>
</template>
