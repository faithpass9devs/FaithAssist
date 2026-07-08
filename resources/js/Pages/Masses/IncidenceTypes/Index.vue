<script setup>
import { computed } from 'vue';
import { Tags } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import CatalogTable from '../../../components/catalogs/CatalogTable.vue';

const props = defineProps({
  incidenceTypes: { type: Object, required: true },
  statusOptions: { type: Array, default: () => [] },
  search: { type: String, default: '' },
});

const columns = computed(() => [
  {
    key: 'name',
    label: 'Nombre',
    type: 'text',
    required: true,
  },
  {
    key: 'description',
    label: 'Descripcion',
    type: 'text',
    required: false,
  },
  {
    key: 'status',
    label: 'Estatus',
    type: 'select',
    default: 'active',
    options: props.statusOptions,
    badges: {
      active: 'badge-success',
      inactive: 'badge-secondary',
    },
  },
]);
</script>

<template>
  <AppShell :page-title="'Tipos de Incidencias'">
    <CatalogHeader
      title="Tipos de Incidencias"
      subtitle="Administra el catálogo de motivos para justificar faltas"
      back-href="/"
      :count="incidenceTypes.total"
      :icon="Tags"
    />

    <CatalogTable
      :columns="columns"
      :pagination="incidenceTypes"
      :search="search"
      store-url="/tipos-incidencias"
      base-url="/tipos-incidencias"
      permission-module="tipos_incidencias"
      search-placeholder="Buscar por nombre, descripcion o estatus"
    />
  </AppShell>
</template>
