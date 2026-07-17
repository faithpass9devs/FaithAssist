<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Church, Home, RotateCcw, Save, Search, Users } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';

const props = defineProps({
  children: { type: Array, default: () => [] },
  weekends: { type: Array, default: () => [] },
  masses: { type: Array, default: () => [] },
  levels: { type: Array, default: () => [] },
  municipalities: { type: Array, default: () => [] },
  communities: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ child_id: null, weekend_id: null }) },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('asistencias_manuales.create'));

const codeTerm = ref(props.filters.code ?? '');
const nameTerm = ref(props.filters.name ?? '');
const selectedLevelId = ref(props.filters.level_id ?? null);
const selectedMunicipalityId = ref(props.filters.municipality_id ?? null);
const selectedCommunityId = ref(props.filters.community_id ?? null);
const selectedChildId = ref(props.filters.child_id);
const selectedWeekendId = ref(props.filters.weekend_id ?? null);
const selectedMassIds = ref([]);
let debounce = null;

const availableMasses = computed(() => props.masses ?? []);

const selectedChild = computed(
  () => props.children.find((child) => String(child.id) === String(selectedChildId.value)) ?? null,
);

const selectedWeekend = computed(
  () => props.weekends.find((weekend) => String(weekend.id) === String(selectedWeekendId.value)) ?? null,
);

const selectedLevel = computed(
  () => props.levels.find((level) => String(level.id) === String(selectedLevelId.value)) ?? null,
);

const selectedCommunity = computed(
  () => props.communities.find((community) => String(community.id) === String(selectedCommunityId.value)) ?? null,
);

const filteredMunicipalities = computed(() => {
  if (!selectedCommunityId.value) {
    return props.municipalities;
  }

  const community = props.communities.find(
    (item) => String(item.id) === String(selectedCommunityId.value),
  );

  if (!community?.municipality_id) {
    return props.municipalities;
  }

  return props.municipalities.filter(
    (municipality) => String(municipality.id) === String(community.municipality_id),
  );
});

const filteredCommunities = computed(() => {
  if (!selectedMunicipalityId.value) {
    return props.communities;
  }

  return props.communities.filter(
    (community) => String(community.municipality_id) === String(selectedMunicipalityId.value),
  );
});

const chosenMasses = computed(() =>
  availableMasses.value.filter((mass) => selectedMassIds.value.map(String).includes(String(mass.id))),
);

const suggestedChildren = computed(() => props.children.slice(0, 3));

const hasSuggestedChildren = computed(() => activeFilters.value && suggestedChildren.value.length > 0);

const suggestionsMessage = computed(() => {
  if (!activeFilters.value) {
    return 'Aplica al menos un filtro para ver sugerencias de niños.';
  }

  if (!suggestedChildren.value.length) {
    return 'No se encontraron coincidencias con los filtros seleccionados.';
  }

  return '';
});

const activeFilters = computed(
  () =>
    !!codeTerm.value ||
    !!nameTerm.value ||
    !!selectedLevelId.value ||
    !!selectedMunicipalityId.value ||
    !!selectedCommunityId.value,
);

const reload = () => {
  router.get(
    '/asistencias-manuales',
    {
      code: codeTerm.value || undefined,
      name: nameTerm.value || undefined,
      level_id: selectedLevelId.value || undefined,
      municipality_id: selectedMunicipalityId.value || undefined,
      community_id: selectedCommunityId.value || undefined,
      weekend_id: selectedWeekendId.value || undefined,
    },
    { preserveState: true, replace: true },
  );
};

watch([codeTerm, nameTerm, selectedLevelId, selectedMunicipalityId, selectedCommunityId], () => {
  clearTimeout(debounce);
  debounce = setTimeout(reload, 350);
});

watch(selectedMunicipalityId, () => {
  if (!selectedCommunityId.value) return;

  const stillValid = filteredCommunities.value.some(
    (community) => String(community.id) === String(selectedCommunityId.value),
  );

  if (!stillValid) {
    selectedCommunityId.value = null;
  }
});

watch(selectedCommunityId, (communityId) => {
  if (!communityId) return;

  const community = props.communities.find(
    (item) => String(item.id) === String(communityId),
  );

  if (!community?.municipality_id) return;

  if (String(selectedMunicipalityId.value) !== String(community.municipality_id)) {
    selectedMunicipalityId.value = community.municipality_id;
  }
});

