<template>
  <transition
      enter-active-class="transition ease-in-out duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in-out duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0">
    <div v-show="$page.props.showingMobileMenu" class="fixed inset-0 z-10 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center"></div>
  </transition>
  <transition
      enter-active-class="transition ease-in-out duration-150"
      enter-from-class="opacity-0 transform -translate-x-20"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in-out duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 transform -translate-x-20">
    <aside v-show="$page.props.showingMobileMenu" class="fixed inset-y-0 z-20 flex-shrink-0 w-64 mt-16 overflow-y-auto bg-blue-500 md:hidden">
      <div class="py-4 text-gray-500">
        <Link class="ml-6 text-lg font-bold text-gray-800" :href="route('dashboard')">
        SQL-INFO 2025
      </Link>
        <ul class="mt-6">
          <li class="relative px-6 py-3">
            <ResponsiveNavLink :href="route('admin')" :active="route().current('admin')">
              <template #icon>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </template>
              Administration
            </ResponsiveNavLink>
          </li>

          <li class="relative px-6 py-3">
            <ResponsiveNavLink :href="route('releases.index')" :active="route().current('releases.index')">
              <template #icon>
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round"
                    stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
              </template>
              Releases
            </ResponsiveNavLink>
          </li>

          <li class="relative px-6 py-3">
            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
              <template #icon>
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round"
                    stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
              </template>
              Dashboard
            </ResponsiveNavLink>
          </li>

        <li class="relative px-6 py-3">
          <ResponsiveNavLink :href="route('projects.index')" :active="route().current('projects.index')">
            <template #icon>
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
              </svg>
            </template>
            My project
          </ResponsiveNavLink>
        </li>
        </ul>

        <!-- Indicateur de chargement ou erreur -->
        <div v-if="!navigationData || navigationData.metadata?.error" class="px-6 py-4">
          <div v-if="navigationData?.metadata?.error" class="text-red-300 text-xs">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Erreur de chargement
            <button @click="refreshNavigation" class="ml-2 text-blue-200 hover:text-white underline">
              Actualiser
            </button>
          </div>
          <div v-else class="text-gray-300 text-xs text-center">
            <svg class="animate-spin h-4 w-4 mx-auto" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Chargement...
          </div>
        </div>

        <!-- Interface de navigation normale -->
        <div v-else>
          <!-- Barre de recherche -->
          <div class="px-6 mt-4">
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Rechercher..."
              class="w-full px-3 py-2 text-sm text-gray-900 bg-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600"
            />
          </div>

          <!-- Filtres -->
          <div class="px-6 mt-2 flex flex-wrap gap-2">
            <button
              v-for="filter in filters"
              :key="filter.type"
              @click="toggleFilter(filter.type)"
              :class="[
                'px-2 py-1 text-xs rounded-full transition-colors',
                activeFilters.includes(filter.type)
                  ? 'bg-blue-700 text-white'
                  : 'bg-gray-200 text-gray-800'
              ]"
            >
              {{ filter.label }} ({{ getFilterCount(filter.type) }})
            </button>
          </div>

          <!-- Section Tables -->
          <div v-if="shouldShowSection('tables')" class="px-6 py-3">
            <button @click="toggleSection('tables')" class="flex items-center w-full text-white">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :d="isOpen.tables ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
              </svg>
              Tables ({{ filteredTables.length }})
            </button>
            <ul v-if="isOpen.tables" class="pl-4 mt-2 max-h-64 overflow-y-auto">
              <li v-for="table in filteredTables" :key="table.id" class="py-1 hover:text-white cursor-pointer text-sm">
                <Link 
                  :href="route('table.details', { tableName: table.name })" 
                  class="flex items-center text-gray-200 hover:text-white"
                  preserve-state
                  preserve-scroll
                >
                  <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="18" rx="2" ry="2" stroke-width="2"/>
                    <rect x="2" y="3" width="20" height="4" fill="currentColor" opacity="0.1"/>
                    <line x1="2" y1="9" x2="22" y2="9" stroke-width="2"/>
                    <line x1="2" y1="13" x2="22" y2="13" stroke-width="2"/>
                    <line x1="2" y1="17" x2="22" y2="17" stroke-width="2"/>
                    <line x1="8" y1="3" x2="8" y2="21" stroke-width="2"/>
                    <line x1="14" y1="3" x2="14" y2="21" stroke-width="2"/>
                  </svg>
                  {{ table.name }}
                  <span v-if="table.has_primary_key" class="ml-auto">
                    <svg class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M10 2L3 7v11h14V7l-7-5z"/>
                    </svg>
                  </span>
                </Link>
              </li>
              <li v-if="filteredTables.length === 0" class="py-1 text-gray-400 italic text-sm">
                No Tables found
              </li>
            </ul>
          </div>

          <!-- Section Vues -->
          <div v-if="shouldShowSection('views')" class="px-6 py-3">
            <button @click="toggleSection('views')" class="flex items-center w-full text-white">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :d="isOpen.views ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
              </svg>
              Views ({{ filteredViews.length }})
            </button>
            <ul v-if="isOpen.views" class="pl-4 mt-2 max-h-64 overflow-y-auto">
              <li v-for="view in filteredViews" :key="view.id" class="py-1 hover:text-white cursor-pointer text-sm">
                <Link 
                  :href="route('view.details', { viewName: view.name })" 
                  class="flex items-center text-gray-200 hover:text-white"
                  preserve-state
                  preserve-scroll
                >
                  <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                    <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" 
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                  </svg>
                  {{ view.name }}
                </Link>
              </li>
              <li v-if="filteredViews.length === 0" class="py-1 text-gray-400 italic text-sm">
                No Views found
              </li>
            </ul>
          </div>

          <!-- Section Fonctions -->
          <div v-if="shouldShowSection('functions')" class="px-6 py-3">
            <button @click="toggleSection('functions')" class="flex items-center w-full text-white">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :d="isOpen.functions ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
              </svg>
              Functions ({{ filteredFunctions.length }})
            </button>
            <ul v-if="isOpen.functions" class="pl-4 mt-2 max-h-64 overflow-y-auto">
              <li v-for="func in filteredFunctions" :key="func.id" class="py-1 hover:text-white cursor-pointer text-sm">
                <Link 
                  :href="route('function.details', { functionName: func.name })" 
                  class="flex items-center text-gray-200 hover:text-white"
                  preserve-state
                  preserve-scroll
                >
                  <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M10 20l4-16m4 4l4 4-4 4M4 4l4 4-4 4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                  </svg>
                  {{ func.name }}
                </Link>
              </li>
              <li v-if="filteredFunctions.length === 0" class="py-1 text-gray-400 italic text-sm">
                No functions found
              </li>
            </ul>
          </div>

          <!-- Section Procédures -->
          <div v-if="shouldShowSection('procedures')" class="px-6 py-3">
            <button @click="toggleSection('procedures')" class="flex items-center w-full text-white">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :d="isOpen.procedures ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
              </svg>
              Procedures ({{ filteredProcedures.length }})
            </button>
            <ul v-if="isOpen.procedures" class="pl-4 mt-2 max-h-64 overflow-y-auto">
              <li v-for="proc in filteredProcedures" :key="proc.id" class="py-1 hover:text-white cursor-pointer text-sm">
                <Link 
                  :href="route('procedure.details', { procedureName: proc.name })" 
                  class="flex items-center text-gray-200 hover:text-white"
                  preserve-state
                  preserve-scroll
                >
                  <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" 
                          stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                  </svg>
                  {{ proc.name }}
                </Link>
              </li>
              <li v-if="filteredProcedures.length === 0" class="py-1 text-gray-400 italic text-sm">
                No procedures found
              </li>
            </ul>
          </div>

          <!-- Section Triggers -->
          <div v-if="shouldShowSection('triggers')" class="px-6 py-3">
            <button @click="toggleSection('triggers')" class="flex items-center w-full text-white">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :d="isOpen.triggers ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
              </svg>
              Triggers ({{ filteredTriggers.length }})
            </button>
            <ul v-if="isOpen.triggers" class="pl-4 mt-2 max-h-64 overflow-y-auto">
              <li v-for="trigger in filteredTriggers" :key="trigger.id" class="py-1 hover:text-white cursor-pointer text-sm">
                <Link 
                  :href="route('trigger.details', { triggerName: trigger.name })" 
                  class="flex items-center text-gray-200 hover:text-white"
                  preserve-state
                  preserve-scroll
                >
                  <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                  </svg>
                  {{ trigger.name }}
                </Link>
              </li>
              <li v-if="filteredTriggers.length === 0" class="py-1 text-gray-400 italic text-sm">
                No triggers found
              </li>
            </ul>
          </div>
        </div>
      </div>
    </aside>
  </transition>
