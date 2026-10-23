<script setup>
import { computed, reactive, watch } from 'vue';
import { Check, Pencil, Trash2, X } from 'lucide-vue-next';
import CatalogCellEditor from './CatalogCellEditor.vue';

const props = defineProps({
  row: {
    type: Object,
    default: () => ({}),
  },

  columns: {
    type: Array,
    required: true,
  },

  mode: {
    type: String,
    default: 'view', // view | edit | create
  },

  errors: {
    type: Object,
    default: () => ({}),
  },

  generalError: {
    type: String,
    default: '',
  },

  loading: {
    type: Boolean,
    default: false,
  },

  showActions: {
    type: Boolean,
    default: true,
  },

  canUpdate: {
    type: Boolean,
    default: true,
  },

  canDelete: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits([
  'edit',
  'delete',
  'save',
  'cancel',
]);

const localData = reactive({});
let initializing = false;

const initializeData = () => {
  initializing = true;

  props.columns.forEach((col) => {
    localData[col.key] =
      props.row?.[col.key] ??
      col.default ??
      '';
  });

  initializing = false;
};

watch(
  () => props.row,
  () => initializeData(),
  { immediate: true, deep: true }
);

props.columns.forEach((col) => {
  if (!col.resetDependents?.length) return;

  watch(
    () => localData[col.key],
    () => {
      if (initializing) return;

      col.resetDependents.forEach((key) => {
        if (key in localData) localData[key] = '';
      });
    },
    { flush: 'sync' }
  );
});

const resolvedColumns = computed(() =>
  props.columns.map((col) => ({
    ...col,
    options:
      typeof col.options === 'function'
        ? col.options(localData)
        : col.options,
  }))
);

const getOptionLabel = (col, value) =>
  (col.displayOptions ?? col.options)?.find((o) => o.value === value)?.label ?? value;

const getBadgeClass = (col, value) =>
  col.badges?.[value] ?? 'badge-ghost';

const save = () => {
  emit('save', { ...localData });
};
</script>

<template>
  <!-- CREATE / EDIT MODE -->
  <tr
    v-if="mode === 'create' || mode === 'edit'"
    :class="[
      'border-b',
      mode === 'create'
        ? 'border-sky-100 bg-sky-50/60 dark:border-sky-900/30 dark:bg-sky-950/20'
        : 'border-amber-100 bg-amber-50/60 dark:border-amber-900/20 dark:bg-amber-950/10'
    ]"
  >
    <td
      v-for="col in resolvedColumns"
      :key="col.key"
      class="px-4 py-2"
    >
      <CatalogCellEditor
        v-model="localData[col.key]"
        :column="col"
        :error="errors[col.key]?.[0]"
      />
    </td>

    <td
      v-if="showActions"
      class="px-4 py-2 text-right"
    >
      <p
        v-if="generalError"
        class="mb-1 text-left text-xs text-red-600 dark:text-red-400"
      >
        {{ generalError }}
      </p>

      <button
        class="btn btn-success btn-xs mr-1"
        :disabled="loading"
        @click="save"
      >
        <Check class="h-3.5 w-3.5" />
      </button>

      <button
        class="btn btn-ghost btn-xs"
        @click="emit('cancel')"
      >
        <X class="h-3.5 w-3.5" />
      </button>
    </td>
  </tr>

  <!-- VIEW MODE -->
  <tr
    v-else
    class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
  >
    <td
      v-for="col in columns"
      :key="col.key"
      class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300"
    >
      <span
        v-if="col.badges"
        :class="[
          'badge badge-sm font-medium',
          getBadgeClass(col, row[col.key])
        ]"
      >
        {{ getOptionLabel(col, row[col.key]) }}
      </span>

      <span v-else-if="col.type === 'select'">
        {{ getOptionLabel(col, row[col.key]) }}
      </span>

      <span v-else>
        {{ row[col.key] ?? '' }}
      </span>
    </td>

    <td
      v-if="showActions"
      class="px-4 py-3 text-right"
    >
      <button
        v-if="canUpdate"
        class="btn btn-ghost btn-xs mr-1 text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-400 dark:hover:bg-sky-950/40"
        :disabled="loading"
        @click="emit('edit')"
      >
        <Pencil class="h-3.5 w-3.5" />
      </button>

      <button
        v-if="canDelete"
        class="btn btn-ghost btn-xs text-red-500 hover:bg-red-50 hover:text-red-600 dark:text-red-400 dark:hover:bg-red-950/40"
        :disabled="loading"
        @click="emit('delete')"
      >
        <Trash2 class="h-3.5 w-3.5" />
      </button>
    </td>
  </tr>
</template>