<template>
  <div ref="dropdown" class="relative inline-block text-left">
    <!-- Bouton déclencheur -->
    <div @click="toggleOpen">
      <slot name="trigger" />
    </div>

    <!-- Menu déroulant -->
    <transition name="fade">
      <div
        v-if="open"
        class="absolute right-0 mt-2 w-56 rounded-md border border-gray-200 bg-white shadow-lg z-[9999]"
      >
        <slot name="content" />
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const open = ref(false)
const toggleOpen = () => (open.value = !open.value)

const dropdown = ref(null)

const handleClickOutside = (event) => {
  if (dropdown.value && !dropdown.value.contains(event.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