</template>

<script setup>
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'

const page = usePage()

// Données partagées depuis AppServiceProvider (CORRIGÉ)
const navigationData = computed(() => page.props.navigationData)

// États pour les sections dépliables
const isOpen = ref({
  tables: true,
  views: true,
  functions: true,
  procedures: true,
  triggers: true,
})

// État pour la recherche et filtres
const searchQuery = ref('')
const activeFilters = ref(['tables', 'views', 'functions', 'procedures', 'triggers'])

// Configuration des filtres
const filters = [
  { type: 'tables', label: 'Tables' },
  { type: 'views', label: 'Vues' },
  { type: 'functions', label: 'Fonctions' },
  { type: 'procedures', label: 'Procedures' },
  { type: 'triggers', label: 'Triggers' }
]

// Fonctions de navigation
const toggleSection = (section) => {
  isOpen.value[section] = !isOpen.value[section]
}

const toggleFilter = (filterType) => {
  const index = activeFilters.value.indexOf(filterType)
  if (index === -1) {
    activeFilters.value.push(filterType)
  } else {
    activeFilters.value.splice(index, 1)
  }
}

const shouldShowSection = (section) => {
  return activeFilters.value.includes(section) && getFilterCount(section) > 0
}

const getFilterCount = (type) => {
  return navigationData.value?.[type]?.length || 0
}

