<script setup>
import { computed } from 'vue';
import { Building2 } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import CatalogTable from '../../../components/catalogs/CatalogTable.vue';

const props = defineProps({
  municipalities: { type: Object, required: true },
  states: { type: Array, default: () => [] },
  dioceses: { type: Array, default: () => [] },
  search: { type: String, default: '' },
});

const stateOptions = computed(() =>
  props.states.map((s) => ({
    value: s.id,
    label: s.short_name ? `${s.name} (${s.short_name})` : s.name,
  }))
);

const dioceseOptions = computed(() =>
  props.dioceses.map((d) => ({
    value: d.id,
    label: d.name,
  }))
);

const columns = computed(() => [
  {
    key: 'state_id',
    label: 'Estado',
    type: 'select',
    required: true,
    options: stateOptions.value,
  },
  {
    key: 'diocese_id',
    label: 'Diocesis',
    type: 'select',
    required: false,
    options: dioceseOptions.value,
  },
  {
    key: 'name',
    label: 'Nombre',
    type: 'text',
    required: true,
  },
  {
    key: 'status',
    label: 'Estatus',
    type: 'select',
    default: 'active',
    options: [
      { value: 'active', label: 'Activo' },
      { value: 'inactive', label: 'Inactivo' },
    ],
    badges: {
      active: 'badge-success',
      inactive: 'badge-error',
    },
  },
]);
</script>

<template>
  <AppShell :page-title="'Municipios'">
    <CatalogHeader
      title="Municipios"
      subtitle="Catalogo de municipios"
      back-href="/"
      :count="municipalities.total"
      :icon="Building2"
    />

    <CatalogTable
      :columns="columns"
      :pagination="municipalities"
      :search="search"
      :create-requires-full-scope="true"
      export-url="/municipios/export"
      export-permission="municipios.export"
      export-label="Exportar Excel"
      store-url="/municipios"
      base-url="/municipios"
      permission-module="municipios"
      search-placeholder="Buscar por nombre de municipio..."
    />
  </AppShell>
</template>
