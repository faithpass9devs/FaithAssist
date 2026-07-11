<script setup>
import { computed, ref, watch } from 'vue';
import { CheckSquare, Search, Square, XSquare } from 'lucide-vue-next';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  groups: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue']);

const moduleSearch = ref('');
const permissionSearch = ref('');
const selectedModuleKey = ref(props.groups[0]?.key ?? null);

watch(
  () => props.groups,
  (groups) => {
    if (!selectedModuleKey.value && groups.length) {
      selectedModuleKey.value = groups[0].key;
    }
  },
  { immediate: true },
);

const filteredGroups = computed(() => {
  const q = moduleSearch.value.toLowerCase();
  if (!q) return props.groups;
  return props.groups.filter((g) => g.label.toLowerCase().includes(q));
});

const currentGroup = computed(() =>
  props.groups.find((g) => g.key === selectedModuleKey.value),
);

const permissionResourceMetaByModule = {
  catechism: {
    children: { singular: 'niño', plural: 'niños', gender: 'm' },
    reinscripciones: { singular: 'reinscripción', plural: 'reinscripciones', gender: 'f' },
  },
  ecclesiastes: {
    capillas: { singular: 'capilla', plural: 'capillas', gender: 'f' },
    decanato: { singular: 'decanato', plural: 'decanatos', gender: 'm' },
    diocesis: { singular: 'diócesis', plural: 'diócesis', gender: 'f' },
    parroquias: { singular: 'parroquia', plural: 'parroquias', gender: 'f' },
  },
  operation: {
    periodos: { singular: 'período', plural: 'períodos', gender: 'm' },
    periodo_movimientos: { singular: 'movimiento de período', plural: 'movimientos de período', gender: 'm' },
    tipos_movimientos_periodo: { singular: 'tipo de movimiento de período', plural: 'tipos de movimiento de período', gender: 'm' },
    niveles: { singular: 'nivel', plural: 'niveles', gender: 'm' },
  },
  masses: {
    weekends: { singular: 'fin de semana de misa', plural: 'fines de semana de misas', gender: 'm' },
    masses: { singular: 'misa', plural: 'misas', gender: 'f' },
    mass_attendance: { singular: 'asistencia de misa', plural: 'asistencias de misa', gender: 'f' },
    tipos_incidencias: { singular: 'tipo de incidencia', plural: 'tipos de incidencias', gender: 'm' },
    incidencias_asistencia: { singular: 'incidencia de asistencia', plural: 'incidencias de asistencia', gender: 'f' },
    incidencias_asitencia: { singular: 'incidencia de asistencia', plural: 'incidencias de asistencia', gender: 'f' },
  },
  regions: {
    estados: { singular: 'estado', plural: 'estados', gender: 'm' },
    municipios: { singular: 'municipio', plural: 'municipios', gender: 'm' },
    comunidades: { singular: 'comunidad', plural: 'comunidades', gender: 'f' },
  },
  security: {
    modulos: { singular: 'módulo', plural: 'módulos', gender: 'm' },
    permisos: { singular: 'permiso', plural: 'permisos', gender: 'm' },
    roles: { singular: 'rol', plural: 'roles', gender: 'm' },
    usuarios: { singular: 'usuario', plural: 'usuarios', gender: 'm' },
  },
  whatsapp: {
    whatsapp: { singular: 'mensaje de WhatsApp', plural: 'mensajes de WhatsApp', gender: 'm' },
  },
};

const capitalize = (value) =>
  value ? value.charAt(0).toUpperCase() + value.slice(1) : value;

const parsePermission = (permissionName) => {
  if (permissionName.includes('.')) {
    const parts = permissionName.split('.');
    return {
      resource: parts[0] ?? '',
      action: parts.slice(1).join('.'),
    };
  }

  const suffixes = ['scope_all', 'create', 'read', 'update', 'delete', 'show', 'export', 'send', 'scan'];
  const matchedSuffix = suffixes.find((suffix) => permissionName.endsWith(`_${suffix}`));

  if (matchedSuffix) {
    const normalizedAction = matchedSuffix === 'scope_all' ? 'scope.all' : matchedSuffix;
    return {
      resource: permissionName.slice(0, -(matchedSuffix.length + 1)),
      action: normalizedAction,
    };
  }

  const parts = permissionName.split('.');
  return {
    resource: parts[0] ?? '',
    action: parts.slice(1).join('.'),
  };
};

const getPermissionMeta = (perm) => {
  const moduleKey = currentGroup.value?.key;
  if (!moduleKey) return null;

  const moduleMeta = permissionResourceMetaByModule[moduleKey];
  if (!moduleMeta) return null;

  const { resource, action } = parsePermission(perm.name);
  const resourceMeta = moduleMeta[resource];
  if (!resourceMeta) return null;

  return { action, ...resourceMeta };
};

