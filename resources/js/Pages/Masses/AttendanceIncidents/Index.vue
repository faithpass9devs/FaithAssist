<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ClipboardCheck, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
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
</script>

<template>
  <AppShell :page-title="'Incidencias de Asistencia'">
    <CatalogHeader
      title="Incidencias de Asistencia"
      subtitle="Justifica faltas por alumno y fin de semana sin cambiar el registro original"
      back-href="/"
      :count="incidents.total"
      :icon="ClipboardCheck"
    />

    <section
      v-if="canCreate || editingId"
      class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200">
            {{ editingId ? 'Editar incidencia' : 'Nueva incidencia' }}
          </h2>
          <p class="mt-1 text-xs text-slate-400">
            Si faltan registros de asistencia, se crearan como falta; la justificacion queda separada.
          </p>
        </div>
        <button v-if="editingId" type="button" class="btn btn-ghost btn-sm" @click="resetForm">
          Cancelar edicion
        </button>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <label class="form-control">
          <span class="label-text">Fin de semana</span>
          <select v-model="form.weekend_id" class="select select-bordered" :class="{ 'select-error': errorFor('weekend_id') }">
            <option value="" disabled>Selecciona un fin de semana</option>
            <option v-for="weekend in weekends" :key="weekend.id" :value="weekend.id">
              {{ weekend.label }}
            </option>
          </select>
          <span v-if="errorFor('weekend_id')" class="mt-1 text-xs text-red-500">{{ errorFor('weekend_id') }}</span>
        </label>

        <label class="form-control">
          <span class="label-text">Alumno</span>
          <select v-model="form.child_id" class="select select-bordered" :class="{ 'select-error': errorFor('child_id') }">
            <option value="" disabled>Selecciona un alumno</option>
            <option v-for="child in children" :key="child.id" :value="child.id">
              {{ child.label }}
            </option>
          </select>
          <span v-if="errorFor('child_id')" class="mt-1 text-xs text-red-500">{{ errorFor('child_id') }}</span>
        </label>

        <label class="form-control">
          <span class="label-text">Tipo de incidencia</span>
          <select
            v-model="form.incidence_type_id"
            class="select select-bordered"
            :class="{ 'select-error': errorFor('incidence_type_id') }"
          >
            <option value="" disabled>Selecciona un tipo</option>
            <option v-for="type in incidenceTypes" :key="type.id" :value="type.id">
              {{ type.name }}
            </option>
          </select>
          <span v-if="selectedIncidenceTypeDescription" class="mt-1 text-xs text-slate-400">
            {{ selectedIncidenceTypeDescription }}
          </span>
          <span v-if="errorFor('incidence_type_id')" class="mt-1 text-xs text-red-500">
            {{ errorFor('incidence_type_id') }}
          </span>
        </label>

        <label class="form-control">
          <span class="label-text">Estatus</span>
          <select v-model="form.status" class="select select-bordered">
            <option v-for="status in statusOptions" :key="status.value" :value="status.value">
              {{ status.label }}
            </option>
          </select>
        </label>

        <label class="form-control lg:col-span-2">
          <span class="label-text">Descripcion de la incidencia</span>
          <textarea
            v-model="form.description"
            class="textarea textarea-bordered min-h-24 uppercase"
            :class="{ 'textarea-error': errorFor('description') }"
            placeholder="Describe la justificacion de la falta"
          ></textarea>
          <span v-if="errorFor('description')" class="mt-1 text-xs text-red-500">{{ errorFor('description') }}</span>
        </label>
      </div>

      <p v-if="generalError" class="mt-3 text-sm text-red-500">{{ generalError }}</p>

      <div class="mt-4 flex justify-end gap-2">
        <button type="button" class="btn btn-ghost" @click="resetForm">Limpiar</button>
        <button type="button" class="btn btn-primary gap-1.5" :disabled="loading" @click="save">
          <Plus v-if="!editingId" class="h-4 w-4" />
          <Pencil v-else class="h-4 w-4" />
          {{ editingId ? 'Actualizar' : 'Crear incidencia' }}
        </button>
      </div>
    </section>

    <div class="mb-4 grid gap-3 lg:grid-cols-[1.5fr_1fr_1fr_12rem]">
      <label
        class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-900"
      >
        <Search class="h-4 w-4 shrink-0 text-slate-400" />
        <input
          v-model="searchTerm"
          type="text"
          placeholder="Buscar por alumno, codigo o tipo..."
          class="w-full bg-transparent text-sm outline-none"
        />
        <button v-if="searchTerm" type="button" @click="searchTerm = ''">
          <X class="h-3.5 w-3.5" />
        </button>
      </label>

      <select v-model="selectedWeekend" class="select select-bordered w-full">
        <option :value="null">Todos los fines de semana</option>
        <option v-for="weekend in weekends" :key="weekend.id" :value="weekend.id">
          {{ weekend.label }}
        </option>
      </select>

      <select v-model="selectedChild" class="select select-bordered w-full">
        <option :value="null">Todos los alumnos</option>
        <option v-for="child in children" :key="child.id" :value="child.id">
          {{ child.label }}
        </option>
      </select>

      <select v-model="selectedStatus" class="select select-bordered w-full">
        <option :value="null">Todo estatus</option>
        <option v-for="status in statusOptions" :key="status.value" :value="status.value">
          {{ status.label }}
        </option>
      </select>
    </div>

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
