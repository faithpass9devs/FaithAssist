<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { Filter, Pencil, Plus, QrCode, RotateCcw, Search, Trash2, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import QRCode from 'qrcode';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  staff: { type: Object, required: true },
  search: { type: String, default: '' },
  filters: {
    type: Object,
    default: () => ({
      church_id: null,
      municipality_id: null,
      community_id: null,
      staff_type_id: null,
    }),
  },
  churches: { type: Array, default: () => [] },
  municipalities: { type: Array, default: () => [] },
  communities: { type: Array, default: () => [] },
  staffTypes: { type: Array, default: () => [] },
});

const searchTerm = ref(props.search);
const selectedChurch = ref(props.filters.church_id);
const selectedMunicipality = ref(props.filters.municipality_id);
const selectedCommunity = ref(props.filters.community_id);
const selectedStaffType = ref(props.filters.staff_type_id);
const qrStaff = ref(null);
const qrDataUrl = ref('');
let debounce = null;

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const hasPermission = (action) => permissions.value.includes(`personal.${action}`);
const canCreate = computed(() => hasPermission('create'));
const canUpdate = computed(() => hasPermission('update'));
const canDelete = computed(() => hasPermission('delete'));

const availableCommunities = computed(() => {
  if (!selectedMunicipality.value) return props.communities;

  return props.communities.filter(
    (community) => String(community.municipality_id) === String(selectedMunicipality.value),
  );
});

const activeFilters = computed(
  () =>
    !!searchTerm.value ||
    !!selectedChurch.value ||
    !!selectedMunicipality.value ||
    !!selectedCommunity.value ||
    !!selectedStaffType.value,
);

const reload = () => {
  router.get('/personal', {
    search: searchTerm.value || undefined,
    church_id: selectedChurch.value || undefined,
    municipality_id: selectedMunicipality.value || undefined,
    community_id: selectedCommunity.value || undefined,
    staff_type_id: selectedStaffType.value || undefined,
  }, {
    preserveState: true,
    replace: true,
  });
};

watch([searchTerm, selectedChurch, selectedMunicipality, selectedCommunity, selectedStaffType], () => {
  clearTimeout(debounce);
  debounce = setTimeout(reload, 400);
});

watch(selectedMunicipality, () => {
  if (!selectedCommunity.value) return;

  const exists = availableCommunities.value.some(
    (community) => String(community.id) === String(selectedCommunity.value),
  );

  if (!exists) {
    selectedCommunity.value = null;
  }
});

const clearFilters = () => {
  searchTerm.value = '';
  selectedChurch.value = null;
  selectedMunicipality.value = null;
  selectedCommunity.value = null;
  selectedStaffType.value = null;
};

const destroyStaff = (staffRow) => {
  if (!confirm(`Eliminar el registro de ${staffRow.full_name}?`)) return;
  router.delete(`/personal/${staffRow.id}`, { preserveScroll: true });
};

const openQr = async (staffRow) => {
  qrStaff.value = staffRow;
  qrDataUrl.value = await QRCode.toDataURL(staffRow.code, {
    width: 256,
    margin: 2,
  });
};

const closeQr = () => {
  qrStaff.value = null;
  qrDataUrl.value = '';
};

const badgePdfHref = computed(() =>
  qrStaff.value ? `/personal/${qrStaff.value.id}/badge-pdf` : '#',
);
</script>