const getScopeAllText = (plural, gender) =>
  gender === 'f' ? `todas las ${plural}` : `todos los ${plural}`;

const getIndefiniteArticle = (gender) => (gender === 'f' ? 'una' : 'un');

const getPermissionDisplayName = (perm) => {
  const meta = getPermissionMeta(perm);
  if (!meta) return perm.name;

  switch (meta.action) {
    case 'create':
      return `Registrar ${capitalize(meta.singular)}`;
    case 'read':
      return `Consultar ${capitalize(meta.plural)}`;
    case 'update':
      return `Actualizar ${capitalize(meta.singular)}`;
    case 'delete':
      return `Eliminar ${capitalize(meta.singular)}`;
    case 'show':
      return `Ver módulo de ${capitalize(meta.plural)}`;
    case 'export':
      return `Exportar ${capitalize(meta.plural)}`;
    case 'send':
      return `Enviar ${capitalize(meta.plural)}`;
    case 'scan':
      return `Escanear ${capitalize(meta.plural)}`;
    case 'scope.all':
      return `Ver ${capitalize(getScopeAllText(meta.plural, meta.gender))}`;
    default:
      return perm.name;
  }
};

const getPermissionDisplayDescription = (perm) => {
  const meta = getPermissionMeta(perm);
  if (!meta) return perm.description;

  switch (meta.action) {
    case 'create':
      return `Permite registrar ${getIndefiniteArticle(meta.gender)} ${meta.singular}`;
    case 'read':
      return `Permite consultar ${meta.plural}`;
    case 'update':
      return `Permite actualizar ${meta.singular}`;
    case 'delete':
      return `Permite eliminar ${meta.singular}`;
    case 'show':
      return `Permite acceder al módulo de ${meta.plural}`;
    case 'export':
      return `Permite exportar ${meta.plural}`;
    case 'send':
      return `Permite enviar ${meta.plural}`;
    case 'scan':
      return `Permite escanear ${meta.plural}`;
    case 'scope.all':
      return `Permite ver ${getScopeAllText(meta.plural, meta.gender)}`;
    default:
      return perm.description;
  }
};

const filteredPermissions = computed(() => {
  if (!currentGroup.value) return [];
  const q = permissionSearch.value.toLowerCase();
  if (!q) return currentGroup.value.permissions;
  return currentGroup.value.permissions.filter(
    (p) =>
      p.name.toLowerCase().includes(q) ||
      (p.description ?? '').toLowerCase().includes(q) ||
      getPermissionDisplayName(p).toLowerCase().includes(q) ||
      (getPermissionDisplayDescription(p) ?? '').toLowerCase().includes(q),
  );
});

const totalSelected = computed(() => props.modelValue.length);

const selectedCountForGroup = (groupKey) => {
  const group = props.groups.find((g) => g.key === groupKey);
  if (!group) return 0;
  return group.permissions.filter((p) => props.modelValue.includes(p.id)).length;
};

const isSelected = (permId) => props.modelValue.includes(permId);

const toggle = (permId) => {
  const current = [...props.modelValue];
  const idx = current.indexOf(permId);
  if (idx >= 0) current.splice(idx, 1);
  else current.push(permId);
  emit('update:modelValue', current);
};

const selectAll = () => {
  const ids = filteredPermissions.value.map((p) => p.id);
  const current = [...props.modelValue];
  ids.forEach((id) => {
    if (!current.includes(id)) current.push(id);
  });
  emit('update:modelValue', current);
};

const clearAll = () => {
  const ids = new Set(filteredPermissions.value.map((p) => p.id));
  emit('update:modelValue', props.modelValue.filter((id) => !ids.has(id)));
};

