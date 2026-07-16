<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Download, Plus, Search, X } from 'lucide-vue-next';

import AppPagination from '../AppPagination.vue';
import CatalogRow from './CatalogRow.vue';
import { useCatalogCrud } from '../../composables/useCatalogCrud';

const props = defineProps({
  columns: { type: Array, required: true },
  pagination: { type: Object, required: true },
  search: { type: String, default: '' },
  storeUrl: { type: String, required: true },
  baseUrl: { type: String, required: true },
  permissionModule: { type: String, default: null },
  createRequiresFullScope: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: 'Buscar...' },
  exportUrl: { type: String, default: null },
  exportPermission: { type: String, default: null },
  exportLabel: { type: String, default: 'Exportar Excel' },
  filterDefinitions: { type: Array, default: () => [] },
  initialFilters: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
  'row-added',
  'row-updated',
  'row-deleted',
]);

const page = usePage();

const rows = ref([...props.pagination.data]);
const searchTerm = ref(props.search);
const filterValues = ref({ ...props.initialFilters });
const editingId = ref(null);
const isAdding = ref(false);

const {
  loading,
  addErrors,
  editErrors,
  addGeneralError,
  editGeneralError,
  clearAddErrors,
  clearEditErrors,
  createRow,
  updateRow,
  deleteRow,
} = useCatalogCrud({
  baseUrl: props.baseUrl,
  storeUrl: props.storeUrl,
});

let debounce = null;

const queryParams = computed(() => {
  const params = {
    search: searchTerm.value || undefined,
    page: 1,
  };

  Object.entries(filterValues.value).forEach(([key, value]) => {
    params[key] = value || undefined;
  });

  return params;
});

watch([searchTerm, filterValues], () => {
  clearTimeout(debounce);

  debounce = setTimeout(() => {
    router.get(
      props.baseUrl,
      queryParams.value,
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      }
    );
  }, 400);
}, { deep: true });

watch(
  () => props.initialFilters,
  (newFilters) => {
    filterValues.value = { ...newFilters };
  },
  { deep: true }
);

watch(
  () => props.pagination.data,
  (newData) => {
    rows.value = [...newData];
    editingId.value = null;
    isAdding.value = false;
    clearAddErrors();
    clearEditErrors();
  }
);

const permissions = computed(
  () => page.props.auth?.permissions ?? []
);

const directPermissions = computed(
  () => page.props.auth?.direct_permissions ?? []
);

const fullScopeAccess = computed(
  () => page.props.auth?.scope?.full_access ?? {}
);

const hasPermission = (action) => {
  if (!props.permissionModule) return true;

  return permissions.value.includes(
    `${props.permissionModule}.${action}`
  );
};

const hasModuleFullScope = computed(() => {
  if (!props.permissionModule) return true;

  return fullScopeAccess.value?.[
    props.permissionModule
  ] === true;
});

const canExport = computed(() => {
  if (!props.exportUrl || !props.exportPermission) return false;

  return permissions.value.includes(props.exportPermission);
});

const exportTable = () => {
  if (!canExport.value) return;

  const url = new URL(props.exportUrl, window.location.origin);

  if (searchTerm.value) {
    url.searchParams.set('search', searchTerm.value);
  }

  Object.entries(filterValues.value).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });

  window.location.assign(url.toString());
};

const canCreate = computed(() =>
  hasPermission('create') &&
  (!props.createRequiresFullScope || hasModuleFullScope.value)
);

const canUpdate = computed(() =>
  hasPermission('update')
);

const canDelete = computed(() =>
  hasPermission('delete')
);

const showActions = computed(() =>
  canCreate.value ||
  canUpdate.value ||
  canDelete.value
);

const validateRow = (data) => {
  const errors = {};

  props.columns.forEach((col) => {
    if (
      col.required &&
      (
        data[col.key] === '' ||
        data[col.key] === null ||
        data[col.key] === undefined
      )
    ) {
      errors[col.key] = [
        `El campo ${col.label} es obligatorio.`,
      ];
    }
  });

  return errors;
};

const startAdd = () => {
  if (!canCreate.value) return;
  if (isAdding.value || editingId.value !== null) return;

  clearAddErrors();
  isAdding.value = true;
};

