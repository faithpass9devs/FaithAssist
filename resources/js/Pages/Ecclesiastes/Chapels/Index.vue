<script setup>
import { computed } from 'vue';
import { Landmark } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import CatalogTable from '../../../components/catalogs/CatalogTable.vue';

const props = defineProps({
  chapels: { type: Object, required: true },
  communities: { type: Array, default: () => [] },
  churches: { type: Array, default: () => [] },
  search: { type: String, default: '' },
  lockedChurchId: { type: Number, default: null },
});

const communityOptions = computed(() =>
  props.communities.map((c) => ({ value: c.id, label: c.name }))
);

const communityOptionsForChurch = (churchId) => {
  const church = props.churches.find((c) => c.id === churchId);

  if (! church?.municipality_id) {
    return communityOptions.value;
  }

  return props.communities
    .filter((c) => c.municipality_id === church.municipality_id)
    .map((c) => ({ value: c.id, label: c.name }));
};

const churchOptions = computed(() =>
  props.churches.map((c) => ({ value: c.id, label: c.name }))
);

const isChurchLocked = computed(() => props.lockedChurchId !== null);

const columns = computed(() => [
  {
    key: 'church_id',
    label: 'Parroquia',
    type: 'select',
    required: false,
    options: churchOptions.value,
    default: props.lockedChurchId ?? undefined,
    disabled: isChurchLocked.value,
    resetDependents: ['community_id'],
  },
  {
    key: 'community_id',
    label: 'Comunidad',
    type: 'select',
    required: true,
    options: (row) => communityOptionsForChurch(row.church_id),
    displayOptions: communityOptions.value,
  },
  {
    key: 'name',
    label: 'Nombre',
    type: 'text',
    required: true,
  },
  {
    key: 'address',
    label: 'Direccion',
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
]);
</script>

<template>
  <AppShell :page-title="'Capillas'">
    <CatalogHeader
      title="Capillas"
      subtitle="Catalogo de capillas"
      back-href="/"
      :count="chapels.total"
      :icon="Landmark"
    />

    <CatalogTable
      :columns="columns"
      :pagination="chapels"
      :search="search"
      store-url="/capillas"
      base-url="/capillas"
      permission-module="capillas"
      search-placeholder="Buscar por nombre de capilla..."
    />
  </AppShell>
</template>
