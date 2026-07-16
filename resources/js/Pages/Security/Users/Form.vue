<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, Check, Church, Eye, EyeOff, KeyRound, MapPinned, ShieldCheck, User, Users, X } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import PermissionSelector from '../../../components/security/PermissionSelector.vue';

const props = defineProps({
  user:                { type: Object, default: null },
  roles:               { type: Array,  required: true },
  permissionGroups:    { type: Array,  required: true },
  dioceses:            { type: Array,  default: () => [] },
  deaneries:           { type: Array,  default: () => [] },
  churches:            { type: Array,  default: () => [] },
  chapels:             { type: Array,  default: () => [] },
  selectedRole:        { type: Number, default: null },
  selectedPermissions: { type: Array,  default: () => [] },
  selectedDiocese:     { type: Number, default: null },
  selectedDeanery:     { type: Number, default: null },
  selectedChurch:      { type: Number, default: null },
  selectedChapel:      { type: Number, default: null },
  editorScope:         { type: Object, default: () => ({ diocese_id: null, deanery_id: null, church_id: null, chapel_id: null }) },
  selectedCountryCode: { type: String, default: '521' },
  countryCodes:        { type: Array,  default: () => [] },
});

const isEditing = computed(() => !!props.user);
const pageTitle = computed(() => (isEditing.value ? `Editar Usuario` : 'Nuevo Usuario'));

/** El editor tiene scope restringido (no es global): no puede cambiar el alcance. */
const scopeLocked = computed(() => props.editorScope.diocese_id !== null);
const canSelectChapelInLockedScope = computed(
  () => scopeLocked.value && props.editorScope.church_id !== null && props.editorScope.chapel_id === null,
);

const activeSection = ref('general');
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const sections = [
  { key: 'general',    label: 'Datos Generales', icon: User },
  { key: 'alcance',    label: 'Alcance',         icon: MapPinned },
  { key: 'roles',      label: 'Roles',           icon: ShieldCheck },
  { key: 'permisos',   label: 'Permisos',        icon: KeyRound },
  { key: 'seguridad',  label: 'Seguridad',       icon: Users },
];

const form = useForm({
  name:                  props.user?.name    ?? '',
  paterno:               props.user?.paterno ?? '',
  materno:               props.user?.materno ?? '',
  email:                 props.user?.email   ?? '',
  whatsapp_country_code: props.user?.whatsapp_country_code ?? props.selectedCountryCode ?? '521',
  whatsapp_phone:        props.user?.whatsapp_phone ?? '',
  role_id:               props.selectedRole,
  diocese_id:            scopeLocked.value && !props.user
    ? props.editorScope.diocese_id
    : (props.selectedDiocese ?? null),
  deanery_id:            scopeLocked.value && !props.user
    ? props.editorScope.deanery_id
    : (props.selectedDeanery ?? null),
  church_id:             scopeLocked.value && !props.user
    ? props.editorScope.church_id
    : (props.selectedChurch ?? null),
  chapel_id:             scopeLocked.value && !props.user
    ? props.editorScope.chapel_id
    : (props.selectedChapel ?? null),
  permissions:           [...props.selectedPermissions],
  password:              '',
  password_confirmation: '',
});

const passwordRules = computed(() => [
  {
    label: 'Mínimo 8 caracteres',
    valid: form.password.length >= 8,
  },
  {
    label: 'Una mayúscula',
    valid: /[A-ZÁÉÍÓÚÑ]/u.test(form.password),
  },
  {
    label: 'Una minúscula',
    valid: /[a-záéíóúñ]/u.test(form.password),
  },
  {
    label: 'Un número',
    valid: /\d/.test(form.password),
  },
]);

const passwordChecklistPassed = computed(() => passwordRules.value.every((rule) => rule.valid));
const canSubmit = computed(() => {
  if (isEditing.value && form.password.length === 0) {
    return true;
  }

  return passwordChecklistPassed.value;
});

const selectedRoleObj = computed(() => props.roles.find((r) => r.id === form.role_id));

const totalPermissions = computed(() => form.permissions.length);
const hasDiocese = computed(() => form.diocese_id !== null);
const hasDeanery = computed(() => form.deanery_id !== null);
const hasChurch = computed(() => form.church_id !== null);
const hasScopeSet = computed(() => hasDiocese.value || hasDeanery.value || hasChurch.value || form.chapel_id !== null);

