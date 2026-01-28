<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-[9999] max-w-sm w-full space-y-3">
      <TransitionGroup name="toast-list" tag="div" class="space-y-3">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :class="getToastClasses(toast)"
          class="transform transition-all duration-300 ease-in-out"
        >
          <!-- Contenu du toast -->
          <div class="flex items-start p-4 rounded-lg shadow-lg border-l-4 bg-white">
            <!-- Icône -->
            <div class="flex-shrink-0 mr-3 mt-0.5">
              <!-- Success Icon -->
              <svg v-if="toast.type === 'success'" class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>

              <!-- Error Icon -->
              <svg v-else-if="toast.type === 'error'" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>

              <!-- Warning Icon -->
              <svg v-else-if="toast.type === 'warning'" class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>

              <!-- Info Icon -->
              <svg v-else class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>

            <!-- Message + Boutons si confirmation -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 break-words">
                {{ toast.message }}
              </p>

              <!-- ✅ Boutons pour les toasts de confirmation -->
              <div v-if="toast.type === 'confirm'" class="mt-3 flex gap-2">
                <button
                  @click="toast.onConfirm?.(); removeToast(toast.id)"
                  class="px-3 py-1 text-white bg-blue-600 hover:bg-blue-700 rounded text-xs"
                >
                  Confirmer
                </button>
                <button
                  @click="toast.onCancel?.(); removeToast(toast.id)"
                  class="px-3 py-1 text-gray-600 bg-gray-200 hover:bg-gray-300 rounded text-xs"
                >
                  Annuler
                </button>
              </div>
            </div>

            <!-- Bouton fermeture (sauf pour confirm) -->
            <button
              v-if="toast.type !== 'confirm'"
              @click="removeToast(toast.id)"
              class="flex-shrink-0 ml-3 text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition-colors duration-200"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Barre de progression (désactivée pour confirm) -->
          <div v-if="toast.duration > 0 && toast.type !== 'confirm'" class="h-1 bg-gray-200 rounded-b-lg overflow-hidden">
            <div
              :class="getProgressBarClass(toast)"
              class="h-full transition-all ease-linear"
              :style="{
                animationDuration: toast.duration + 'ms',
                animationName: 'toast-progress'
              }"
            ></div>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToast } from '@/Composables/useToast'

const { toasts, removeToast } = useToast()

const getToastClasses = (toast) => {
  switch (toast.type) {
    case 'success':
      return 'border-green-400'
    case 'error':
      return 'border-red-400'
    case 'warning':
      return 'border-yellow-400'
    case 'confirm':
      return 'border-blue-400'
    case 'info':
    default:
      return 'border-blue-400'
  }
}

const getProgressBarClass = (toast) => {
  switch (toast.type) {
    case 'success':
      return 'bg-green-400'
    case 'error':
      return 'bg-red-400'
    case 'warning':
      return 'bg-yellow-400'
    case 'info':
    case 'confirm':
    default:
      return 'bg-blue-400'
  }
}
</script>

<style scoped>
.toast-list-enter-active {
  transition: all 0.4s ease-out;
}
.toast-list-leave-active {
  transition: all 0.3s ease-in;
}
.toast-list-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.95);
}
.toast-list-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.95);
}
.toast-list-move {
  transition: transform 0.3s ease;
}

@keyframes toast-progress {
  from {
    width: 100%;
  }
  to {
    width: 0%;
  }
}
</style>