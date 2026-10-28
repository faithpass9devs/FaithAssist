<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { User } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../../components/forms/UnderlineField.vue';
import UnderlineSection from '../../../components/forms/UnderlineSection.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  staff: { type: Object, default: null },
  churches: { type: Array, default: () => [] },
  municipalities: { type: Array, default: () => [] },
  communities: { type: Array, default: () => [] },
  staffTypes: { type: Array, default: () => [] },
  countryCodes: { type: Array, default: () => [] },
  defaultCountryCode: { type: String, default: '521' },
});

const isEditing = computed(() => !!props.staff);
const pageTitle = computed(() => (isEditing.value ? 'Editar personal' : 'Nuevo personal'));
const defaultChurchId = computed(
  () => props.staff?.church_id ?? (props.churches.length === 1 ? props.churches[0].id : null),
);
const defaultCommunityId = computed(
  () => props.staff?.community_id ?? (props.communities.length === 1 ? props.communities[0].id : null),
);

const form = useForm({
  staff_type_id: props.staff?.staff_type_id ?? null,
  church_id: defaultChurchId.value,
  community_id: defaultCommunityId.value,
  name: props.staff?.name ?? '',
  paterno: props.staff?.paterno ?? '',
  materno: props.staff?.materno ?? '',
  birthdate: props.staff?.birthdate ?? '',
  phone_lada: props.staff?.phone_lada ?? props.defaultCountryCode,
  phone: props.staff?.phone ?? '',
});

const selectedChurch = computed(() =>
  props.churches.find((church) => church.id === form.church_id),
);

const municipalityOptions = computed(() =>
  props.municipalities.map((municipality) => ({
    value: municipality.id,
    label: municipality.name,
  })),
);

const churchOptions = computed(() =>
  props.churches.map((church) => ({
    value: church.id,
    label: church.name,
  })),
);

const staffTypeOptions = computed(() =>
  props.staffTypes.map((staffType) => ({
    value: staffType.id,
    label: staffType.name,
  })),
);

const filteredCommunities = computed(() => {
  if (!selectedChurch.value?.municipality_id) return props.communities;

  return props.communities.filter(
    (community) => community.municipality_id === selectedChurch.value.municipality_id,
  );
});

const communityOptions = computed(() =>
  filteredCommunities.value.map((community) => ({
    value: community.id,
    label: community.name,
  })),
);

watch(
  () => form.church_id,
  () => {
    if (form.community_id !== null) {
      const valid = filteredCommunities.value.some((community) => community.id === form.community_id);
      if (!valid) form.community_id = null;
    }
  },
);

const submit = () => {
  if (isEditing.value) {
    form.put(`/personal/${props.staff.id}`, { preserveScroll: true });
  } else {
    form.post('/personal');
  }
};
</script>

<template>
  <AppShell :page-title="pageTitle">
    <form @submit.prevent="submit">
      <CatalogHeader
        :title="pageTitle"
        subtitle="Datos del personal y adscripción parroquial"
        back-href="/personal"
        :icon="User"
      />

      <div class="mb-3 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-8">
        <div v-if="isEditing" class="mb-8 grid gap-6 md:grid-cols-2">
          <UnderlineField :model-value="staff.code" label="Código único" disabled />
          <UnderlineField :model-value="staff.created_at" label="Fecha de registro" disabled />
        </div>

        <div class="space-y-9">
          <UnderlineSection title="Datos personales">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2 lg:grid-cols-3">
              <UnderlineField v-model="form.name" label="Nombre" :error="form.errors.name" required />
              <UnderlineField v-model="form.paterno" label="Paterno" :error="form.errors.paterno" required />
              <UnderlineField v-model="form.materno" label="Materno" :error="form.errors.materno" />
              <UnderlineField
                v-model="form.birthdate"
                label="Fecha de nacimiento"
                type="date"
                :error="form.errors.birthdate"
              />
              <UnderlineField
                v-model="form.staff_type_id"
                label="Tipo de personal"
                as="select"
                placeholder="Selecciona..."
                :options="staffTypeOptions"
                :error="form.errors.staff_type_id"
                number-value
                required
              />
              <div class="grid grid-cols-[8.5rem_1fr] gap-4">
                <UnderlineField
                  v-model="form.phone_lada"
                  label="Lada"
                  as="select"
                  :options="countryCodes"
                  :error="form.errors.phone_lada"
                />
                <UnderlineField
                  v-model="form.phone"
                  label="Teléfono"
                  placeholder="Ej. 5512345678"
                  :error="form.errors.phone"
                />
              </div>
            </div>
          </UnderlineSection>

          <UnderlineSection title="Adscripción">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2">
              <UnderlineField
                v-model="form.church_id"
                label="Parroquia adscrita"
                as="select"
                placeholder="Selecciona una parroquia..."
                :options="churchOptions"
                :error="form.errors.church_id"
                number-value
                required
              />
              <UnderlineField
                :model-value="selectedChurch?.municipality_id ?? null"
                label="Municipio"
                as="select"
                placeholder="Se define por la parroquia"
                :options="municipalityOptions"
                number-value
                disabled
              />
              <UnderlineField
                v-model="form.community_id"
                label="Comunidad"
                as="select"
                placeholder="Selecciona una comunidad..."
                :options="communityOptions"
                :error="form.errors.community_id"
                number-value
                required
              />
            </div>
          </UnderlineSection>
        </div>
      </div>

      <div class="flex justify-end gap-3 border-t border-slate-200 dark:border-slate-700">
        <Link
          href="/personal"
          class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:bg-slate-800"
        >
          Cancelar
        </Link>

        <button
          type="submit"
          class="btn btn-sm rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Guardando...' : isEditing ? 'Actualizar personal' : 'Crear personal' }}
        </button>
      </div>
    </form>
  </AppShell>
</template>
