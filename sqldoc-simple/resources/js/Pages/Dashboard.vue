<template>
	<AuthenticatedLayout>
	  <template #header>
		<div class="flex justify-between items-center">
		  <h2 class="text-xl font-semibold leading-tight text-gray-800">
			Dashboard - {{ dashboardData.project_name }}
		  </h2>
		  <!-- Indicateur de permissions -->
		  <div v-if="dashboardData.permissions" class="flex items-center space-x-2">
			<span class="text-sm text-gray-600">Access Level:</span>
			<span :class="getPermissionBadgeClass()" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
			  {{ getPermissionIcon() }} {{ getPermissionText() }}
			</span>
		  </div>
		</div>
	  </template>
  
	  <div class="py-12">
		<div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
		  <!-- État de chargement -->
		  <div v-if="loading" class="space-y-6">
			<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
			  <div v-for="i in 8" :key="i" class="animate-pulse bg-white p-6 rounded-lg shadow-sm">
				<div class="h-4 bg-gray-200 rounded w-1/2 mb-3"></div>
				<div class="h-8 bg-gray-200 rounded w-1/4"></div>
			  </div>
			</div>
		  </div>
  
		  <!-- État d'erreur -->
		  <div v-else-if="error" class="bg-red-50 border-l-4 border-red-400 p-6 rounded-lg shadow-sm">
			<div class="flex items-center">
			  <svg class="h-5 w-5 text-red-400 mr-3" viewBox="0 0 20 20" fill="currentColor">
				<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
			  </svg>
			  <div class="text-red-700">{{ error }}</div>
			</div>
		  </div>
  
		  <!-- Contenu du dashboard -->
		  <div v-else class="space-y-6">
			<!-- Informations de la base de données avec possibilité d'édition -->
			<div class="bg-white overflow-hidden shadow-sm rounded-lg">
			  <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
				<div class="flex justify-between items-center">
				  <h3 class="text-lg font-medium text-gray-900">Database Information</h3>
				  <!-- Bouton d'édition seulement si permissions d'écriture -->
				  <button 
					v-if="canWrite"
					@click="toggleEditDescription"
					class="text-sm text-blue-600 hover:text-blue-800 flex items-center"
				  >
					<svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
					</svg>
					{{ editingDescription ? 'Cancel' : 'Edit' }}
				  </button>
				</div>
			  </div>
			  <div class="px-6 py-4">
				<div class="flex justify-between items-start">
				  <div class="flex-1">
					<p class="text-lg font-semibold text-gray-800">{{ dashboardData.database_name }}</p>
					<p v-if="dashboardData.project_name" class="text-sm text-gray-500 mt-1">
					  Project: {{ dashboardData.project_name }}
					</p>
				  </div>
				</div>
				
				<!-- Description éditable ou en lecture seule -->
				<div class="mt-4">
				  <div v-if="!editingDescription">
					<p v-if="dashboardData.database_description" class="text-gray-600">
					  {{ dashboardData.database_description }}
					</p>
					<p v-else class="text-gray-400 italic">
					  No description available
					  <span v-if="canWrite"> - Click "Edit" to add one</span>
					</p>
				  </div>
				  
				  <!-- Mode édition -->
				  <div v-else class="space-y-3">
					<textarea
					  v-model="editedDescription"
					  rows="3"
					  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
					  placeholder="Enter database description..."
					></textarea>
					<div class="flex space-x-2">
					  <button
						@click="saveDescription"
						:disabled="savingDescription"
						class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
					  >
						{{ savingDescription ? 'Saving...' : 'Save' }}
					  </button>
					  <button
						@click="cancelEditDescription"
						class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
					  >
						Cancel
					  </button>
					</div>
				  </div>
				</div>
			  </div>
			</div>
  
			<!-- Bannière d'information sur les permissions pour les utilisateurs en lecture seule -->
			<div v-if="isReadOnly" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
			  <div class="flex items-center">
				<svg class="h-5 w-5 text-yellow-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
				  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
				</svg>
				<div class="text-yellow-700">
				  <p class="font-medium">Read-only access</p>
				  <p class="text-sm">You can view all information but cannot make modifications. Contact the project owner for write permissions.</p>
				</div>
			  </div>
			</div>
  
			<!-- Statistiques globales -->
			<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
			  <StatCard title="Tables" :count="dashboardData.tables_count" color="blue" icon="table" />
			  <StatCard title="Views" :count="dashboardData.views_count" color="green" icon="view" />
			  <StatCard title="Stored Procedures" :count="dashboardData.procedures_count" color="purple" icon="procedure" />
			  <StatCard title="Functions" :count="dashboardData.functions_count" color="indigo" icon="function" />
			  <StatCard title="Triggers" :count="dashboardData.triggers_count" color="red" icon="trigger" />
			  <StatCard title="Total Columns" :count="dashboardData.columns_count" color="yellow" icon="column" />
			  <StatCard title="Primary Keys" :count="dashboardData.primary_keys_count" color="amber" icon="key" />
			  <StatCard title="Foreign Keys" :count="dashboardData.foreign_keys_count" color="orange" icon="link" />
			</div>
  
			<!-- Taux de documentation -->
			<div class="bg-white overflow-hidden shadow-sm rounded-lg">
			  <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
				<h3 class="text-lg font-medium text-gray-900">Documentation Status</h3>
			  </div>
			  <div class="px-6 py-4">
				<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
				  <!-- Documentation des tables -->
				  <div>
					<h4 class="text-sm font-medium text-gray-600 mb-2">Documented Tables</h4>
					<div class="flex items-center">
					  <div class="flex-1 mr-4">
						<div class="h-2 bg-gray-200 rounded-full">
						  <div 
							class="h-2 bg-blue-500 rounded-full transition-all duration-300" 
							:style="`width: ${getPercentage(dashboardData.documented_tables_count, dashboardData.tables_count)}%`"
						  ></div>
						</div>
					  </div>
					  <div class="text-sm text-gray-600 whitespace-nowrap">
						{{ dashboardData.documented_tables_count }} / {{ dashboardData.tables_count }}
						({{ getPercentage(dashboardData.documented_tables_count, dashboardData.tables_count) }}%)
					  </div>
					</div>
				  </div>
  
				  <!-- Documentation des colonnes -->
				  <div>
					<h4 class="text-sm font-medium text-gray-600 mb-2">Documented Columns</h4>
					<div class="flex items-center">
					  <div class="flex-1 mr-4">
						<div class="h-2 bg-gray-200 rounded-full">
						  <div 
							class="h-2 bg-yellow-500 rounded-full transition-all duration-300" 
							:style="`width: ${getPercentage(dashboardData.documented_columns_count, dashboardData.columns_count)}%`"
						  ></div>
						</div>
					  </div>
					  <div class="text-sm text-gray-600 whitespace-nowrap">
						{{ dashboardData.documented_columns_count }} / {{ dashboardData.columns_count }}
						({{ getPercentage(dashboardData.documented_columns_count, dashboardData.columns_count) }}%)
					  </div>
					</div>
				  </div>
  
				  <!-- Documentation des vues -->
				  <div>
					<h4 class="text-sm font-medium text-gray-600 mb-2">Documented Views</h4>
					<div class="flex items-center">
					  <div class="flex-1 mr-4">
						<div class="h-2 bg-gray-200 rounded-full">
						  <div 
							class="h-2 bg-green-500 rounded-full transition-all duration-300" 
							:style="`width: ${getPercentage(dashboardData.documented_views_count, dashboardData.views_count)}%`"
						  ></div>
						</div>
					  </div>
					  <div class="text-sm text-gray-600 whitespace-nowrap">
						{{ dashboardData.documented_views_count }} / {{ dashboardData.views_count }}
						({{ getPercentage(dashboardData.documented_views_count, dashboardData.views_count) }}%)
					  </div>
					</div>
				  </div>
  
				  <!-- Documentation des procédures stockées -->
				  <div>
					<h4 class="text-sm font-medium text-gray-600 mb-2">Documented Stored Procedures</h4>
					<div class="flex items-center">
					  <div class="flex-1 mr-4">
						<div class="h-2 bg-gray-200 rounded-full">
						  <div 
							class="h-2 bg-purple-500 rounded-full transition-all duration-300" 
							:style="`width: ${getPercentage(dashboardData.documented_procedures_count, dashboardData.procedures_count)}%`"
						  ></div>
						</div>
					  </div>
					  <div class="text-sm text-gray-600 whitespace-nowrap">
						{{ dashboardData.documented_procedures_count }} / {{ dashboardData.procedures_count }}
						({{ getPercentage(dashboardData.documented_procedures_count, dashboardData.procedures_count) }}%)
					  </div>
					</div>
				  </div>
  
				  <!-- Documentation des fonctions -->
				  <div>
					<h4 class="text-sm font-medium text-gray-600 mb-2">Documented Functions</h4>
					<div class="flex items-center">
					  <div class="flex-1 mr-4">
						<div class="h-2 bg-gray-200 rounded-full">
						  <div 
							class="h-2 bg-indigo-500 rounded-full transition-all duration-300" 
							:style="`width: ${getPercentage(dashboardData.documented_functions_count, dashboardData.functions_count)}%`"
						  ></div>
						</div>
					  </div>
					  <div class="text-sm text-gray-600 whitespace-nowrap">
						{{ dashboardData.documented_functions_count }} / {{ dashboardData.functions_count }}
						({{ getPercentage(dashboardData.documented_functions_count, dashboardData.functions_count) }}%)
					  </div>
					</div>
				  </div>
  
				  <!-- Documentation des triggers -->
				  <div>
					<h4 class="text-sm font-medium text-gray-600 mb-2">Documented Triggers</h4>
					<div class="flex items-center">
					  <div class="flex-1 mr-4">
						<div class="h-2 bg-gray-200 rounded-full">
						  <div 
							class="h-2 bg-red-500 rounded-full transition-all duration-300" 
							:style="`width: ${getPercentage(dashboardData.documented_triggers_count, dashboardData.triggers_count)}%`"
						  ></div>
						</div>
					  </div>
					  <div class="text-sm text-gray-600 whitespace-nowrap">
						{{ dashboardData.documented_triggers_count }} / {{ dashboardData.triggers_count }}
						({{ getPercentage(dashboardData.documented_triggers_count, dashboardData.triggers_count) }}%)
					  </div>
					</div>
				  </div>
				</div>
			  </div>
			</div>
  
			<!-- Tables les plus référencées -->
			<div class="bg-white overflow-hidden shadow-sm rounded-lg">
			  <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
				<h3 class="text-lg font-medium text-gray-900">Most Referenced Tables</h3>
			  </div>
			  <div class="overflow-x-auto">
				<table class="min-w-full divide-y divide-gray-200">
				  <thead class="bg-gray-50">
					<tr>
					  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
						Table
					  </th>
					  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
						Number of References
					  </th>
					  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
						Documentation Status
					  </th>
					</tr>
				  </thead>
				  <tbody class="bg-white divide-y divide-gray-200">
					<tr v-for="table in dashboardData.most_referenced_tables.slice(0, 5)" :key="table.id" class="hover:bg-gray-50">
					  <td class="px-6 py-4 whitespace-nowrap">
						<Link :href="route('table.details', { tableName: table.name })" class="text-blue-600 hover:text-blue-900 font-medium">
						  {{ table.name }}
						</Link>
					  </td>
					  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
						<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
						  {{ table.references_count }}
						</span>
					  </td>
					  <td class="px-6 py-4 whitespace-nowrap">
						<span :class="[
						  'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
						  table.is_documented ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
						]">
						  {{ table.is_documented ? 'Documented' : 'Not documented' }}
						</span>
					  </td>
					</tr>
					<tr v-if="!dashboardData.most_referenced_tables.length">
					  <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
						No referenced tables found
					  </td>
					</tr>
				  </tbody>
				</table>
			  </div>
			</div>
		  </div>
		</div>
	  </div>
	</AuthenticatedLayout>
  </template>
  
  <script setup>
  import { ref, computed, onMounted } from 'vue';
  import { Link } from '@inertiajs/vue3'
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
  import StatCard from '@/Components/StatCard.vue';
  
  const loading = ref(true);
  const error = ref(null);
  const editingDescription = ref(false);
  const editedDescription = ref('');
  const savingDescription = ref(false);
  
  const dashboardData = ref({
	database_name: '',
	database_description: '',
	project_name: '',
	tables_count: 0,
	views_count: 0,
	procedures_count: 0,
	functions_count: 0,
	triggers_count: 0,
	columns_count: 0,
	primary_keys_count: 0,
	foreign_keys_count: 0,
	documented_tables_count: 0,
	documented_columns_count: 0,
	documented_views_count: 0,
	documented_procedures_count: 0,
	documented_functions_count: 0,
	documented_triggers_count: 0,
	most_referenced_tables: [],
	permissions: null,
	user_access_level: 'none'
  });
  
  // Computed properties pour les permissions
  const canRead = computed(() => dashboardData.value.permissions?.can_read || false);
  const canWrite = computed(() => dashboardData.value.permissions?.can_write || false);
  const canAdmin = computed(() => dashboardData.value.permissions?.can_admin || false);
  const isOwner = computed(() => dashboardData.value.user_access_level === 'owner');
  const isReadOnly = computed(() => canRead.value && !canWrite.value);
  
  // Fonctions pour l'affichage des permissions
  const getPermissionIcon = () => {
	switch (dashboardData.value.user_access_level) {
	  case 'owner':
		return '👑';
	  case 'Admin':
		return '🔧';
	  case 'write':
		return '✏️';
	  case 'read':
		return '👁️';
	  default:
		return '❓';
	}
  };
  
  const getPermissionText = () => {
	switch (dashboardData.value.user_access_level) {
	  case 'owner':
		return 'Owner';
	  case 'Admin':
		return 'Admin';
	  case 'write':
		return 'Read/Write';
	  case 'read':
		return 'Read Only';
	  default:
		return 'No Access';
	}
  };
  
  const getPermissionBadgeClass = () => {
	const baseClasses = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium';
	switch (dashboardData.value.user_access_level) {
	  case 'owner':
		return `${baseClasses} bg-yellow-100 text-yellow-800`;
	  case 'Admin':
		return `${baseClasses} bg-purple-100 text-purple-800`;
	  case 'write':
		return `${baseClasses} bg-green-100 text-green-800`;
	  case 'read':
		return `${baseClasses} bg-blue-100 text-blue-800`;
	  default:
		return `${baseClasses} bg-gray-100 text-gray-800`;
	}
  };
  
  // Calcul du pourcentage avec gestion des divisions par zéro
  const getPercentage = (numerator, denominator) => {
	if (!denominator) return 0;
	return Math.round((numerator / denominator) * 100);
  };
  
  // Fonctions pour l'édition de description
  const toggleEditDescription = () => {
	if (!canWrite.value) {
	  alert('You need write permissions to edit the description.');
	  return;
	}
	editingDescription.value = !editingDescription.value;
	if (editingDescription.value) {
	  editedDescription.value = dashboardData.value.database_description || '';
	}
  };
  
  const cancelEditDescription = () => {
	editingDescription.value = false;
	editedDescription.value = '';
  };
  
  const saveDescription = async () => {
	if (!canWrite.value) {
	  alert('You need write permissions to save changes.');
	  return;
	}
	
	try {
	  savingDescription.value = true;
	  
	  const response = await axios.post('/dashboard/update-description', {
		description: editedDescription.value
	  });
	  
	  if (response.data.success) {
		dashboardData.value.database_description = editedDescription.value;
		editingDescription.value = false;
		// Optionnel : afficher une notification de succès
	  }
	} catch (err) {
	  console.error('Error saving description:', err);
	  alert('Error saving description: ' + (err.response?.data?.error || err.message));
	} finally {
	  savingDescription.value = false;
	}
  };
  
  // Charger les données du dashboard
  onMounted(async () => {
	try {
	  const response = await axios.get('/dashboard-data');
	  dashboardData.value = response.data;
	  loading.value = false;
	} catch (err) {
	  console.error('Error loading dashboard data:', err);
	  error.value = err.response?.data?.error || 'Error loading data';
	  loading.value = false;
	}
  });
  </script>
  
