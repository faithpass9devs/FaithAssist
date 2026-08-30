<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { User } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../../components/forms/UnderlineField.vue';
import UnderlineSection from '../../../components/forms/UnderlineSection.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  child: { type: Object, default: null },
  churches: { type: Array, default: () => [] },
  municipalities: { type: Array, default: () => [] },
  communities: { type: Array, default: () => [] },
  countryCodes: { type: Array, default: () => [] },
  defaultCountryCode: { type: String, default: '521' },
  statuses: { type: Array, default: () => [] },
  sexes: { type: Array, default: () => [] },
  bloodTypes: { type: Array, default: () => [] },
  levels: { type: Array, default: () => [] },
});

const isEditing = computed(() => !!props.child);
const pageTitle = computed(() => (isEditing.value ? 'Editar niño' : 'Nuevo niño'));
const defaultChurchId = computed(
  () => props.child?.church_id ?? (props.churches.length === 1 ? props.churches[0].id : null),
);
const defaultCommunityId = computed(
  () =>
    props.child?.community_id ?? (props.communities.length === 1 ? props.communities[0].id : null),
);

const form = useForm({
  church_id: defaultChurchId.value,
  community_id: defaultCommunityId.value,
  name: props.child?.name ?? '',
  paterno: props.child?.paterno ?? '',
  materno: props.child?.materno ?? '',
  birthdate: props.child?.birthdate ?? '',
  sex: props.child?.sex ?? '',
  email: props.child?.email ?? '',
  phone_lada: props.child?.phone_lada ?? props.defaultCountryCode,
  phone: props.child?.phone ?? '',
  emergency_phone_lada: props.child?.emergency_phone_lada ?? props.defaultCountryCode,
  emergency_phone: props.child?.emergency_phone ?? '',
  blood_type: props.child?.blood_type ?? 'unknown',
  observations: props.child?.observations ?? '',
  privacy_terms: props.child?.privacy_terms ?? false,
  status: props.child?.status ?? 'active',
  level_ids: props.child?.levels?.map((level) => level.id) ?? [],
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
const communityOptions = computed(() =>
  filteredCommunities.value.map((community) => ({
    value: community.id,
    label: community.name,
  })),
);
const levelOptions = computed(() => {
  if (!selectedChurch.value?.diocese_id) {
    return [];
  }

  return props.levels
    .filter((level) => level.diocese_id === selectedChurch.value.diocese_id)
    .map((level) => ({
      value: level.id,
      label: level.name,
    }));
});
const currentLevelNames = computed(() =>
  props.child?.levels?.length ? props.child.levels.map((level) => level.name).join(', ') : 'Sin nivel asignado',
);
const levelEmptyMessage = computed(() =>
  selectedChurch.value?.diocese_id
    ? 'No hay niveles activos disponibles para la diócesis de la parroquia seleccionada.'
    : 'Selecciona una parroquia para cargar los niveles de su diócesis.',
);
const filteredCommunities = computed(() => {
  if (!selectedChurch.value?.municipality_id) return props.communities;

  return props.communities.filter(
    (community) => community.municipality_id === selectedChurch.value.municipality_id,
  );
});

const toggleLevel = (levelId) => {
  const normalizedId = Number(levelId);
  if (form.level_ids.includes(normalizedId)) {
    form.level_ids = form.level_ids.filter((id) => id !== normalizedId);
    return;
  }

  form.level_ids = [...form.level_ids, normalizedId];
};

watch(
  () => form.church_id,
  () => {
    if (form.community_id !== null) {
      const communityValid = filteredCommunities.value.some(
        (community) => community.id === form.community_id,
      );
      if (!communityValid) form.community_id = null;
    }

    form.level_ids = form.level_ids.filter((levelId) =>
      levelOptions.value.some((level) => level.value === levelId),
    );
  },
);

const submit = () => {
  if (isEditing.value) {
    form.put(`/children/${props.child.id}`, { preserveScroll: true });
  } else {
    form.post('/children');
  }
};
</script>

<template>
  <AppShell :page-title="pageTitle">
    <form @submit.prevent="submit">
      <CatalogHeader
        :title="pageTitle"
        subtitle="Datos del niño y su asignación de catecismo"
        back-href="/children"
        :icon="User"
      >
      </CatalogHeader>

      <div
        class="mb-3 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-8"
      >
        <div v-if="isEditing" class="mb-8 grid gap-6 md:grid-cols-2">
          <UnderlineField :model-value="child.code" label="Código único" disabled />
          <UnderlineField :model-value="child.created_at" label="Fecha de registro" disabled />
        </div>

        <div class="space-y-9">
          <UnderlineSection title="Datos personales">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2 lg:grid-cols-3">
              <UnderlineField
                v-model="form.name"
                label="Nombre"
                :error="form.errors.name"
                required
              />
              <UnderlineField
                v-model="form.paterno"
                label="Paterno"
                :error="form.errors.paterno"
                required
              />
              <UnderlineField
                v-model="form.materno"
                label="Materno"
                :error="form.errors.materno"
              />
              <UnderlineField
                v-model="form.birthdate"
                label="Fecha de nacimiento"
                type="date"
                :error="form.errors.birthdate"
                required
              />
              <UnderlineField
                v-model="form.sex"
                label="Sexo"
                as="select"
                placeholder="Selecciona..."
                :options="sexes"
                :error="form.errors.sex"
                required
              />
              <UnderlineField
                v-model="form.blood_type"
                label="Tipo de sangre"
                as="select"
                :options="bloodTypes"
                :error="form.errors.blood_type"
                required
              />
            </div>
          </UnderlineSection>

          <UnderlineSection title="Catecismo">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2">
              <UnderlineField
                v-model="form.church_id"
                label="Iglesia"
                as="select"
                placeholder="Selecciona una iglesia..."
                :options="churchOptions"
                :error="form.errors.church_id"
                number-value
                required
              />
              <UnderlineField
                :model-value="selectedChurch?.municipality_id ?? null"
                label="Municipio"
                as="select"
                placeholder="Se define por la iglesia"
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
              <UnderlineField
                v-model="form.status"
                label="Estado"
                as="select"
                :options="statuses"
                :error="form.errors.status"
                required
              />
              <div v-if="!isEditing" class="md:col-span-2">
                <div class="mb-2 flex items-center justify-between gap-3">
                  <label class="block text-sm font-bold text-slate-600 dark:text-slate-300">
                    Niveles <span class="text-red-500">*</span>
                  </label>
                  <span class="text-xs font-semibold text-slate-400">
                    {{ form.level_ids.length }} seleccionado(s)
                  </span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  <button
                    v-for="level in levelOptions"
                    :key="level.value"
                    type="button"
                    class="rounded-2xl border p-4 text-left transition"
                    :class="
                      form.level_ids.includes(level.value)
                        ? 'border-rose-500 bg-rose-50 text-rose-800 shadow-sm dark:border-rose-400 dark:bg-rose-950/30 dark:text-rose-200'
                        : 'border-slate-200 bg-white text-slate-700 hover:border-rose-200 hover:bg-rose-50/60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-rose-700'
                    "
                    @click="toggleLevel(level.value)"
                  >
                    <span class="flex items-center gap-3">
                      <span
                        class="flex h-5 w-5 items-center justify-center rounded border text-xs font-bold"
                        :class="
                          form.level_ids.includes(level.value)
                            ? 'border-rose-500 bg-rose-600 text-white'
                            : 'border-slate-300 text-transparent dark:border-slate-600'
                        "
                      >
                        ✓
                      </span>
                      <span class="font-semibold">{{ level.label }}</span>
                    </span>
                  </button>
                </div>
                <p v-if="form.errors.level_ids" class="mt-2 text-xs font-semibold text-red-500">
                  {{ form.errors.level_ids }}
                </p>
                <p v-if="levelOptions.length === 0" class="mt-2 text-sm font-semibold text-slate-400">
                  {{ levelEmptyMessage }}
                </p>
              </div>
              <UnderlineField
                v-else
                :model-value="currentLevelNames"
                label="Niveles asignados"
                disabled
              />
            </div>
          </UnderlineSection>

          <UnderlineSection title="Contacto">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2">
              <UnderlineField
                v-model="form.email"
                label="Correo electrónico"
                type="email"
                placeholder="nombre@correo.com"
                :error="form.errors.email"
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
              <div class="grid grid-cols-[8.5rem_1fr] gap-4 md:col-span-2">
                <UnderlineField
                  v-model="form.emergency_phone_lada"
                  label="Lada emergencia"
                  as="select"
                  :options="countryCodes"
                  :error="form.errors.emergency_phone_lada"
                />
                <UnderlineField
                  v-model="form.emergency_phone"
                  label="Teléfono de emergencia"
                  placeholder="Ej. 5598765432"
                  :error="form.errors.emergency_phone"
                />
              </div>
            </div>
          </UnderlineSection>

          <UnderlineSection title="Observaciones">
            <div class="grid gap-x-9 gap-y-7">
              <UnderlineField
                v-model="form.observations"
                label="Observaciones"
                as="textarea"
                placeholder="Notas médicas, familiares o administrativas"
                :error="form.errors.observations"
              />

              <label
                class="flex items-start gap-3 rounded-xl border border-slate-300 bg-slate-50/70 p-4 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300"
              >
                <input
                  v-model="form.privacy_terms"
                  type="checkbox"
                  class="checkbox checkbox-primary mt-0.5"
                  :class="{ 'checkbox-error': form.errors.privacy_terms }"
                />
                <span>
                  Confirmo que se aceptaron los términos de privacidad para registrar y tratar los
                  datos del niño.
                  <span class="text-red-500">*</span>
                  <span v-if="form.errors.privacy_terms" class="mt-1 block text-xs text-red-500">{{
                    form.errors.privacy_terms
                  }}</span>
                </span>
              </label>
            </div>
          </UnderlineSection>
        </div>
      </div>
      <div class="flex justify-end gap-3 border-t border-slate-200 dark:border-slate-700">
        <Link
          href="/children"
          class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:bg-slate-800"
        >
          Cancelar
        </Link>

        <button
          type="submit"
          class="btn btn-sm rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Guardando...' : isEditing ? 'Actualizar niño' : 'Crear niño' }}
        </button>
      </div>
    </form>
  </AppShell>
</template>
