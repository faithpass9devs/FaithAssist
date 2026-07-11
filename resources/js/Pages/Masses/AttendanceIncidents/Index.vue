<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ClipboardCheck, Filter, Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../../components/forms/UnderlineField.vue';
import UnderlineSection from '../../../components/forms/UnderlineSection.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  incidents: { type: Object, required: true },
  weekends: { type: Array, default: () => [] },
  children: { type: Array, default: () => [] },
  incidenceTypes: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  search: { type: String, default: '' },
  filters: { type: Object, default: () => ({ weekend_id: null, child_id: null, status: null }) },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('incidencias_asistencia.create'));
const canUpdate = computed(() => permissions.value.includes('incidencias_asistencia.update'));
const canDelete = computed(() => permissions.value.includes('incidencias_asistencia.delete'));

const rows = ref([...props.incidents.data]);
watch(
  () => props.incidents.data,
  (data) => {
    rows.value = [...data];
  },
);

const searchTerm = ref(props.search);
const selectedWeekend = ref(props.filters.weekend_id);
const selectedChild = ref(props.filters.child_id);
const selectedStatus = ref(props.filters.status);
let debounce = null;
const activeFilters = computed(
  () => !!searchTerm.value || !!selectedWeekend.value || !!selectedChild.value || !!selectedStatus.value,
);

const reload = () => {
  router.get(
    '/incidencias-asistencia',
    {
      search: searchTerm.value || undefined,
      weekend_id: selectedWeekend.value || undefined,
      child_id: selectedChild.value || undefined,
      status: selectedStatus.value || undefined,
    },
    { preserveState: true, replace: true },
  );
};

watch([searchTerm, selectedWeekend, selectedChild, selectedStatus], () => {
  clearTimeout(debounce);
  debounce = setTimeout(reload, 400);
});

const clearFilters = () => {
  searchTerm.value = '';
  selectedWeekend.value = null;
  selectedChild.value = null;
  selectedStatus.value = null;
};

const blankForm = () => ({
  weekend_id: '',
  child_id: '',
  incidence_type_id: '',
  description: '',
  status: 'active',
});

const form = reactive(blankForm());
const editingId = ref(null);
const loading = ref(false);
const errors = ref({});
const generalError = ref('');

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const resetForm = () => {
  Object.assign(form, blankForm());
  editingId.value = null;
  errors.value = {};
  generalError.value = '';
};

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
      message: json.message ?? 'No se pudo guardar la incidencia.',
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
    timer: 2500,
    showConfirmButton: false,
  });
};

const save = async () => {
  if (loading.value) return;
  errors.value = {};
  generalError.value = '';
  loading.value = true;

  try {
    const payload = { ...form };
    const json = editingId.value
      ? await apiFetch(`/incidencias-asistencia/${editingId.value}`, 'PUT', payload)
      : await apiFetch('/incidencias-asistencia', 'POST', payload);

    const index = rows.value.findIndex((row) => row.id === json.data.id);
    if (index === -1) {
      rows.value = [json.data, ...rows.value];
    } else {
      rows.value[index] = json.data;
    }

    toast('success', json.message);
    resetForm();
  } catch (error) {
    errors.value = error.errors ?? {};
    generalError.value = Object.keys(errors.value).length ? '' : error.message;
    toast('error', error.message);
  } finally {
    loading.value = false;
  }
};

const edit = (incident) => {
  if (!canUpdate.value) return;
  editingId.value = incident.id;
  Object.assign(form, {
    weekend_id: incident.weekend_id,
    child_id: incident.child_id,
    incidence_type_id: incident.incidence_type_id,
    description: incident.description,
    status: incident.status,
  });
  errors.value = {};
  generalError.value = '';
};