const cancelAdd = () => {
  isAdding.value = false;
  clearAddErrors();
};

const saveAdd = async (payload) => {
  const clientErrors = validateRow(payload);

  if (Object.keys(clientErrors).length) {
    addErrors.value = clientErrors;
    return;
  }

  const created = await createRow(payload);

  if (created) {
    rows.value.push(created);
    isAdding.value = false;
    emit('row-added', created);
  }
};

const startEdit = (row) => {
  if (!canUpdate.value) return;
  if (isAdding.value) return;

  clearEditErrors();
  editingId.value = row.id;
};

const cancelEdit = () => {
  editingId.value = null;
  clearEditErrors();
};

const saveEdit = async (row, payload) => {
  const clientErrors = validateRow(payload);

  if (Object.keys(clientErrors).length) {
    editErrors.value = clientErrors;
    return;
  }

  const updated = await updateRow(row.id, payload);

  if (updated) {
    const idx = rows.value.findIndex(
      (r) => r.id === row.id
    );

    if (idx !== -1) {
      rows.value[idx] = updated;
    }

    editingId.value = null;
    emit('row-updated', updated);
  }
};

const handleDelete = async (row) => {
  const deleted = await deleteRow(row);

  if (deleted) {
    rows.value = rows.value.filter(
      (r) => r.id !== row.id
    );

    emit('row-deleted', row.id);
  }
};
</script>

<template>
  <div>
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <label
          class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 sm:w-80 dark:border-slate-700 dark:bg-slate-900"
        >
          <Search class="h-4 w-4 shrink-0 text-slate-400" />

          <input
            v-model="searchTerm"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-full bg-transparent text-sm outline-none"
          />

          <button
            v-if="searchTerm"
            type="button"
            @click="searchTerm = ''"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </label>

        <div
          v-for="filter in filterDefinitions"
          :key="filter.key"
          class="sm:w-72"
        >
          <select
            v-model="filterValues[filter.key]"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-900"
          >
            <option :value="null">{{ filter.placeholder ?? `Todos` }}</option>
            <option
              v-for="option in filter.options ?? []"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-2 sm:justify-end">
        <button
          v-if="canExport"
          class="btn btn-outline btn-sm gap-1.5"
          :disabled="loading"
          @click="exportTable"
        >
          <Download class="h-4 w-4" />
          {{ exportLabel }}
        </button>

        <button
          v-if="canCreate"
          class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="isAdding || editingId !== null || loading"
          @click="startAdd"
        >
          <Plus class="h-4 w-4" />
          Agregar registro
        </button>
      </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <table class="table w-full">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500">
            <th
              v-for="col in columns"
              :key="col.key"
            >
              {{ col.label }}
            </th>

            <th v-if="showActions">
              Acciones
            </th>
          </tr>
        </thead>

        <tbody>
          <CatalogRow
            v-if="isAdding"
            mode="create"
            :columns="columns"
            :errors="addErrors"
            :general-error="addGeneralError"
            :loading="loading"
            :show-actions="showActions"
            @save="saveAdd"
            @cancel="cancelAdd"
          />

          <CatalogRow
            v-for="row in rows"
            :key="row.id"
            :row="row"
            :columns="columns"
            :mode="editingId === row.id ? 'edit' : 'view'"
            :errors="editErrors"
            :general-error="editGeneralError"
            :loading="loading"
            :show-actions="showActions"
            :can-update="canUpdate"
            :can-delete="canDelete"
            @edit="startEdit(row)"
            @delete="handleDelete(row)"
            @cancel="cancelEdit"
            @save="(payload) => saveEdit(row, payload)"
          />

          <tr v-if="rows.length === 0 && !isAdding">
            <td
              :colspan="columns.length + (showActions ? 1 : 0)"
              class="px-4 py-12 text-center text-sm text-slate-400"
            >
              <span v-if="searchTerm">
                No se encontraron registros para
                <strong>"{{ searchTerm }}"</strong>
              </span>

              <span v-else>
                No hay registros.
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="pagination.links"
      :from="pagination.from"
      :to="pagination.to"
      :total="pagination.total"
    />
  </div>
</template>