// Computed properties pour filtrer les données
const filteredTables = computed(() => {
  if (!navigationData.value?.tables) return []
  
  let tables = navigationData.value.tables
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    tables = tables.filter(table => {
      return table.name.toLowerCase().includes(query) ||
             (table.description && table.description.toLowerCase().includes(query))
    })
  }
  
  return tables.slice(0, 100)
})

const filteredViews = computed(() => {
  if (!navigationData.value?.views) return []
  
  let views = navigationData.value.views
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    views = views.filter(view => {
      return view.name.toLowerCase().includes(query) ||
             (view.description && view.description.toLowerCase().includes(query))
    })
  }
  
  return views.slice(0, 100)
})

const filteredFunctions = computed(() => {
  if (!navigationData.value?.functions) return []
  
  let functions = navigationData.value.functions
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    functions = functions.filter(func => {
      return func.name.toLowerCase().includes(query) ||
             (func.description && func.description.toLowerCase().includes(query))
    })
  }
  
  return functions.slice(0, 100)
})

const filteredProcedures = computed(() => {
  if (!navigationData.value?.procedures) return []
  
  let procedures = navigationData.value.procedures
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    procedures = procedures.filter(proc => {
      return proc.name.toLowerCase().includes(query) ||
             (proc.description && proc.description.toLowerCase().includes(query))
    })
  }
  
  return procedures.slice(0, 100)
})

const filteredTriggers = computed(() => {
  if (!navigationData.value?.triggers) return []
  
  let triggers = navigationData.value.triggers
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    triggers = triggers.filter(trigger => {
      return trigger.name.toLowerCase().includes(query) ||
             (trigger.description && trigger.description.toLowerCase().includes(query))
    })
  }
  
  return triggers.slice(0, 100)
})

// Fonction pour rafraîchir la navigation
const refreshNavigation = async () => {
  try {
    console.log('NavigationMobile - Rafraîchissement demandé')
    
    const response = await fetch('/database-structure/refresh', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      }
    })
    
    if (response.ok) {
      router.reload({ only: ['navigationData'] })
      console.log('NavigationMobile - Rafraîchissement réussi')
    } else {
      console.error('NavigationMobile - Erreur lors du rafraîchissement')
    }
  } catch (error) {
    console.error('NavigationMobile - Erreur réseau lors du rafraîchissement:', error)
  }
}

// Log des informations de debug au montage
onMounted(() => {
  console.log('NavigationMobile - Données chargées depuis props partagées:', {
    hasData: !!navigationData.value,
    metadata: navigationData.value?.metadata,
    tables: navigationData.value?.tables?.length || 0,
    views: navigationData.value?.views?.length || 0,
    functions: navigationData.value?.functions?.length || 0,
    procedures: navigationData.value?.procedures?.length || 0,
    triggers: navigationData.value?.triggers?.length || 0
  })
})
</script>