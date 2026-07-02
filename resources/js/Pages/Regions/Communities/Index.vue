<script setup>
import { computed } from 'vue';
import { Users } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import CatalogTable from '../../../components/catalogs/CatalogTable.vue';

const props = defineProps({
  communities: { type: Object, required: true },
  municipalities: { type: Array, default: () => [] },
  search: { type: String, default: '' },
});

const municipalityOptions = computed(() =>
  props.municipalities.map((m) => ({ value: m.id, label: m.name }))
);

const columns = computed(() => [
  {
    key: 'municipality_id',
    label: 'Municipio',
    type: 'select',
    required: true,
    options: municipalityOptions.value,
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
  <AppShell :page-title="'Comunidades'">
    <CatalogHeader
      title="Comunidades"
      subtitle="Catalogo de comunidades"
      back-href="/"
      :count="communities.total"
      :icon="Users"
    />

    <CatalogTable
      :columns="columns"
      :pagination="communities"
      :search="search"
      :create-requires-full-scope="true"
      export-url="/comunidades/export"
      export-permission="comunidades.export"
      export-label="Exportar Excel"
      store-url="/comunidades"
      base-url="/comunidades"
      permission-module="comunidades"
      search-placeholder="Buscar por nombre de comunidad..."
    />
  </AppShell>
</template>