/** Decanatos filtrados según la diócesis seleccionada. */
const filteredDeaneries = computed(() => {
  if (form.diocese_id === null) return props.deaneries;
  return props.deaneries.filter((d) => d.diocese_id === form.diocese_id);
});

/** Iglesias filtradas según el decanato seleccionado (o todos si no hay decanato). */
const filteredChurches = computed(() => {
  if (form.deanery_id === null) return props.churches;
  return props.churches.filter((c) => c.deanery_id === form.deanery_id);
});

/** Capillas filtradas según la parroquia seleccionada. */
const filteredChapels = computed(() => {
  if (form.church_id === null) return props.chapels;
  return props.chapels.filter((c) => c.church_id === form.church_id);
});

/** Al cambiar diócesis, limpiar decanato e iglesia si ya no aplican. */
watch(
  () => form.diocese_id,
  () => {
    const deaneryValid = filteredDeaneries.value.some((d) => d.id === form.deanery_id);
    if (!deaneryValid) form.deanery_id = null;
  },
);

/** Al cambiar el rol, actualizar los permisos al listado del nuevo rol (solo en edición). */
watch(
  () => form.role_id,
  (newRoleId, oldRoleId) => {
    if (oldRoleId === undefined) return; // inicialización, no disparar
    const role = props.roles.find((r) => r.id === newRoleId);
    form.permissions = role ? [...(role.permissions ?? [])] : [];
  },
);

/** Al cambiar decanato, limpiar iglesia si ya no pertenece al decanato. */
watch(
  () => form.deanery_id,
  () => {
    if (form.church_id === null) return;
    const churchValid = filteredChurches.value.some((c) => c.id === form.church_id);
    if (!churchValid) form.church_id = null;
  },
);

/** Al cambiar parroquia, limpiar capilla si ya no pertenece a la parroquia. */
watch(
  () => form.church_id,
  () => {
    if (form.chapel_id === null) return;
    const chapelValid = filteredChapels.value.some((c) => c.id === form.chapel_id);
    if (!chapelValid) form.chapel_id = null;
  },
);

const submit = () => {
  if (isEditing.value) {
    form.put(`/usuarios/${props.user.id}`, { preserveScroll: true });
  } else {
    form.post('/usuarios');
  }
};
</script>

