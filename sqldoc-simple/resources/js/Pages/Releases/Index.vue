<template>
  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div class="flex items-center justify-between">
        <h2 id="releases-header" class="text-xl font-semibold text-gray-800">
          <span class="text-gray-500 font-normal">Releases :</span> 
          Release management
          <span v-if="currentProject" class="text-blue-600 font-medium">
            - {{ currentProject.name }}
          </span>
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
              Need help ?
            </span>
          </button>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        <!-- Message si aucun projet sélectionné -->
        <div v-if="!loading && !currentProject" class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg shadow-sm">
          <div class="flex items-center">
            <svg class="h-5 w-5 text-yellow-400 mr-3" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div class="text-yellow-700">
              No project selected. Please select a project to access the release page.
            </div>
          </div>
        </div>

        <!-- Loading state -->
        <div v-else-if="loading" class="bg-white rounded-lg shadow-sm p-6">
          <div class="animate-pulse space-y-4">
            <div class="h-4 bg-gray-200 rounded w-1/4"></div>
            <div class="space-y-3">
              <div class="h-4 bg-gray-200 rounded"></div>
              <div class="h-4 bg-gray-200 rounded"></div>
              <div class="h-4 bg-gray-200 rounded"></div>
            </div>
          </div>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="bg-red-50 border-l-4 border-red-400 p-6 rounded-lg shadow-sm">
          <div class="flex items-center">
            <svg class="h-5 w-5 text-red-400 mr-3" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <div class="text-red-700">{{ error }}</div>
          </div>
        </div>

        <!-- Success state -->
        <div v-else class="space-y-8">
          <!-- Liste des versions -->
          <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">
                  <svg class="h-5 w-5 text-gray-500 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  Available releases for {{ currentProject?.name }}
                </h3>
                <div class="flex space-x-2">
                  <!-- ✅ ID ajouté -->
                  <div class="relative">
                    <input 
                      id="search-input"
                      v-model="searchQuery" 
                      type="text" 
                      placeholder="Search..." 
                      class="px-3 py-1.5 text-sm border rounded focus:ring-blue-500 focus:border-blue-500"
                    />
                    <svg v-if="searchQuery" @click="searchQuery = ''" class="absolute top-2 right-2 h-4 w-4 text-gray-400 cursor-pointer hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </div>
                  <!-- ✅ ID ajouté -->
                  <select 
                    id="filter-version"
                    v-model="filterVersion" 
                    class="pl-2 pr-6 py-1.5 text-sm border rounded focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">All releases</option>
                    <option v-for="version in uniqueVersions" :key="version" :value="version">
                      {{ version }}
                    </option>
                  </select>
                  <!-- ✅ ID ajouté -->
                  <PrimaryButton id="add-release-button" @click="showAddReleaseModal = true">
                    Add a release
                  </PrimaryButton>
                </div>
              </div>
            </div>
            <!-- ✅ ID ajouté -->
            <div id="releases-table" class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead>
                  <tr class="bg-gray-50">
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Release
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Description
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Associated columns
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Created at
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(release, index) in filteredReleases" 
                      :key="release.id"
                      class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8">
                          <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                          </div>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium text-blue-600">
                            {{ release.version_number }}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                      {{ release.description || '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ release.column_count || 0 }} columns
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ release.created_at }}
                    </td>
                    <!-- ✅ ID ajouté à la première ligne d'actions -->
                    <td :id="index === 0 ? 'release-actions' : undefined" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div class="flex space-x-2">
                        <button @click="editRelease(release)" class="text-indigo-600 hover:text-indigo-900" title="Modifier">
                          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </button>
                        <button @click="confirmDeleteRelease(release)" class="text-red-600 hover:text-red-900" title="Supprimer">
                          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="filteredReleases.length === 0">
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                      <div class="flex flex-col items-center py-4">
                        <svg class="h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <p class="text-gray-500">No releases found for this project</p>
                        <p class="text-sm text-gray-400 mt-1">Create your first release to get started</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal pour ajouter une version -->
    <div v-if="showAddReleaseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            {{ editingReleaseId ? 'Edit release' : 'Add new release' }}
            <span v-if="currentProject" class="text-sm text-gray-500 font-normal">
              for {{ currentProject.name }}
            </span>
          </h3>
          <button @click="closeReleaseModal" class="text-gray-400 hover:text-gray-500">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <form @submit.prevent="saveRelease">
          <div class="space-y-4">
            <div>
              <label for="version_number" class="block text-sm font-medium text-gray-700">Release number</label>
              <input 
                id="version_number" 
                v-model="newRelease.version_number" 
                type="text" 
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="e.g: 1.0.0, v2.1.3, Release-2024.1"
              >
              <p class="mt-1 text-sm text-gray-500">Enter a unique version number for this project</p>
            </div>
            
            <div v-if="currentProject">
              <label class="block text-sm font-medium text-gray-700">Project</label>
              <div class="mt-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm text-gray-700">
                <div class="flex items-center">
                  <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0h2M7 7h.01M7 10h.01M7 13h.01"/>
                  </svg>
                  {{ currentProject.name }}
                </div>
              </div>
              <p class="mt-1 text-sm text-gray-500">This release will be created for the current project</p>
            </div>
            
            <div>
              <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
              <textarea 
                id="description" 
                v-model="newRelease.description" 
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Optional description of this release (new features, bug fixes, etc.)"
              ></textarea>
            </div>
            
            <div v-if="editingReleaseId">
              <label class="block text-sm font-medium text-gray-700">Creation date</label>
              <div class="mt-1 text-sm text-gray-500">{{ newRelease.created_at || 'Not available' }}</div>
            </div>
          </div>
          
          <div class="mt-6 flex justify-end space-x-3">
            <button 
              type="button"
              @click="closeReleaseModal"
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >
              Cancel
            </button>
            <button 
              type="submit"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              :disabled="savingRelease"
            >
              {{ savingRelease ? 'Saving...' : (editingReleaseId ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Modal de confirmation de suppression -->
    <div v-if="showDeleteConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
        <div class="flex flex-col items-center">
          <svg class="h-16 w-16 text-red-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2 text-center">
            Delete release
          </h3>
          <p class="text-sm text-gray-500 text-center mb-6">
            Are you sure you want to delete the release <span class="font-semibold text-gray-900">{{ releaseToDelete?.version_number }}</span>?<br>
            <span v-if="releaseToDelete?.column_count > 0" class="text-red-600 font-medium">
              This release is associated with {{ releaseToDelete.column_count }} columns.
            </span><br>
            This action cannot be undone.
          </p>
          
          <div class="flex justify-center space-x-4 w-full">
            <button 
              @click="showDeleteConfirmModal = false"
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >
              Cancel
            </button>
            <button 
              @click="deleteRelease"
              class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
              :disabled="deletingRelease"
            >
              {{ deletingRelease ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { useToast } from '@/Composables/useToast'
import { useDriver } from '@/Composables/useDriver.js'  

const { success, error: showError, warning, info } = useToast()
const { showReleaseGuide } = useDriver() 

const error = ref(null);
const loading = ref(true);
const releases = ref([]);
const uniqueVersions = ref([]);
const currentProject = ref(null);

const searchQuery = ref('');
const filterVersion = ref('');

const showAddReleaseModal = ref(false);
const savingRelease = ref(false);
const editingReleaseId = ref(null);
const newRelease = ref({
  version_number: '',
  description: '',
  created_at: null
});

const showDeleteConfirmModal = ref(false);
const deletingRelease = ref(false);
const releaseToDelete = ref(null);

// Fonction pour relancer le tutoriel
const restartTutorial = () => {
  localStorage.removeItem('release_tutorial_shown')
  showReleaseGuide()
}

onMounted(async () => {
  try {
    await loadReleases();
    
    // Lancer le tutoriel au premier chargement SI un projet est sélectionné
    const tutorialShown = localStorage.getItem('release_tutorial_shown');
    if (!tutorialShown && currentProject.value) {
      setTimeout(() => {
        showReleaseGuide();
        localStorage.setItem('release_tutorial_shown', 'true');
      }, 1000);
    }
  } catch (err) {
    console.error('Erreur lors du chargement des données:', err);
    error(`Erreur de chargement: ${err.response?.data?.error || err.message}`);
  } finally {
    loading.value = false;
  }
});

const loadReleases = async () => {
  try {
    console.log('🔍 DÉBUT loadReleases');
    const response = await axios.get('/api/releases');
    console.log('🔍 Réponse complète:', response.data);
    
    if (response.data && typeof response.data === 'object') {
      releases.value = response.data.releases || [];
      uniqueVersions.value = response.data.uniqueVersions || [];
      currentProject.value = response.data.currentProject || null;

      console.log('🔍 APRÈS ASSIGNATION:');
      console.log('  - currentProject.value:', currentProject.value);
      console.log('  - Type:', typeof currentProject.value);
      console.log('  - === null:', currentProject.value === null);
      console.log('  - === undefined:', currentProject.value === undefined);
      console.log('  - Truthy/Falsy:', !!currentProject.value);
      console.log('  - JSON stringify:', JSON.stringify(currentProject.value));
      
    } else {
      throw new Error('Format de réponse inattendu');
    }
  } catch (err) {
    console.error('🔍 ERREUR dans loadReleases:', err);
    throw err;
  }
};

const filteredReleases = computed(() => {
  return releases.value.filter(release => {
    const matchesSearch = searchQuery.value === '' || 
      release.version_number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (release.description && release.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
    
    const matchesVersion = filterVersion.value === '' || release.version_number === filterVersion.value;
    
    return matchesSearch && matchesVersion;
  });
});

const editRelease = (release) => {
  editingReleaseId.value = release.id;
  newRelease.value = {
    version_number: release.version_number,
    description: release.description || '',
    created_at: release.created_at
  };
  showAddReleaseModal.value = true;
  info(`Édition de la release ${release.version_number}`);
};

const closeReleaseModal = () => {
  showAddReleaseModal.value = false;
  editingReleaseId.value = null;
  newRelease.value = {
    version_number: '',
    description: '',
    created_at: null
  };
};

const saveRelease = async () => {
  try {
    savingRelease.value = true;
    
    if (!newRelease.value.version_number?.trim()) {
      warning('Release version is needed');
      return;
    }
    
    let response;
    if (editingReleaseId.value) {
      response = await axios.post(`/api/releases/${editingReleaseId.value}`, newRelease.value);
    } else {
      response = await axios.post('/api/releases', newRelease.value);
    }
    
    if (response.data.success) {
      await loadReleases();
      closeReleaseModal();
      
      success(
        editingReleaseId.value 
          ? `🎯 Release ${newRelease.value.version_number} Updated successfully!`
          : `🚀 Release ${newRelease.value.version_number} created successfully!`
      );
    } else {
      throw new Error(response.data.error || 'Error');
    }
  } catch (err) {
    console.error('Erreur lors de la sauvegarde:', err);
    showError(
      `❌ Error while saving: ${err.response?.data?.error || err.message}`
    );
  } finally {
    savingRelease.value = false;
  }
};

const confirmDeleteRelease = (release) => {
  releaseToDelete.value = release;
  showDeleteConfirmModal.value = true;
  warning(`You are about to deleted release: ${release.version_number}`);
};

const deleteRelease = async () => {
  try {
    deletingRelease.value = true;
    
    const response = await axios.delete(`/api/releases/${releaseToDelete.value.id}`);
    
    if (response.data.success) {
      const deletedVersion = releaseToDelete.value.version_number;
      await loadReleases();
      showDeleteConfirmModal.value = false;
      releaseToDelete.value = null;
      success(`🗑️ Release ${deletedVersion} Deleted successfully`);
    } else {
      throw new Error(response.data.error || 'Error while deleting');
    }
  } catch (err) {
    console.error('Error while deleting:', err);
    error(
      `❌ Error while deleting: ${err.response?.data?.error || err.message}`
    );
  } finally {
    deletingRelease.value = false;
  }
};

const cancelDelete = () => {
  showDeleteConfirmModal.value = false;
  releaseToDelete.value = null;
  info('cancel delete');
};
</script>