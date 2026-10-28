<script setup>
import { IdCard } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import CatalogTable from '../../../components/catalogs/CatalogTable.vue';

defineProps({
  staffTypes: { type: Object, required: true },
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
    key: 'description',
    label: 'Descripción',
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
      inactive: 'badge-secondary',
    },
  },
];
</script>

<template>
  <AppShell :page-title="'Tipos de Personal'">
    <CatalogHeader
      title="Tipos de Personal"
      subtitle="Catálogo de clasificación del personal"
      back-href="/"
      :count="staffTypes.total"
      :icon="IdCard"
    />

    <CatalogTable
      :columns="columns"
      :pagination="staffTypes"
      :search="search"
      store-url="/tipos-personal"
      base-url="/tipos-personal"
      permission-module="tipos_personal"
      search-placeholder="Buscar por nombre de tipo de personal..."
    />
  </AppShell>
</template>