<template>
  <AppShell :page-title="pageTitle">
    <form @submit.prevent="submit">
      <CatalogHeader
        :title="pageTitle"
        subtitle="Configuracion de cuenta y permisos del usuario"
        back-href="/usuarios"
      >
        <template #actions>
          <Link
            href="/usuarios"
            class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:bg-slate-800"
          >
            Cancelar
          </Link>
          <button
            type="submit"
            class="btn btn-sm rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="form.processing || !canSubmit"
          >
            {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar usuario' : 'Crear usuario') }}
          </button>
        </template>
      </CatalogHeader>

    <!-- User identity header (edit mode) -->
    <div
      v-if="isEditing"
      class="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between"
    >
      <div class="flex items-center gap-4">
        <span
            v-if="user.photo_url"
          class="h-16 w-16 overflow-hidden rounded-full border-2 border-rose-200 shadow"
        >
          <img :src="user.photo_url" :alt="user.full_name" class="h-full w-full object-cover" />
        </span>
        <span
          v-else
          class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-rose-700 text-xl font-black uppercase text-white shadow"
        >
          {{ user.initials }}
        </span>
        <div>
          <p class="text-xl font-black uppercase tracking-wide text-slate-800 dark:text-slate-100">
            {{ user.full_name }}
          </p>
          <p class="text-sm text-slate-500 dark:text-slate-400">{{ user.email }}</p>
        </div>
      </div>
      <div
        v-if="user.created_at"
        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
      >
        <CalendarDays class="h-3.5 w-3.5" />
        Alta: {{ user.created_at }}
      </div>
    </div>

      <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        <!-- Sidebar nav -->
        <aside class="w-full shrink-0 lg:w-52">
          <nav class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
              <li v-for="s in sections" :key="s.key">
                <button
                  type="button"
                  class="flex w-full items-center justify-between px-4 py-3 text-sm transition-colors"
                  :class="
                    activeSection === s.key
                      ? 'bg-slate-100 font-semibold text-slate-900 dark:bg-slate-800 dark:text-slate-100'
                      : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50'
                  "
                  @click="activeSection = s.key"
                >
                  <span class="flex items-center gap-2.5">
                    <component :is="s.icon" class="h-4 w-4 shrink-0" />
                    {{ s.label }}
                  </span>
                  <span
                      v-if="s.key === 'alcance' && hasScopeSet"
                      class="rounded-full bg-rose-700 px-1.5 py-0.5 text-xs font-bold text-white"
                    >
                      {{ [hasDiocese, hasDeanery, hasChurch, form.chapel_id !== null].filter(Boolean).length }}
                    </span>
                    <span
                      v-if="s.key === 'roles' && selectedRoleObj"
                      class="rounded-full bg-rose-700 px-1.5 py-0.5 text-xs font-bold text-white"
                    >
                      1
                    </span>
                  <span
                    v-if="s.key === 'permisos' && totalPermissions > 0"
                    class="rounded-full bg-rose-700 px-1.5 py-0.5 text-xs font-bold text-white"
                  >
                    {{ totalPermissions }}
                  </span>
                </button>
              </li>
            </ul>
          </nav>
        </aside>

        <!-- Main content -->
        <div class="min-w-0 flex-1">

          <!-- Datos Generales -->
          <div v-show="activeSection === 'general'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <h2 class="mb-5 text-sm font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400">
              Informacion basica de la cuenta
            </h2>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  Nombre <span class="text-red-500">*</span>
                </label>
                <input v-model="form.name" type="text" placeholder="Nombre(s)" class="input input-bordered w-full" :class="{ 'input-error': form.errors.name }" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
              </div>

              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  Apellido paterno <span class="text-red-500">*</span>
                </label>
                <input v-model="form.paterno" type="text" placeholder="Apellido paterno" class="input input-bordered w-full" :class="{ 'input-error': form.errors.paterno }" />
                <p v-if="form.errors.paterno" class="mt-1 text-xs text-red-500">{{ form.errors.paterno }}</p>
              </div>

              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  Apellido materno
                </label>
                <input v-model="form.materno" type="text" placeholder="Apellido materno" class="input input-bordered w-full" :class="{ 'input-error': form.errors.materno }" />
                <p v-if="form.errors.materno" class="mt-1 text-xs text-red-500">{{ form.errors.materno }}</p>
              </div>

              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  Correo electronico <span class="text-red-500">*</span>
                </label>
                <input v-model="form.email" type="email" placeholder="correo@ejemplo.com" class="input input-bordered w-full" :class="{ 'input-error': form.errors.email }" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
              </div>

              <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  Numero de telefono
                </label>
                <div class="flex gap-2">
                  <select
                    v-model="form.whatsapp_country_code"
                    class="select select-bordered w-28 shrink-0"
                    :class="{ 'select-error': form.errors.whatsapp_country_code }"
                  >
                    <option v-for="code in countryCodes" :key="code.value" :value="code.value">
                      {{ code.label }}
                    </option>
                  </select>
                  <input v-model="form.whatsapp_phone" type="text" placeholder="5512345678" class="input input-bordered w-full" :class="{ 'input-error': form.errors.whatsapp_phone }" />
                </div>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Este numero se usa para recuperar tu contrasena.</p>
                <p v-if="form.errors.whatsapp_country_code" class="mt-1 text-xs text-red-500">{{ form.errors.whatsapp_country_code }}</p>
                <p v-if="form.errors.whatsapp_phone" class="mt-1 text-xs text-red-500">{{ form.errors.whatsapp_phone }}</p>
              </div>
            </div>
          </div>

          <!-- Alcance -->
          <div v-show="activeSection === 'alcance'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400">
              Alcance de datos
            </h2>
            <p class="mb-5 text-xs text-slate-400 dark:text-slate-500">
              Define hasta qué nivel puede ver datos este usuario. Sin asignación tiene acceso total.
            </p>

            <!-- Aviso de scope bloqueado -->
            <div
              v-if="scopeLocked"
              class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-700 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-400"
            >
              <MapPinned class="mt-0.5 h-3.5 w-3.5 shrink-0" />
              <span>
                {{ canSelectChapelInLockedScope
                  ? 'El alcance se hereda de tu perfil. Puedes asignar una capilla de tu parroquia.'
                  : 'El alcance se hereda de tu perfil y no puede modificarse.' }}
              </span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <!-- Diócesis -->
              <div>
                <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                  <MapPinned class="h-4 w-4 text-rose-700 dark:text-rose-400" />
                  Diócesis
                </label>
                <select
                  v-model="form.diocese_id"
                  class="select select-bordered w-full"
                  :class="{ 'select-error': form.errors.diocese_id }"
                  :disabled="scopeLocked"
                >
                  <option :value="null">— Global (sin restricción) —</option>
                  <option v-for="diocese in dioceses" :key="diocese.id" :value="diocese.id">
                    {{ diocese.name }}
                  </option>
                </select>
                <p v-if="form.errors.diocese_id" class="mt-1 text-xs text-red-500">{{ form.errors.diocese_id }}</p>
              </div>

              <!-- Decanato -->
              <div>
                <label
                  class="mb-1.5 flex items-center gap-2 text-sm font-medium"
                  :class="hasDiocese ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500'"
                >
                  <MapPinned class="h-4 w-4" :class="hasDiocese ? 'text-rose-700 dark:text-rose-400' : 'text-slate-400'" />
                  Decanato
                </label>

                <div v-if="!hasDiocese" class="flex h-10 items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 text-xs text-slate-400 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-500">
                  Selecciona primero una diócesis
                </div>
                <template v-else>
                  <select
                    v-model="form.deanery_id"
                    class="select select-bordered w-full"
                    :class="{ 'select-error': form.errors.deanery_id }"
                    :disabled="scopeLocked"
                  >
                    <option :value="null">— Toda la diócesis —</option>
                    <option v-for="deanery in filteredDeaneries" :key="deanery.id" :value="deanery.id">
                      {{ deanery.name }}
                    </option>
                  </select>
                  <p v-if="form.errors.deanery_id" class="mt-1 text-xs text-red-500">{{ form.errors.deanery_id }}</p>
                </template>
              </div>

              <!-- Parroquia -->
              <div>
                <label
                  class="mb-1.5 flex items-center gap-2 text-sm font-medium"
                  :class="hasDeanery ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500'"
                >
                  <Church class="h-4 w-4" :class="hasDeanery ? 'text-rose-700 dark:text-rose-400' : 'text-slate-400'" />
                  Parroquia
                </label>

                <div v-if="!hasDeanery" class="flex h-10 items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 text-xs text-slate-400 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-500">
                  Selecciona primero un decanato
                </div>
                <template v-else>
                  <select
                    v-model="form.church_id"
                    class="select select-bordered w-full"
                    :class="{ 'select-error': form.errors.church_id }"
                    :disabled="scopeLocked"
                  >
                    <option :value="null">— Todo el decanato —</option>
                    <option v-for="church in filteredChurches" :key="church.id" :value="church.id">
                      {{ church.name }}
                    </option>
                  </select>
                  <p v-if="form.errors.church_id" class="mt-1 text-xs text-red-500">{{ form.errors.church_id }}</p>
                </template>
              </div>

              <!-- Capilla -->
              <div>
                <label
                  class="mb-1.5 flex items-center gap-2 text-sm font-medium"
                  :class="hasChurch ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500'"
                >
                  <Church class="h-4 w-4" :class="hasChurch ? 'text-rose-700 dark:text-rose-400' : 'text-slate-400'" />
                  Capilla
                </label>

                <div v-if="!hasChurch" class="flex h-10 items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 text-xs text-slate-400 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-500">
                  Selecciona primero una parroquia
                </div>
                <template v-else>
                  <select
                    v-model="form.chapel_id"
                    class="select select-bordered w-full"
                    :class="{ 'select-error': form.errors.chapel_id }"
                    :disabled="scopeLocked && !canSelectChapelInLockedScope"
                  >
                    <option :value="null">— Toda la parroquia —</option>
                    <option v-for="chapel in filteredChapels" :key="chapel.id" :value="chapel.id">
                      {{ chapel.name }}
                    </option>
                  </select>
                  <p v-if="form.errors.chapel_id" class="mt-1 text-xs text-red-500">{{ form.errors.chapel_id }}</p>
                </template>
              </div>
            </div>
          </div>

          <!-- Roles -->
          <div v-show="activeSection === 'roles'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400">
              Rol del usuario
            </h2>
            <p class="mb-5 text-xs text-slate-400 dark:text-slate-500">Solo se puede asignar un rol por usuario.</p>

            <div class="grid gap-2 sm:grid-cols-2">
              <!-- None option -->
              <button
                type="button"
                class="flex items-start gap-3 rounded-xl border px-4 py-3 text-left transition-all"
                :class="
                  form.role_id === null
                    ? 'border-rose-300 bg-rose-50 dark:border-rose-700 dark:bg-rose-900/20'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800/40'
                "
                @click="form.role_id = null"
              >
                <span
                  class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2"
                  :class="form.role_id === null ? 'border-rose-600' : 'border-slate-300 dark:border-slate-600'"
                >
                  <span v-if="form.role_id === null" class="h-2 w-2 rounded-full bg-rose-600" />
                </span>
                <span>
                  <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Sin rol</span>
                  <span class="block text-xs text-slate-400">No asignar ningun rol</span>
                </span>
                    1
              </button>

              <button
                v-for="role in roles"
                :key="role.id"
                type="button"
                class="flex items-start gap-3 rounded-xl border px-4 py-3 text-left transition-all"
                :class="
                  form.role_id === role.id
                    ? 'border-rose-300 bg-rose-50 dark:border-rose-700 dark:bg-rose-900/20'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800/40'
                "
                @click="form.role_id = role.id"
              >
                <span
                  class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2"
                  :class="form.role_id === role.id ? 'border-rose-600' : 'border-slate-300 dark:border-slate-600'"
                >
                  <span v-if="form.role_id === role.id" class="h-2 w-2 rounded-full bg-rose-600" />
                </span>
                <span>
                  <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">{{ role.name }}</span>
                  <span v-if="role.description" class="block text-xs text-slate-400 dark:text-slate-500">{{ role.description }}</span>
                </span>
              </button>
            </div>

            <p v-if="form.errors.role_id" class="mt-3 text-xs text-red-500">{{ form.errors.role_id }}</p>
          </div>

          <!-- Permisos -->
          <div v-show="activeSection === 'permisos'">
            <PermissionSelector
              v-model="form.permissions"
              :groups="permissionGroups"
            />
            <p v-if="form.errors.permissions" class="mt-2 text-xs text-red-500">{{ form.errors.permissions }}</p>
          </div>

          <!-- Seguridad -->
          <div v-show="activeSection === 'seguridad'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400">
              Seguridad
            </h2>
            <p class="mb-5 text-xs text-slate-400 dark:text-slate-500">
              {{ isEditing ? 'Deja los campos vacios para mantener la contrasena actual.' : 'Define la contrasena de acceso.' }}
            </p>

            <div class="grid gap-5 lg:grid-cols-2">
              <div class="space-y-1.5">
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  {{ isEditing ? 'Nueva contrasena' : 'Contrasena' }}
                  <span v-if="!isEditing" class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="?"
                    autocomplete="new-password"
                    class="input input-bordered w-full pr-11"
                    :class="{ 'input-error': form.errors.password }"
                  />
                  <button
                    type="button"
                    class="absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center justify-center rounded-lg p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                    :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    @click="showPassword = !showPassword"
                  >
                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                    <Eye v-else class="h-4 w-4" />
                  </button>
                </div>

                <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
              </div>

              <div class="space-y-1.5">
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  Confirmar contrasena
                  <span v-if="!isEditing" class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input
                    v-model="form.password_confirmation"
                    :type="showPasswordConfirmation ? 'text' : 'password'"
                    placeholder=""
                    autocomplete="new-password"
                    class="input input-bordered w-full pr-11"
                    :class="{ 'input-error': form.errors.password_confirmation }"
                  />
                  <button
                    type="button"
                    class="absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center justify-center rounded-lg p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                    :aria-label="showPasswordConfirmation ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                  >
                    <EyeOff v-if="showPasswordConfirmation" class="h-4 w-4" />
                    <Eye v-else class="h-4 w-4" />
                  </button>
                </div>
                <p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-red-500">{{ form.errors.password_confirmation }}</p>
              </div>
            </div>

            <div class="mt-4 rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/40">
              <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div
                  v-for="rule in passwordRules"
                  :key="rule.label"
                  class="flex items-center gap-2 text-sm font-medium"
                  :class="
                    rule.valid
                      ? 'text-emerald-600 dark:text-emerald-300'
                      : 'text-slate-500 dark:text-slate-400'
                  "
                >
                  <span
                    class="flex h-5 w-5 items-center justify-center rounded-full"
                    :class="
                      rule.valid
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'bg-slate-200 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                    "
                  >
                    <Check v-if="rule.valid" class="h-3.5 w-3.5" />
                    <X v-else class="h-3.5 w-3.5" />
                  </span>

                  {{ rule.label }}
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </form>
  </AppShell>
</template>