const allFilteredSelected = computed(() =>
  filteredPermissions.value.length > 0 &&
  filteredPermissions.value.every((p) => props.modelValue.includes(p.id)),
);
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3 dark:border-slate-800">
      <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Permisos</h3>
      <span
        v-if="totalSelected > 0"
        class="inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-300"
      >
        <CheckSquare class="h-3.5 w-3.5" />
        {{ totalSelected }} permiso{{ totalSelected !== 1 ? 's' : '' }} seleccionado{{ totalSelected !== 1 ? 's' : '' }}
      </span>
    </div>

    <div class="flex min-h-[420px] divide-x divide-slate-200 dark:divide-slate-800">
      <!-- Left: Modules panel -->
      <aside class="flex w-56 shrink-0 flex-col">
        <div class="border-b border-slate-200 px-3 py-2 dark:border-slate-800">
          <label class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-800/60 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40">
            <Search class="h-3.5 w-3.5 shrink-0 text-slate-400" />
            <input
              v-model="moduleSearch"
              type="text"
              placeholder="Filtrar módulo"
              class="w-full bg-transparent text-xs text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
            />
          </label>
        </div>
        <ul class="flex-1 overflow-y-auto py-1.5">
          <li v-for="group in filteredGroups" :key="group.key">
            <button
              type="button"
              class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition-colors"
              :class="
                selectedModuleKey === group.key
                  ? 'bg-rose-700 font-semibold text-white'
                  : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800/60'
              "
              @click="selectedModuleKey = group.key; permissionSearch = ''"
            >
              <span class="truncate">{{ group.label }}</span>
              <span
                class="ml-2 shrink-0 rounded-full px-1.5 py-0.5 text-xs font-bold"
                :class="
                  selectedModuleKey === group.key
                    ? 'bg-white/25 text-white'
                    : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                "
              >
                {{ selectedCountForGroup(group.key) }}
              </span>
            </button>
          </li>
          <li v-if="filteredGroups.length === 0" class="px-3 py-4 text-center text-xs text-slate-400">
            Sin resultados
          </li>
        </ul>
      </aside>

      <!-- Right: Permissions panel -->
      <section class="flex flex-1 flex-col">
        <!-- Permissions toolbar -->
        <div class="flex items-center gap-2 border-b border-slate-200 px-4 py-2 dark:border-slate-800">
          <button
            type="button"
            title="Seleccionar todos"
            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-sky-700 dark:hover:bg-sky-900/30 dark:hover:text-sky-300"
            :disabled="filteredPermissions.length === 0"
            @click="selectAll"
          >
            <CheckSquare class="h-3.5 w-3.5" />
            Seleccionar todos
          </button>
          <button
            type="button"
            title="Limpiar seleccion"
            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-red-300 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-red-700 dark:hover:bg-red-900/30 dark:hover:text-red-400"
            :disabled="filteredPermissions.length === 0"
            @click="clearAll"
          >
            <XSquare class="h-3.5 w-3.5" />
            Limpiar
          </button>
          <label class="ml-auto flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-800/60 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40">
            <Search class="h-3.5 w-3.5 shrink-0 text-slate-400" />
            <input
              v-model="permissionSearch"
              type="text"
              placeholder="Buscar permiso"
              class="w-48 bg-transparent text-xs text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
            />
          </label>
        </div>

        <!-- Permissions grid -->
        <div class="flex-1 overflow-y-auto p-4">
          <div
            v-if="filteredPermissions.length > 0"
            class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
          >
            <button
              v-for="perm in filteredPermissions"
              :key="perm.id"
              type="button"
              class="flex items-start gap-2.5 rounded-xl border px-3 py-2.5 text-left transition-all"
              :class="
                isSelected(perm.id)
                  ? 'border-sky-300 bg-sky-50 dark:border-sky-700 dark:bg-sky-900/30'
                  : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800/40 dark:hover:border-slate-600'
              "
              @click="toggle(perm.id)"
            >
              <span class="mt-0.5 shrink-0">
                <CheckSquare
                  v-if="isSelected(perm.id)"
                  class="h-4 w-4 text-sky-600 dark:text-sky-400"
                />
                <Square
                  v-else
                  class="h-4 w-4 text-slate-400 dark:text-slate-500"
                />
              </span>
              <span class="min-w-0">
                <span
                  class="block truncate text-xs font-semibold"
                  :class="isSelected(perm.id) ? 'text-sky-700 dark:text-sky-300' : 'text-slate-700 dark:text-slate-200'"
                >
                  {{ getPermissionDisplayName(perm) }}
                </span>
                <span
                  v-if="getPermissionDisplayDescription(perm)"
                  class="block truncate text-xs text-slate-400 dark:text-slate-500"
                >
                  {{ getPermissionDisplayDescription(perm) }}
                </span>
              </span>
            </button>
          </div>

          <div
            v-else-if="currentGroup"
            class="flex h-full min-h-[200px] items-center justify-center text-sm text-slate-400 dark:text-slate-500"
          >
            <span v-if="permissionSearch">
              Sin permisos para
              <strong class="text-slate-600 dark:text-slate-300">"{{ permissionSearch }}"</strong>
            </span>
            <span v-else>Sin permisos en este módulo</span>
          </div>

          <div
            v-else
            class="flex h-full min-h-[200px] items-center justify-center text-sm text-slate-400 dark:text-slate-500"
          >
            Selecciona un módulo
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
