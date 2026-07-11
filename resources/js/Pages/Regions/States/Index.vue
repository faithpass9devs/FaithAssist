<script setup>
import { MapPinned } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import CatalogTable from '../../../components/catalogs/CatalogTable.vue';

defineProps({
  states: { type: Object, required: true },
  search: { type: String, default: '' },
});

const columns = [
  {
    key: 'name',
    label: 'Nombre',
    type: 'text',
    required: true,
  },
  {
    key: 'short_name',
    label: 'Abreviatura',
    type: 'text',
    required: false,
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
];
</script>

<template>
  <AppShell :page-title="'Estados'">
    <CatalogHeader
      title="Estados"
      subtitle="Catalogo de estados del pais"
      back-href="/"
      :count="states.total"
      :icon="MapPinned"
    />

    <CatalogTable
      :columns="columns"
      :pagination="states"
      :search="search"
      export-url="/estados/export"
      export-permission="estados.export"
      export-label="Exportar Excel"
      store-url="/estados"
      base-url="/estados"
      permission-module="estados"
      search-placeholder="Buscar por nombre de estado..."
    />
  </AppShell>
</template>