<template>
  <AppShell :page-title="'Personal'">
    <CatalogHeader
      title="Personal"
      subtitle="Gestión del personal de la iglesia"
      back-href="/"
      :count="staff.total"
      :icon="Users"
    >
      <template #actions>
        <Link
          v-if="canCreate"
          href="/personal/create"
          class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
        >
          <Plus class="h-4 w-4" />
          Nuevo personal
        </Link>
      </template>
    </CatalogHeader>

    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900">
      <div class="mb-4 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">
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
        <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40">
          <Search class="h-4 w-4 shrink-0 text-slate-400" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Buscar por código, nombre, tipo, parroquia o teléfono..."
            class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
          />
        </label>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <select v-model="selectedChurch" class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950">
            <option :value="null">Todas las parroquias</option>
            <option v-for="church in churches" :key="church.id" :value="church.id">{{ church.name }}</option>
          </select>

          <select v-model="selectedMunicipality" class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950">
            <option :value="null">Todos los municipios</option>
            <option v-for="municipality in municipalities" :key="municipality.id" :value="municipality.id">{{ municipality.name }}</option>
          </select>

          <select v-model="selectedCommunity" class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950">
            <option :value="null">Todas las comunidades</option>
            <option v-for="community in availableCommunities" :key="community.id" :value="community.id">{{ community.name }}</option>
          </select>

          <select v-model="selectedStaffType" class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950">
            <option :value="null">Todos los tipos</option>
            <option v-for="staffType in staffTypes" :key="staffType.id" :value="staffType.id">{{ staffType.name }}</option>
          </select>
        </div>
      </div>
    </section>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <table class="table w-full">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
            <th class="px-4 py-3 font-semibold">Código</th>
            <th class="px-4 py-3 font-semibold">Nombre</th>
            <th class="px-4 py-3 font-semibold">Tipo</th>
            <th class="px-4 py-3 font-semibold">Parroquia</th>
            <th class="px-4 py-3 font-semibold">Comunidad</th>
            <th class="px-4 py-3 font-semibold">Teléfono</th>
            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="staffRow in staff.data"
            :key="staffRow.id"
            class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
          >
            <td class="px-4 py-3">
              <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                {{ staffRow.code }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ staffRow.full_name }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ staffRow.staff_type || 'Sin tipo' }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ staffRow.church || '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ staffRow.community || '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              <span v-if="staffRow.phone">{{ `${staffRow.phone_lada || ''}${staffRow.phone}` }}</span>
              <span v-else>Sin teléfono</span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="inline-flex items-center gap-1">
                <a
                  :href="`/personal/${staffRow.id}/badge-pdf?download=1`"
                  class="btn btn-ghost btn-xs text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                  title="Descargar gafete PDF"
                >
                  PDF
                </a>
                <button
                  type="button"
                  class="btn btn-ghost btn-xs text-slate-600 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                  title="Mostrar QR"
                  @click="openQr(staffRow)"
                >
                  <QrCode class="h-3.5 w-3.5" />
                </button>
                <Link
                  v-if="canUpdate"
                  :href="`/personal/${staffRow.id}/edit`"
                  class="btn btn-ghost btn-xs text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-400 dark:hover:bg-sky-950/40"
                  title="Editar registro"
                >
                  <Pencil class="h-3.5 w-3.5" />
                </Link>
                <button
                  v-if="canDelete"
                  type="button"
                  class="btn btn-ghost btn-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40"
                  title="Eliminar registro"
                  @click="destroyStaff(staffRow)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="staff.data.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400 dark:text-slate-500">
              <span v-if="activeFilters">No se encontró personal con los filtros seleccionados.</span>
              <span v-else>No hay personal registrado.</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination :links="staff.links" :from="staff.from" :to="staff.to" :total="staff.total" />

    <div v-if="qrStaff" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="closeQr">
      <div class="w-full max-w-sm rounded-3xl bg-white p-6 text-center shadow-2xl dark:bg-slate-900">
        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100">{{ qrStaff.full_name }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ qrStaff.staff_type || 'Sin tipo' }}</p>
        <p class="mt-1 font-mono text-xs text-slate-500">{{ qrStaff.code }}</p>
        <img v-if="qrDataUrl" :src="qrDataUrl" :alt="`QR ${qrStaff.code}`" class="mx-auto my-5 h-64 w-64" />
        <p class="text-xs text-slate-400">Este QR contiene únicamente el código único del personal.</p>
        <div class="mt-5 flex gap-3">
          <button
            type="button"
            class="btn btn-ghost btn-sm flex-1 rounded-2xl border border-slate-300 dark:border-slate-600"
            @click="closeQr"
          >
            Cerrar
          </button>
          <a
            :href="badgePdfHref"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-outline btn-sm flex-1 rounded-2xl border-sky-300 text-sky-700 hover:border-sky-400 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-300 dark:hover:bg-sky-950/40"
          >
            Ver gafete
          </a>
        </div>
      </div>
    </div>
  </AppShell>
</template>
