import { ref, nextTick } from 'vue'

// État global des toasts (en dehors de la fonction)
const toasts = ref([])
let toastId = 0

// Fonctions globales
const addToast = (toastOptions) => {
  const id = ++toastId

  const defaultToast = {
    id,
    type: 'info',
    message: '',
    duration: 5000,
    visible: false
  }

  const toast = { ...defaultToast, ...toastOptions, id }

  toasts.value.push(toast)

  nextTick(() => {
    const toastEl = toasts.value.find(t => t.id === id)
    if (toastEl) toastEl.visible = true
  })

  if (toast.duration > 0 && toast.type !== 'confirm') {
    setTimeout(() => removeToast(id), toast.duration)
  }

  return id
}

const removeToast = (id) => {
  const index = toasts.value.findIndex(t => t.id === id)
  if (index > -1) {
    toasts.value[index].visible = false
    setTimeout(() => {
      toasts.value.splice(index, 1)
    }, 300)
  }
}

// Fonctions de convenance globales
const success = (message, duration = 5000) =>
  addToast({ type: 'success', message, duration })

const error = (message, duration = 7000) =>
  addToast({ type: 'error', message, duration })

const info = (message, duration = 5000) =>
  addToast({ type: 'info', message, duration })

const warning = (message, duration = 6000) =>
  addToast({ type: 'warning', message, duration })

const confirmToast = ({ message, onConfirm, onCancel }) =>
  addToast({ type: 'confirm', message, duration: 0, onConfirm, onCancel })

export const useToast = () => {
  return {
    toasts,
    addToast,
    removeToast,
    success,
    error,
    info,
    warning,
    confirmToast
  }
}