const destroyIncident = async (incident) => {
  if (!canDelete.value) return;

  const result = await Swal.fire({
    title: 'Eliminar incidencia',
    text: 'La asistencia/falta se conservara; solo se eliminara la justificacion.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  });

  if (!result.isConfirmed) return;

  try {
    const json = await apiFetch(`/incidencias-asistencia/${incident.id}`, 'DELETE');
    rows.value = rows.value.filter((row) => row.id !== incident.id);
    toast('success', json.message);
  } catch (error) {
    toast('error', error.message);
  }
};

const errorFor = (field) => errors.value[field]?.[0] ?? '';

const selectedIncidenceTypeDescription = computed(
  () =>
    props.incidenceTypes.find((type) => Number(type.id) === Number(form.incidence_type_id))
      ?.description ?? '',
);

const weekendOptions = computed(() =>
  props.weekends.map((weekend) => ({
    value: weekend.id,
    label: weekend.label,
  })),
);

const childOptions = computed(() =>
  props.children.map((child) => ({
    value: child.id,
    label: child.label,
  })),
);

const incidenceTypeOptions = computed(() =>
  props.incidenceTypes.map((type) => ({
    value: type.id,
    label: type.name,
  })),
);
</script>

<template>
  <AppShell :page-title="'Incidencias de Asistencia'">
    <CatalogHeader
      title="Incidencias de Asistencia"
      subtitle="Justifica faltas por alumno y fin de semana sin cambiar el registro original"
      back-href="/"
      :count="incidents.total"
      :icon="ClipboardCheck"
    >
      <template #actions>
        <div v-if="canCreate || editingId" class="flex items-center gap-2">
          <button
            type="button"
            class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:bg-slate-800"
            @click="resetForm"
          >
            Limpiar
          </button>

          <button
            type="button"
            class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loading"
            @click="save"
          >
            <Plus v-if="!editingId" class="h-4 w-4" />
            <Pencil v-else class="h-4 w-4" />
            {{ editingId ? 'Actualizar' : 'Crear incidencia' }}
          </button>
        </div>
      </template>
    </CatalogHeader>

    <section
      v-if="canCreate || editingId"
      class="mb-6 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-8"
    >
      <div class="space-y-9">
        <UnderlineSection :title="editingId ? 'Editar incidencia' : 'Nueva incidencia'">
          <p class="-mt-2 text-sm font-semibold text-slate-500 dark:text-slate-400">
            Si faltan registros de asistencia, se crearan como falta; la justificacion queda separada.
          </p>

          <div class="grid gap-x-9 gap-y-7 md:grid-cols-2">
            <UnderlineField
              v-model="form.weekend_id"
              label="Fin de semana"
              as="select"
              placeholder="Selecciona un fin de semana"
              :options="weekendOptions"
              :error="errorFor('weekend_id')"
              number-value
              required
            />

            <UnderlineField
              v-model="form.child_id"
              label="Alumno"
              as="select"
              placeholder="Selecciona un alumno"
              :options="childOptions"
              :error="errorFor('child_id')"
              number-value
              required
            />

            <div>
              <UnderlineField
                v-model="form.incidence_type_id"
                label="Tipo de incidencia"
                as="select"
                placeholder="Selecciona un tipo"
                :options="incidenceTypeOptions"
                :error="errorFor('incidence_type_id')"
                number-value
                required
              />
              <p v-if="selectedIncidenceTypeDescription" class="mt-1 text-xs font-semibold text-slate-400">
                {{ selectedIncidenceTypeDescription }}
              </p>
            </div>

            <UnderlineField
              v-model="form.status"
              label="Estatus"
              as="select"
              :options="statusOptions"
            />

            <div class="md:col-span-2">
              <UnderlineField
                v-model="form.description"
                label="Descripcion de la incidencia"
                as="textarea"
                placeholder="Describe la justificacion de la falta"
                :error="errorFor('description')"
                required
              />
            </div>
          </div>
        </UnderlineSection>

      </div>

      <p v-if="generalError" class="mt-3 text-sm text-red-500">{{ generalError }}</p>
    </section>

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
          class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 transition hover:border-sky-300 hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-900/30 dark:text-sky-200 dark:hover:bg-sky-900/50"
          @click="clearFilters"
        >
          <RotateCcw class="h-3.5 w-3.5" />
          Limpiar filtros
        </button>
      </div>

      <div class="grid gap-3 md:grid-cols-[1.65fr_1fr] xl:grid-cols-[1.8fr_1fr_1fr_0.9fr]">
        <label
          class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40 xl:col-span-1"
        >
          <Search class="h-4 w-4 shrink-0 text-slate-400" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Buscar por alumno, codigo o tipo..."
            class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
          />
        </label>

        <select
          v-model="selectedWeekend"
          class="select select-bordered h-10 w-full rounded-xl bg-white dark:bg-slate-950"
        >
          <option :value="null">Todos los fines de semana</option>
          <option v-for="weekend in weekends" :key="weekend.id" :value="weekend.id">
            {{ weekend.label }}
          </option>
        </select>

        <select
          v-model="selectedChild"
          class="select select-bordered h-10 w-full rounded-xl bg-white dark:bg-slate-950"
        >
          <option :value="null">Todos los alumnos</option>
          <option v-for="child in children" :key="child.id" :value="child.id">
            {{ child.label }}
          </option>
        </select>

        <select
          v-model="selectedStatus"
          class="select select-bordered h-10 w-full rounded-xl bg-white dark:bg-slate-950"
        >
          <option :value="null">Todo estatus</option>
          <option v-for="status in statusOptions" :key="status.value" :value="status.value">
            {{ status.label }}
          </option>
        </select>
      </div>
    </section>

    <div
      class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <table class="table w-full">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950">
            <th>Alumno</th>
            <th>Fin de semana</th>
            <th>Parroquia</th>
            <th>Tipo</th>
            <th>Descripcion</th>
            <th>Estatus</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="incident in rows" :key="incident.id">
            <td>
              <div class="font-semibold">{{ incident.child_name }}</div>
              <div class="font-mono text-xs text-slate-400">{{ incident.child_code }}</div>
            </td>
            <td>{{ incident.weekend }}</td>
            <td>{{ incident.church }}</td>
            <td>
              <div>{{ incident.incidence_type }}</div>
              <div v-if="incident.incidence_type_description" class="text-xs text-slate-400">
                {{ incident.incidence_type_description }}
              </div>
            </td>
            <td class="max-w-md whitespace-pre-wrap">{{ incident.description }}</td>
            <td>
              <span class="badge badge-sm" :class="incident.status === 'active' ? 'badge-success' : 'badge-secondary'">
                {{ incident.status }}
              </span>
            </td>
            <td class="text-right">
              <button
                v-if="canUpdate"
                type="button"
                class="btn btn-ghost btn-xs text-sky-600"
                @click="edit(incident)"
              >
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button
                v-if="canDelete"
                type="button"
                class="btn btn-ghost btn-xs text-red-600"
                @click="destroyIncident(incident)"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="7" class="py-10 text-center text-sm text-slate-400">
              No hay incidencias registradas.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="incidents.links"
      :from="incidents.from"
      :to="incidents.to"
      :total="incidents.total"
    />
  </AppShell>
</template>
