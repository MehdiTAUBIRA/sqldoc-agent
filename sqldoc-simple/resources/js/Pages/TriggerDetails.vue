<template>
    <AuthenticatedLayout>
      <template #header>
        <div class="flex items-center justify-between">
          <h2 id="trigger-header" class="text-xl font-semibold text-gray-800">
            <span class="text-gray-500 font-normal">Trigger :</span> 
            {{ triggerName }}
          </h2>
          <!--  Bouton aide -->
          <button
            @click="restartTutorial"
            class="fixed bottom-6 right-6 bg-indigo-600 text-white p-4 rounded-full shadow-lg hover:bg-indigo-700 hover:shadow-xl transition-all z-50 group"
            title="Show tutorial"
          >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="absolute right-full mr-3 top-1/2 -translate-y-1/2 bg-gray-900 text-white text-sm px-3 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
              help ?
            </span>
          </button>
        </div>
      </template>
  
      <div class="py-12">
        <div class="max-w-10xl mx-auto sm:px-6 lg:px-8 space-y-8">
          
          <!-- État d'erreur -->
          <div v-if="error" 
               class="bg-red-50 border-l-4 border-red-400 p-6 rounded-lg shadow-sm">
            <div class="flex items-center">
              <svg class="h-5 w-5 text-red-400 mr-3" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
              </svg>
              <div class="text-red-700">{{ error }}</div>
            </div>
          </div>
  
          <!-- Contenu principal -->
          <div class="space-y-8">
  
            <!-- ID ajouté - Description du trigger -->
            <div id="trigger-description" class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <div class="flex justify-between items-center">
                  <h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Description
                  </h3>
                  <!-- ID ajouté -->
                  <button 
                    id="save-description-button"
                    @click="saveDescription" 
                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :disabled="saving"
                  >
                    <span v-if="!saving">Save</span>
                    <span v-else class="flex items-center">
                      <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Saving...
                    </span>
                  </button>
                </div>
              </div>
              <div class="p-6">
                <textarea
                  v-model="form.description"
                  rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  placeholder="Trigger description (usage, behavior, impact...)"
                  :disabled="saving"
                ></textarea>
              </div>
            </div>
  
            <!-- ID ajouté - Informations générales -->
            <div id="trigger-info" class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900 flex items-center gap-1">
                  <svg class="h-5 w-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                  Information
                </h3>
              </div>
              <div class="p-6 grid grid-cols-2 gap-6">
                <div>
                  <p class="text-sm text-gray-600">Table</p>
                  <p class="mt-1 font-medium">{{ triggerDetails.table_name || '-' }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Schema</p>
                  <p class="mt-1 font-medium">{{ triggerDetails.schema || '-' }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Type</p>
                  <p class="mt-1">
                    <span v-if="triggerDetails.trigger_type" 
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      {{ triggerDetails.trigger_type }}
                    </span>
                    <span v-else class="text-gray-400">-</span>
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Event</p>
                  <p class="mt-1">
                    <span v-if="triggerDetails.trigger_event" 
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      {{ triggerDetails.trigger_event }}
                    </span>
                    <span v-else class="text-gray-400">-</span>
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">State</p>
                  <p class="mt-1">
                    <span :class="[
                      'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                      triggerDetails.is_disabled 
                        ? 'bg-red-100 text-red-800' 
                        : 'bg-green-100 text-green-800'
                    ]">
                      {{ triggerDetails.is_disabled ? 'Desactivated' : 'Activated' }}
                    </span>
                  </p>
                </div>
                <div v-if="triggerDetails.create_date">
                  <p class="text-sm text-gray-600">Creation date</p>
                  <p class="mt-1 font-medium">{{ formatDate(triggerDetails.create_date) }}</p>
                </div>
              </div>
            </div>
  
            <!-- ID ajouté - Définition SQL -->
            <div id="trigger-sql" class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900 flex items-center gap-1">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                  </svg>
                  Definition SQL
                </h3>
              </div>
              <div class="p-6">
                <pre v-if="triggerDetails.definition" 
                     class="whitespace-pre-wrap text-sm font-mono bg-gray-800 p-4 rounded-lg text-gray-50 overflow-auto max-h-96">{{ triggerDetails.definition }}</pre>
                <p v-else class="text-gray-400 italic">No definition available</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
</template>
  
<script setup>
import { ref, onMounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useToast } from '@/Composables/useToast'
import { useDriver } from '@/Composables/useDriver.js' 
import axios from 'axios'

const { success, error: showError, warning, info } = useToast()
const { showTriggerDetailsGuide } = useDriver() 

// Props définies avec des valeurs par défaut
const props = defineProps({
  triggerName: {
    type: String,
    required: true
  },
  triggerDetails: {
    type: Object,
    default: () => ({
      name: '',
      description: '',
      table_name: '',
      schema: null,
      trigger_type: '',
      trigger_event: '',
      is_disabled: false,
      definition: '',
      create_date: null
    })
  },
  error: {
    type: String,
    default: null
  }
})

// ✅ Fonction pour relancer le tutoriel
const restartTutorial = () => {
  localStorage.removeItem('trigger_details_tutorial_shown')
  showTriggerDetailsGuide()
}

// Réactifs locaux simplifiés
const saving = ref(false)
const form = ref({
  description: props.triggerDetails.description || ''
})

// Fonction de formatage de date
const formatDate = (date) => {
  if (!date) return '-';
  const d = new Date(date)
  if (isNaN(d.getTime())) {
    console.warn("Date invalide fournie pour formatDate:", date);
    return 'Invalid date';
  }
  return d.toLocaleString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Initialisation au montage
onMounted(() => {
  form.value.description = props.triggerDetails.description || ''
  console.log('🔍 [TRIGGER] Composant monté avec les données:', props.triggerDetails)
  
  // ✅ Lancer le tutoriel au premier chargement
  const tutorialShown = localStorage.getItem('trigger_details_tutorial_shown')
  if (!tutorialShown && props.triggerDetails && !props.error) {
    setTimeout(() => {
      showTriggerDetailsGuide()
      localStorage.setItem('trigger_details_tutorial_shown', 'true')
    }, 1000)
  }
})

// Fonction de sauvegarde de la description
const saveDescription = async () => {
  if (!props.triggerName) {
    warning('Error: Trigger name missing');
    return;
  }

  try {
    saving.value = true
    
    const response = await axios.post(`/api/trigger/${encodeURIComponent(props.triggerName)}/description`, { 
      description: form.value.description
    })
    
    if (response.data.success) {
      success('Trigger description save successfully')
    } else {
      throw new Error(response.data.error || 'Error while saving')
    }
  } catch (error) {
    console.error('❌ [TRIGGER] Error while saving description:', error)
    showError('Error while saving description: ' + (error.response?.data?.error || error.message))
  } finally {
    saving.value = false
  }
}

// Fonction de sauvegarde complète
const saveAll = async () => {
  if (!props.triggerName) {
    warning('Error: Trigger name missing');
    return;
  }

  try {
    saving.value = true
    
    const triggerData = {
      description: form.value.description,
      language: 'fr'
    }
    
    const response = await axios.post(`/api/trigger/${encodeURIComponent(props.triggerName)}/save-all`, triggerData) 
    
    if (response.data.success) {
      success('Trigger description saved with success')
    } else {
      throw new Error(response.data.error || 'Error while saving')
    }
  } catch (error) {
    console.error('❌ [TRIGGER] Erreur lors de la sauvegarde globale:', error)
    showError('Error while saving trigger informations: ' + (error.response?.data?.error || error.message))
  } finally {
    saving.value = false
  }
}
</script>