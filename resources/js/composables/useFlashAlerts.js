import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

export const toastSuccess = (title) =>
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
  });

export const alertError = (title, text = '') =>
  Swal.fire({
    icon: 'error',
    title,
    text,
    confirmButtonColor: '#0369a1',
  });

export const firstError = (errors, fallback) => Object.values(errors ?? {})[0] || fallback;

export function useFlashAlerts() {
  const page = usePage();

  watch(
    () => page.props.flash,
    (flash) => {
      if (flash?.success) toastSuccess(flash.success);
      if (flash?.error) alertError(flash.error);
    },
    { immediate: true },
  );
}
