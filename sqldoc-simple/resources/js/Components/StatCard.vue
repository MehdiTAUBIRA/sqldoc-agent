<template>
  <div class="bg-white overflow-hidden shadow-sm rounded-lg">
    <div class="p-6">
      <div class="flex items-center">
        <div :class="`flex-shrink-0 ${iconClasses}`">
          <svg v-if="icon === 'default'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
          </svg>
          
          <svg v-else-if="icon === 'table'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 11h16M11 4v16"/>
          </svg>
          
          <svg v-else-if="icon === 'view'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          
          <svg v-else-if="icon === 'procedure'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/>
          </svg>
          
          <svg v-else-if="icon === 'function'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M4 4l4 4-4 4"/>
          </svg>
          
          <svg v-else-if="icon === 'trigger'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
          
          <svg v-else-if="icon === 'column'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
          </svg>
          
          <svg v-else-if="icon === 'key'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
          </svg>
          
          <svg v-else-if="icon === 'link'" class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
          </svg>
        </div>
        <div class="ml-5 w-0 flex-1">
          <dt class="text-sm font-medium text-gray-500 truncate">
            {{ title }}
          </dt>
          <dd class="flex items-baseline">
            <div class="text-2xl font-semibold text-gray-900">
              {{ count }}
            </div>
          </dd>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  count: {
    type: Number,
    required: true
  },
  color: {
    type: String,
    default: 'blue',
    validator: (value) => [
      'blue', 'green', 'red', 'yellow', 'indigo', 'purple', 
      'pink', 'orange', 'amber', 'teal', 'cyan'
    ].includes(value)
  },
  icon: {
    type: String,
    default: 'default',
    validator: (value) => [
      'default', 'table', 'view', 'procedure', 'function',
      'trigger', 'column', 'key', 'link'
    ].includes(value)
  }
});

// Classes CSS pour la couleur de l'icône
const iconClasses = computed(() => {
  const colorClasses = {
    blue: 'text-blue-500 bg-blue-100',
    green: 'text-green-500 bg-green-100',
    red: 'text-red-500 bg-red-100',
    yellow: 'text-yellow-500 bg-yellow-100',
    indigo: 'text-indigo-500 bg-indigo-100',
    purple: 'text-purple-500 bg-purple-100',
    pink: 'text-pink-500 bg-pink-100',
    orange: 'text-orange-500 bg-orange-100',
    amber: 'text-amber-500 bg-amber-100',
    teal: 'text-teal-500 bg-teal-100',
    cyan: 'text-cyan-500 bg-cyan-100'
  };
  
  return `rounded-md p-3 ${colorClasses[props.color]}`;
});
</script>