watch(
  () => props.children,
  () => {
    if (!selectedChildId.value) return;

    const exists = props.children.some((child) => String(child.id) === String(selectedChildId.value));

    if (!exists) {
      selectedChildId.value = null;
    }
  },
  { immediate: true },
);

watch(selectedWeekendId, () => {
  selectedMassIds.value = [];
  reload();
});

watch(
  () => props.masses,
  (masses) => {
    const validMassIds = new Set(masses.map((mass) => String(mass.id)));
    selectedMassIds.value = selectedMassIds.value.filter((massId) => validMassIds.has(String(massId)));
  },
  { immediate: true },
);

const clearFilters = () => {
  codeTerm.value = '';
  nameTerm.value = '';
  selectedLevelId.value = null;
  selectedMunicipalityId.value = null;
  selectedCommunityId.value = null;
  selectedMassIds.value = [];
};

const toggleMass = (massId) => {
  const currentIds = selectedMassIds.value.map(String);
  const targetId = String(massId);

  if (currentIds.includes(targetId)) {
    selectedMassIds.value = selectedMassIds.value.filter((value) => String(value) !== targetId);
    return;
  }

  selectedMassIds.value = [...selectedMassIds.value, massId];
};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const loading = ref(false);
const errors = ref({});

const apiFetch = async (url, method, payload = null) => {
  const response = await fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: payload ? JSON.stringify(payload) : undefined,
  });

  const json = await response.json();

  if (!response.ok) {
    throw {
      message: json.message ?? 'No se pudo registrar la asistencia manual.',
      errors: json.errors ?? {},
    };
  }

  return json;
};

const toast = (icon, title) => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon,
    title,
    timer: 2800,
    showConfirmButton: false,
  });
};

const save = async () => {
  if (loading.value || !canCreate.value) return;

  loading.value = true;
  errors.value = {};

  try {
    const json = await apiFetch('/asistencias-manuales', 'POST', {
      child_id: selectedChildId.value,
      weekend_id: selectedWeekendId.value,
      mass_ids: selectedMassIds.value,
    });

    toast('success', json.message);
    selectedMassIds.value = [];
  } catch (error) {
    errors.value = error.errors ?? {};
    toast('error', error.message);
  } finally {
    loading.value = false;
  }
};

const errorFor = (field) => errors.value[field]?.[0] ?? '';

const chooseChild = (childId) => {
  selectedChildId.value = childId;
};
</script>

