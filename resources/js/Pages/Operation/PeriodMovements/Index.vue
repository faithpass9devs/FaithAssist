<script setup>
import { computed } from 'vue';
import { ArrowLeftRight } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import CatalogTable from '../../../components/catalogs/CatalogTable.vue';

const props = defineProps({
  movements: { type: Object, required: true },
  periods: { type: Array, default: () => [] },
  churches: { type: Array, default: () => [] },
  movementTypes: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  search: { type: String, default: '' },
});

const periodOptions = computed(() =>
  props.periods.map((period) => ({
    value: period.id,
    label: [period.diocese_name, period.name, period.years].filter(Boolean).join(' - '),
  })),
);

const churchOptions = computed(() =>
  props.churches.map((church) => ({
    value: church.id,
    label: church.name,
  })),
);

const movementTypeOptions = computed(() =>
  props.movementTypes.map((movementType) => ({
    value: movementType.id,
    label: movementType.name,
  })),
);

const columns = computed(() => [
  {
    key: 'period_id',
    label: 'Periodo',
    type: 'select',
    required: true,
    options: periodOptions.value,
  },
  {
    key: 'church_id',
    label: 'Parroquia',
    type: 'select',
    required: true,
    options: churchOptions.value,
  },
  {
    key: 'period_movement_type_id',
    label: 'Tipo de movimiento',
    type: 'select',
    required: true,
    options: movementTypeOptions.value,
  },
  {
    key: 'status',
    label: 'Estatus',
    type: 'select',
    default: 'pending',
    options: props.statusOptions,
    badges: {
      pending: 'badge-info',
      in_progress: 'badge-warning',
      completed: 'badge-success',
    },
  },
  {
    key: 'start_date',
    label: 'Fecha de inicio',
    type: 'date',
    required: true,
  },
  {
    key: 'end_date',
    label: 'Fecha de fin',
    type: 'date',
    required: true,
  },
  {
    key: 'notes',
    label: 'Notas',
    type: 'text',
    required: false,
    uppercase: false,
  },
]);
</script>

<template>
  <AppShell :page-title="'Movimientos del Periodo'">
    <CatalogHeader
      title="Movimientos del Periodo"
      subtitle="Historial administrativo de movimientos por periodo"
      back-href="/"
      :count="movements.total"
      :icon="ArrowLeftRight"
    />

    <CatalogTable
      :columns="columns"
      :pagination="movements"
      :search="search"
      store-url="/periodo-movimientos"
      base-url="/periodo-movimientos"
      permission-module="periodo_movimientos"
      search-placeholder="Buscar por periodo, diocesis, tipo o estatus..."
    />
  </AppShell>
</template>
