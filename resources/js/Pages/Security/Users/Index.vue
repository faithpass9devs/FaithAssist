<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Users, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppPagination from '../../../components/AppPagination.vue';

const props = defineProps({
  users: { type: Object, required: true },
  search: { type: String, default: '' },
});

const formatScope = (name) => name ?? 'Acceso total';

const page = usePage();
const rows = ref([...props.users.data]);
const searchTerm = ref(props.search);
let debounce = null;

const canDelete = computed(() => (page.props.auth?.permissions ?? []).includes('usuarios.delete'));

const getCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

watch(
  () => props.users.data,
  (newData) => {
    rows.value = [...newData];
  },
);

watch(searchTerm, (val) => {
  clearTimeout(debounce);
  debounce = setTimeout(() => {
    router.get('/usuarios', { search: val }, { preserveState: true, replace: true });
  }, 400);
});

const destroyUser = async (user) => {
  const result = await Swal.fire({
    toast: true,
    title: 'Eliminar usuario',
    text: '¿Seguro que deseas eliminar este usuario?',
    icon: 'warning',
    position: 'top-end',
    width: 460,
    backdrop: false,
    showConfirmButton: true,
    showCancelButton: true,
    buttonsStyling: false,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    customClass: {
      popup: 'swal-delete-banner',
      actions: 'swal-delete-banner-actions',
      confirmButton: 'btn btn-error btn-xs',
      cancelButton: 'btn btn-ghost btn-xs',
    },
  });

  if (!result.isConfirmed) return;

  try {
    const response = await fetch(`/usuarios/${user.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': getCsrf(),
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    const json = await response.json();

    if (!response.ok) {
      throw new Error(json?.message ?? 'Ocurrió un error al intentar eliminar el usuario.');
    }

    rows.value = rows.value.filter((row) => row.id !== user.id);

    router.get('/usuarios', { search: searchTerm.value || undefined }, { preserveScroll: true, replace: true });

    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: json?.message ?? 'Usuario eliminado correctamente.',
      showConfirmButton: false,
      timer: 2200,
      timerProgressBar: true,
    });
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'No se pudo eliminar el usuario',
      text: error?.message ?? 'Ocurrió un error al intentar eliminar el usuario.',
    });
  }
};
</script>

<template>
  <AppShell :page-title="'Usuarios'">
    <CatalogHeader
      title="Usuarios"
      subtitle="Gestion de usuarios del sistema"
      back-href="/"
      :count="users.total"
      :icon="Users"
    />

    <!-- Toolbar -->
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <label
        class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 sm:w-80 dark:border-slate-700 dark:bg-slate-900 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40"
      >
        <Search class="h-4 w-4 shrink-0 text-slate-400" />
        <input
          v-model="searchTerm"
          type="text"
          placeholder="Buscar por nombre o correo..."
          class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
        />
        <button
          v-if="searchTerm"
          type="button"
          class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
          @click="searchTerm = ''"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </label>

      <Link
        href="/usuarios/create"
        class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
      >
        <Plus class="h-4 w-4" />
        Nuevo usuario
      </Link>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <table class="table w-full">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
            <th class="px-4 py-3 font-semibold">Usuario</th>
            <th class="px-4 py-3 font-semibold">Correo</th>
            <th class="px-4 py-3 font-semibold">Rol</th>
            <th class="px-4 py-3 font-semibold">Diócesis</th>
            <th class="px-4 py-3 font-semibold">Parroquia</th>
            <th class="px-4 py-3 font-semibold">Capilla</th>
            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in rows"
            :key="user.id"
            class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
          >
            <!-- Avatar + Nombre -->
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <span
                  v-if="user.photo_url"
                  class="h-9 w-9 overflow-hidden rounded-full border border-slate-200 bg-slate-100 dark:border-slate-700"
                >
                  <img :src="user.photo_url" :alt="user.full_name" class="h-full w-full object-cover" />
                </span>
                <span
                  v-else
                  class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-700 text-xs font-bold uppercase text-white dark:bg-rose-800"
                >
                  {{ user.initials }}
                </span>
                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                  {{ user.full_name }}
                </span>
              </div>
            </td>

            <!-- Email -->
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ user.email }}
            </td>

            <!-- Role -->
            <td class="px-4 py-3">
              <span
                v-if="user.role"
                class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-300"
              >
                {{ user.role }}
              </span>
              <span v-else class="text-xs text-slate-400">Sin rol</span>
            </td>

            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ formatScope(user.diocese) }}
            </td>

            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ formatScope(user.church) }}
            </td>

            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ user.chapel ?? '—' }}
            </td>

            <!-- Actions -->
            <td class="px-4 py-3 text-right">
              <div class="inline-flex items-center gap-1">
                <Link
                  :href="`/usuarios/${user.id}/edit`"
                  class="btn btn-ghost btn-xs text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-400 dark:hover:bg-sky-950/40"
                  title="Editar usuario"
                >
                  <Pencil class="h-3.5 w-3.5" />
                </Link>
                <button
                  v-if="canDelete"
                  type="button"
                  class="btn btn-ghost btn-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40"
                  title="Eliminar usuario"
                  @click="destroyUser(user)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="rows.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400 dark:text-slate-500">
              <span v-if="searchTerm">
                No se encontraron usuarios para
                <strong class="text-slate-600 dark:text-slate-300">"{{ searchTerm }}"</strong>.
              </span>
              <span v-else>No hay usuarios registrados.</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="users.links"
      :from="users.from"
      :to="users.to"
      :total="users.total"
    />
  </AppShell>
</template>

<style>
.swal2-popup.swal-delete-banner {
  border: 1px solid rgb(226 232 240);
  border-radius: 14px;
  box-shadow: 0 10px 24px rgb(15 23 42 / 18%);
  padding: 0.9rem 0.95rem;
}

.swal2-popup.swal-delete-banner .swal2-title {
  font-size: 0.95rem;
  font-weight: 700;
}

.swal2-popup.swal-delete-banner .swal2-html-container {
  margin-top: 0.25rem;
  font-size: 0.8rem;
}

.swal-delete-banner-actions {
  margin-top: 0.7rem;
  gap: 0.4rem;
}
</style>
