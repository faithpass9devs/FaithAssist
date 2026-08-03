<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRightLeft } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../../components/forms/UnderlineField.vue';
import UnderlineSection from '../../../components/forms/UnderlineSection.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  externo: { type: Object, required: true },
  churches: { type: Array, default: () => [] },
  municipalities: { type: Array, default: () => [] },
  communities: { type: Array, default: () => [] },
  defaultChurchId: { type: [Number, String], default: null },
  currentLevel: { type: Object, default: null },
  targetLevel: { type: Object, default: null },
  levels: { type: Array, default: () => [] },
  bloodTypes: { type: Array, default: () => [] },
  countryCodes: { type: Array, default: () => [] },
  defaultCountryCode: { type: String, default: '52' },
});

const initialBloodType = computed(() => {
  const raw = (props.externo.blood_type || '').replace('−', '-').trim().toUpperCase();
  const option = props.bloodTypes.find((blood) => blood.label.toUpperCase() === raw);

  return option ? option.value : 'unknown';
});

const form = useForm({
  name: props.externo.name ?? '',
  paterno: props.externo.paterno ?? '',
  materno: props.externo.materno ?? '',
  blood_type: initialBloodType.value,
  email: props.externo.email ?? '',
  phone_lada: props.externo.phone_lada ?? props.defaultCountryCode,
  phone: props.externo.phone ?? '',
  emergency_phone_lada: props.externo.emergency_phone_lada ?? props.defaultCountryCode,
  emergency_phone: props.externo.emergency_phone ?? '',
  community_id: props.externo.community_id ?? null,
  observations: props.externo.notes ?? '',
  privacy_terms: false,
  level_ids: props.targetLevel?.id ? [props.targetLevel.id] : [],
});

const editLevel = ref(false);

const sexLabel = computed(() => (props.externo.sex === 'M' ? 'Mujer' : 'Hombre'));

const selectedChurch = computed(() =>
  props.churches.find((church) => church.id === props.defaultChurchId),
);

const churchOptions = computed(() =>
  props.churches.map((church) => ({
    value: church.id,
    label: church.name,
  })),
);

const municipalityName = computed(() => {
  const municipality = props.municipalities.find(
    (item) => item.id === selectedChurch.value?.municipality_id,
  );

  return municipality?.name ?? '—';
});

const communityOptions = computed(() => {
  const municipalityId = selectedChurch.value?.municipality_id;
  const filtered = municipalityId
    ? props.communities.filter((community) => community.municipality_id === municipalityId)
    : props.communities;

  const list = [...filtered];
  const externoCommunity = props.communities.find((community) => community.id === props.externo.community_id);

  if (externoCommunity && !list.some((community) => community.id === externoCommunity.id)) {
    list.push(externoCommunity);
  }

  return list.map((community) => ({
    value: community.id,
    label: community.name,
  }));
});

const bloodTypeOptions = computed(() =>
  props.bloodTypes.map((blood) => ({ value: blood.value, label: blood.label })),
);

const levelOptions = computed(() =>
  props.levels.map((level) => ({ value: level.id, label: level.name })),
);

const toggleLevel = (levelId) => {
  const normalizedId = Number(levelId);
  if (form.level_ids.includes(normalizedId)) {
    form.level_ids = form.level_ids.filter((id) => id !== normalizedId);
    return;
  }

  form.level_ids = [...form.level_ids, normalizedId];
};

const submit = () => {
  form.post(`/externos/${props.externo.id}/import`, { preserveScroll: true });
};
</script>

<template>
  <AppShell :page-title="'Importar externo'">
    <form @submit.prevent="submit">
      <CatalogHeader
        title="Importar externo"
        subtitle="Pase el registro de la base externa (Hostinger) al módulo de niños"
        back-href="/externos"
        :icon="ArrowRightLeft"
      >
      </CatalogHeader>

      <div
        class="mb-3 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-8"
      >
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
                :model-value="externo.birthdate"
                label="Fecha de nacimiento"
                disabled
              />
              <UnderlineField :model-value="sexLabel" label="Sexo" disabled />
              <UnderlineField
                v-model="form.blood_type"
                label="Tipo de sangre"
                as="select"
                placeholder="Selecciona..."
                :options="bloodTypeOptions"
                :error="form.errors.blood_type"
                required
              />
            </div>
            <p class="mt-4 text-xs text-slate-400">
              Nombre y apellidos son editables; la fecha de nacimiento y el sexo provienen del
              registro externo y no se pueden modificar.
            </p>
          </UnderlineSection>

          <UnderlineSection title="Catecismo">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2">
              <UnderlineField
                :model-value="defaultChurchId"
                label="Iglesia"
                as="select"
                :options="churchOptions"
                number-value
                disabled
              />
              <UnderlineField :model-value="municipalityName" label="Municipio" disabled />
              <UnderlineField
                v-model="form.community_id"
                label="Comunidad"
                as="select"
                placeholder="Selecciona..."
                :options="communityOptions"
                number-value
                :error="form.errors.community_id"
                required
              />

              <div class="md:col-span-2">
                <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-4 dark:border-sky-900/60 dark:bg-sky-950/30">
                  <p class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-300">
                    Asignación de nivel
                  </p>
                  <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                      <dt class="text-xs text-slate-400 dark:text-slate-400">Nivel actual (Hostinger)</dt>
                      <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">
                        {{ currentLevel?.name ?? 'Sin nivel' }}
                      </dd>
                    </div>
                    <div>
                      <dt class="text-xs text-slate-400 dark:text-slate-400">
                        {{ editLevel ? 'Niveles seleccionados' : 'Nivel a asignar' }}
                      </dt>
                      <dd v-if="!editLevel" class="mt-0.5 font-semibold text-sky-700 dark:text-sky-300">
                        {{ targetLevel?.name ?? 'Sin asignar' }}
                      </dd>
                      <dd v-else class="mt-0.5 font-semibold text-sky-700 dark:text-sky-300">
                        {{
                          form.level_ids.length > 0
                            ? form.level_ids
                                .map((id) => levelOptions.find((level) => level.value === id)?.label)
                                .filter(Boolean)
                                .join(', ')
                            : 'Sin asignar'
                        }}
                      </dd>
                    </div>
                  </dl>

                  <label
                    class="mt-4 flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
                  >
                    <input v-model="editLevel" type="checkbox" class="checkbox checkbox-primary checkbox-sm" />
                    Editar nivel
                  </label>

                  <div v-if="editLevel" class="mt-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                      <span class="block text-sm font-bold text-slate-600 dark:text-slate-300">
                        Niveles disponibles
                      </span>
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
                      No hay niveles activos disponibles para esta parroquia.
                    </p>
                  </div>

                  <p v-if="!editLevel && !targetLevel" class="mt-3 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    El niño está en el último nivel o no se pudo determinar; activa "Editar nivel" para elegir uno.
                  </p>
                  <p v-if="!editLevel" class="mt-1 text-xs text-slate-400">
                    El nivel se asigna automáticamente un nivel adelante; activa "Editar nivel" para corregirlo o asignar varios.
                  </p>
                </div>
              </div>
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
                placeholder="Notas del registro externo"
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
                  Confirmo que se aceptaron los términos de privacidad para importar y tratar los
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
          href="/externos"
          class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:bg-slate-800"
        >
          Cancelar
        </Link>

        <button
          type="submit"
          class="btn btn-sm rounded-xl border-0 bg-rose-700 text-white shadow-md shadow-rose-900/20 transition-all hover:-translate-y-0.5 hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Importando...' : 'Importar al módulo de niños' }}
        </button>
      </div>
    </form>
  </AppShell>
</template>