<template>
  <AppShell :page-title="'Asistencia manual'">
    <CatalogHeader
      title="Asistencia manual"
      subtitle="Registro manual de niños por fin de semana y misas específicas"
      back-href="/"
      :icon="Users"
    >
      <template #actions>
        <Link
          href="/"
          class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
        >
          <Home class="h-4 w-4" />
          Regresar al inicio
        </Link>
      </template>
    </CatalogHeader>

    <div class="mx-auto w-full max-w-7xl space-y-6 pb-6">
      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">
            <Search class="h-4 w-4 text-rose-700 dark:text-rose-400" />
            Filtros de búsqueda
          </h2>

          <button
            v-if="activeFilters"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 transition hover:border-sky-300 hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-900/30 dark:text-sky-200 dark:hover:border-sky-900/50"
            @click="clearFilters"
          >
            <RotateCcw class="h-3.5 w-3.5" />
            Limpiar filtros
          </button>
        </div>

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-5">
          <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40">
            <Search class="h-4 w-4 shrink-0 text-slate-400" />
            <input
              v-model="codeTerm"
              type="text"
              placeholder="Código"
              class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
            />
          </label>

          <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40">
            <Search class="h-4 w-4 shrink-0 text-slate-400" />
            <input
              v-model="nameTerm"
              type="text"
              placeholder="Nombre"
              class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
            />
          </label>

          <select
            v-model="selectedLevelId"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Nivel</option>
            <option v-for="level in levels" :key="level.id" :value="level.id">
              {{ level.name }}
            </option>
          </select>

          <select
            v-model="selectedMunicipalityId"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Municipio</option>
            <option v-for="municipality in filteredMunicipalities" :key="municipality.id" :value="municipality.id">
              {{ municipality.name }}
            </option>
          </select>

          <select
            v-model="selectedCommunityId"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Comunidad</option>
            <option v-for="community in filteredCommunities" :key="community.id" :value="community.id">
              {{ community.name }}
            </option>
          </select>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0 flex-1 space-y-4">
            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">
              <Users class="h-4 w-4 text-sky-700 dark:text-sky-400" />
              Seleccionar niño
            </h2>

            <div v-if="hasSuggestedChildren" class="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-800">
              <div class="flex items-center justify-end">
                <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-900/40 dark:text-sky-200">
                  {{ suggestedChildren.length }} resultados
                </span>
              </div>

              <div class="grid gap-3 md:grid-cols-3">
                <button
                  v-for="child in suggestedChildren"
                  :key="child.id"
                  type="button"
                  class="rounded-2xl border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-sky-300 hover:bg-sky-50 dark:border-slate-700 dark:bg-slate-950/60 dark:hover:border-sky-700 dark:hover:bg-slate-900"
                  :class="String(selectedChildId) === String(child.id) ? 'ring-2 ring-sky-200 dark:ring-sky-800' : ''"
                  @click="chooseChild(child.id)"
                >
                  <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ child.full_name }}</p>
                  <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ child.label }}</p>
                  <div class="mt-3 flex items-center justify-end">
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                      {{ String(selectedChildId) === String(child.id) ? 'Seleccionado' : 'Elegir' }}
                    </span>
                  </div>
                </button>
              </div>
            </div>

            <div v-else class="border-t border-slate-200 pt-3 dark:border-slate-800">
              <p class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ suggestionsMessage }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">
            <CalendarDays class="h-4 w-4 text-rose-700 dark:text-rose-400" />
            Fin de semana y misas
          </h2>
          <span class="text-xs text-slate-400">Selecciona una o varias misas</span>
        </div>

        <div class="space-y-6">
          <div class="space-y-2">
            <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">
              Fin de semana
            </label>
            <select
              v-model="selectedWeekendId"
              class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
            >
              <option :value="null">Selecciona un fin de semana</option>
              <option v-for="weekend in weekends" :key="weekend.id" :value="weekend.id">
                {{ weekend.label }}
              </option>
            </select>
            <p v-if="errorFor('weekend_id')" class="mt-2 text-xs text-red-500">{{ errorFor('weekend_id') }}</p>

          </div>

          <div>
            <div class="mb-3 flex items-center justify-between">
              <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                Misas disponibles
              </label>
              <span class="text-xs text-slate-400">{{ selectedMassIds.length }} seleccionadas</span>
            </div>

            <div v-if="availableMasses.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <label
                v-for="mass in availableMasses"
                :key="mass.id"
                class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-sky-700 dark:hover:bg-slate-900"
                :class="selectedMassIds.map(String).includes(String(mass.id)) ? 'ring-2 ring-sky-200 dark:ring-sky-800' : ''"
              >
                <input
                  :checked="selectedMassIds.map(String).includes(String(mass.id))"
                  type="checkbox"
                  class="checkbox checkbox-sm mt-0.5"
                  @change="toggleMass(mass.id)"
                />

                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ mass.label }}
                  </p>
                  <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ mass.starts_at }} · {{ mass.ends_at || 'Sin fin' }}
                  </p>
                </div>
              </label>
            </div>

            <p v-else class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-400 dark:border-slate-700">
              Selecciona un fin de semana para ver las misas disponibles.
            </p>

            <p v-if="errorFor('mass_ids')" class="mt-2 text-xs text-red-500">{{ errorFor('mass_ids') }}</p>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <p v-if="!canCreate" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
            No tienes permiso para registrar asistencias manuales.
          </p>

          <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-slate-800">
              Niño: {{ selectedChild?.full_name || 'Ninguno' }}
            </span>
            <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-slate-800">
              Fin de semana: {{ selectedWeekend?.label || 'Ninguno' }}
            </span>
            <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-slate-800">
              Misas: {{ selectedMassIds.length }}
            </span>
          </div>

          <button
            type="button"
            class="btn btn-primary gap-1.5 rounded-xl px-5"
            :disabled="loading || !canCreate || !selectedChildId || !selectedWeekendId || selectedMassIds.length === 0"
            @click="save"
          >
            <Save class="h-4 w-4" />
            {{ loading ? 'Registrando...' : 'Registrar asistencias manuales' }}
          </button>
        </div>
      </section>
    </div>
  </AppShell>
</template>
