<template>
  <AuthenticatedLayout>
    <!-- Header -->
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800">
          <span class="text-gray-500 font-normal">Table :</span> 
          {{ tableName }}
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

          <!-- Description de la table -->
          <div id="table-description" class="bg-white rounded-lg shadow-sm overflow-hidden mb-6" :key="`description-${tableName}`">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                  </svg>
                  Table description
                </h3>
                <button 
                  id="save-table-button"
                  v-if="tableDetails.can_edit"
                  @click="saveTableStructure" 
                  class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :disabled="saving"
                >
                  <span v-if="!saving">Save modification</span>
                  <span v-else class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Recording...
                  </span>
                </button>
                <span v-else class="text-sm text-gray-500 italic">
                  Read only access
                </span>
              </div>
            </div>
            <div class="p-6">
              <textarea
                v-model="form.description"
                :key="`textarea-${tableName}`"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                :class="{ 'opacity-50 cursor-not-allowed bg-gray-100': !tableDetails.can_edit }"
                placeholder="Optionnal description (use, environnement, content...)"
                :disabled="!tableDetails.can_edit || saving"
                :readonly="!tableDetails.can_edit"
              ></textarea>
            </div>
          </div>
          
          <!-- Structure de la table -->
          <div id="table-structure" class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">
                  <svg class="h-5 w-5 text-gray-500 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z"/>
                  </svg>
                  Table structure
                <span v-if="searchQuery" class="text-sm font-normal text-gray-600 ml-2">
                    ({{ filteredColumns.length }}/{{ tableDetails.columns.length }})
                  </span>
                </h3>
                <PrimaryButton id="add-column-button" v-if="tableDetails.can_add_columns" @click="showAddColumnModal = true">
                  Add a column
                </PrimaryButton>
              </div>
            </div>

            <!-- Modal d'ajout de colonne -->
            <div v-if="showAddColumnModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
              <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div v-if="addingColumn" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 rounded-md">
                  <div class="flex flex-col items-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
                    <p class="text-gray-600 text-sm">Adding column...</p>
                  </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-medium text-gray-900">Add new column</h3>
                  <button @click="showAddColumnModal = false" class="text-gray-400 hover:text-gray-500" :disabled="addingColumn">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                
                <form @submit.prevent="addNewColumn" :class="{ 'opacity-50 pointer-events-none': addingColumn }">
                  <div class="space-y-4">
                    <div>
                      <label for="column_name" class="block text-sm font-medium text-gray-700">Column name</label>
                      <input 
                        id="column_name" 
                        v-model="newColumn.column_name" 
                        type="text" 
                        required
                        :disabled="addingColumn"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      >
                    </div>
                    
                    <div>
                      <label for="data_type" class="block text-sm font-medium text-gray-700">Data type</label>
                      <input 
                        id="data_type" 
                        v-model="newColumn.data_type" 
                        type="text" 
                        required
                        :disabled="addingColumn"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="ex: varchar(255), int, date..."
                      >
                    </div>
                    
                    <div class="flex items-center">
                      <input 
                        id="is_nullable" 
                        v-model="newColumn.is_nullable" 
                        type="checkbox" 
                        :disabled="addingColumn"
                        class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                      >
                      <label for="is_nullable" class="ml-2 block text-sm text-gray-700">Nullable</label>
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Key type</label>
                      <div class="mt-1 flex items-center space-x-4">
                        <div class="flex items-center">
                          <input 
                            id="no_key" 
                            v-model="newColumn.key_type" 
                            type="radio" 
                            value="none"
                            :disabled="addingColumn"
                            class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                          >
                          <label for="no_key" class="ml-2 block text-sm text-gray-700">None</label>
                        </div>
                        <div class="flex items-center">
                          <input 
                            id="primary_key" 
                            v-model="newColumn.key_type" 
                            type="radio" 
                            value="PK"
                            :disabled="addingColumn"
                            class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                          >
                          <label for="primary_key" class="ml-2 block text-sm text-gray-700">Primary key</label>
                        </div>
                        <div class="flex items-center">
                          <input 
                            id="foreign_key" 
                            v-model="newColumn.key_type" 
                            type="radio" 
                            value="FK"
                            :disabled="addingColumn"
                            class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                          >
                          <label for="foreign_key" class="ml-2 block text-sm text-gray-700">Foreign key</label>
                        </div>
                      </div>
                    </div>
                    
                    <div>
                      <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                      <textarea 
                        id="description" 
                        v-model="newColumn.description" 
                        rows="2"
                        :disabled="addingColumn"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      ></textarea>
                    </div>
                    
                    <div>
                      <label for="possible_values" class="block text-sm font-medium text-gray-700">Range value</label>
                      <textarea 
                        id="possible_values" 
                        v-model="newColumn.possible_values" 
                        rows="2"
                        :disabled="addingColumn"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      ></textarea>
                    </div>
                    
                    <div>
                      <label for="release" class="block text-sm font-medium text-gray-700">Release</label>
                      <select 
                        id="release" 
                        v-model="newColumn.release"
                        :disabled="addingColumn"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                      >
                        <option value="">None</option>
                        <option v-for="release in availableReleases" :key="release.id" :value="release.id">
                          {{ release.display_name }}
                        </option>
                      </select>
                    </div>
                  </div>
                  
                  <div class="mt-6 flex justify-end space-x-3">
                    <button 
                      type="button"
                      @click="showAddColumnModal = false"
                      :disabled="addingColumn"
                      class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                    >
                      Cancel
                    </button>
                    <button 
                      type="submit"
                      class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :disabled="addingColumn"
                    >
                      <span v-if="!addingColumn">Add</span>
                      <span v-else class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Adding...
                      </span>
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Table des colonnes -->
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead>
                  <tr class="bg-gray-50">
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nullable</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Key</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Range Value</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Release</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Historic</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(column, index) in tableDetails.columns" 
                      :key="column.column_name"
                      class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {{ column.column_name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                      <div class="flex items-center space-x-2">
                        <span v-if="!editingDataType[column.column_name]" class="font-mono">
                          {{ column.data_type }}
                        </span>
                        <input
                          v-else
                          v-model="editingDataTypeValue"
                          type="text"
                          class="flex-1 px-2 py-1 text-sm border rounded focus:ring-blue-500 focus:border-blue-500"
                          :disabled="!tableDetails.can_edit"
                          @keyup.enter="saveDataType(column.column_name)"
                          @keyup.esc="cancelEdit('dataType', column.column_name)"
                        >
                        <button
                          v-if="!editingDataType[column.column_name] && tableDetails.can_edit"
                          @click="startEdit('dataType', column.column_name, column.data_type)"
                          class="p-1 text-gray-400 hover:text-gray-600"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </button>
                        <div v-else class="flex space-x-1">
                          <button
                            @click="saveDataType(column.column_name)"
                            class="p-1 text-green-600 hover:text-green-700"
                            :disabled="savingDataType[column.column_name]"
                          >
                            <svg v-if="!savingDataType[column.column_name]" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="animate-spin h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                          </button>
                          <button
                            @click="cancelEdit('dataType', column.column_name)"
                            class="p-1 text-red-600 hover:text-red-700"
                            :disabled="savingDataType[column.column_name]"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                          </button>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <div class="flex items-center space-x-2 relative">
                        <select 
                          :value="column.is_nullable ? 'true' : 'false'"
                          @change="updateNullable(column, $event.target.value === 'true')"
                          :disabled="!tableDetails.can_edit || updatingNullable[column.column_name]"
                          :class="[
                            'block w-full pl-2 pr-7 py-1 text-xs border-gray-300 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 rounded-md',
                            column.is_nullable ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800',
                            updatingNullable[column.column_name] ? 'opacity-50' : ''
                          ]"
                        >
                          <option value="true">Yes</option>
                          <option value="false">No</option>
                        </select>
                        <div v-if="updatingNullable[column.column_name]" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                          <svg class="animate-spin h-3 w-3 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <div class="flex gap-1">
                        <span v-if="column.is_primary_key" 
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                          PK
                        </span>
                        <span v-if="column.is_foreign_key"
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                          FK
                        </span>
                      </div>
                    </td>

                    <!-- Description -->
                    <td :id="index === 0 ? 'column-description' : undefined" class="px-6 py-4 text-sm text-gray-500">
                      <div class="flex items-center space-x-2">
                        
                        <!-- Mode lecture -->
                        <template v-if="!editingDescription[column.column_name]">
                          <!-- Container relatif uniquement si description existe -->
                          <div v-if="column.description" class="relative w-[300px]">
                            <span
                              class="block w-full h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words pr-8"
                            >
                              {{ column.description }}
                            </span>

                            <!-- Bouton loupe en haut à droite du textarea -->
                            <button
                              v-if="column.description.length > 50"
                              @click="openDescriptionModal(column.column_name, column.description)"
                              class="absolute top-2 right-2 p-1 text-blue-500 hover:text-blue-700 bg-white rounded hover:bg-blue-50 transition-colors"
                              title="View full description"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                              </svg>
                            </button>
                          </div>
                          
                          <!-- Tiret si pas de description -->
                          <span v-else class="text-gray-400">-</span>

                          <button
                            v-if="tableDetails.can_edit"
                            @click="startEdit('description', column.column_name, column.description)"
                            class="p-1 text-gray-400 hover:text-gray-600"
                            title="Edit description"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                          </button>
                        </template>

                        <!-- Mode édition -->
                        <template v-else>
                          <textarea
                            v-model="editingDescriptionValue"
                            class="px-2 py-1 text-sm border rounded focus:ring-blue-500 focus:border-blue-500 w-[200px] h-[80px] resize-none overflow-y-auto"
                            :disabled="!tableDetails.can_edit"
                            @keydown.ctrl.enter="saveDescription(column.column_name)"
                            @keydown.esc="cancelEdit('description', column.column_name)"
                          ></textarea>

                          <!-- Actions -->
                          <div class="flex space-x-1">
                            <button
                              @click="saveDescription(column.column_name)"
                              class="p-1 text-green-600 hover:text-green-700"
                              :disabled="savingDescription[column.column_name]"
                              title="Save"
                            >
                              <svg v-if="!savingDescription[column.column_name]" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                              </svg>
                              <svg v-else class="animate-spin h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0..." />
                              </svg>
                            </button>
                            <button
                              @click="cancelEdit('description', column.column_name)"
                              class="p-1 text-red-600 hover:text-red-700"
                              :disabled="savingDescription[column.column_name]"
                              title="Cancel"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                          </div>
                        </template>
                      </div>
                    </td>

                    <!-- Modal pour afficher la description complète -->
                    <div v-if="showDescriptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="closeDescriptionModal">
                      <div class="relative top-20 mx-auto p-6 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                        <div class="flex items-center justify-between mb-4">
                          <h3 class="text-lg font-medium text-gray-900">
                            Description - {{ currentDescriptionColumn }}
                          </h3>
                          <button @click="closeDescriptionModal" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                          </button>
                        </div>
                        
                        <div class="mt-4 max-h-96 overflow-y-auto p-4 bg-gray-50 rounded border">
                          <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ currentDescriptionText }}</p>
                        </div>

                        <div class="mt-6 flex justify-end">
                          <button 
                            @click="closeDescriptionModal"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                          >
                            Close
                          </button>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Possible Values -->
                    <td :id="index === 0 ? 'column-range' : undefined" class="px-6 py-4 text-sm text-gray-500">
                      <div class="flex items-center space-x-2">

                        <!-- Mode lecture -->
                        <template v-if="!editingPossibleValues[column.column_name]">
                          <span
                            v-if="column.possible_values"
                            class="block w-[300px] h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words"
                          >
                            {{ column.possible_values }}
                          </span>
                          <span v-else class="text-gray-400">-</span>

                          <button
                            v-if="tableDetails.can_edit"
                            @click="startEdit('possibleValues', column.column_name, column.possible_values)"
                            class="p-1 text-gray-400 hover:text-gray-600"
                            title="Edit possible values"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                          </button>
                        </template>

                        <!-- Mode édition -->
                        <template v-else>
                          <textarea
                            v-model="editingPossibleValuesValue"
                            class="px-2 py-1 text-sm border rounded focus:ring-blue-500 focus:border-blue-500 w-[300px] h-[80px] resize-none overflow-y-auto"
                            :disabled="!tableDetails.can_edit"
                            @keydown.ctrl.enter="savePossibleValues(column.column_name)"
                            @keydown.esc="cancelEdit('possibleValues', column.column_name)"
                          ></textarea>

                          <div class="flex space-x-1">
                            <button
                              @click="savePossibleValues(column.column_name)"
                              class="p-1 text-green-600 hover:text-green-700"
                              :disabled="savingPossibleValues[column.column_name]"
                            >
                              <svg v-if="!savingPossibleValues[column.column_name]" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                              </svg>
                              <svg v-else class="animate-spin h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0..." />
                              </svg>
                            </button>
                            <button
                              @click="cancelEdit('possibleValues', column.column_name)"
                              class="p-1 text-red-600 hover:text-red-700"
                              :disabled="savingPossibleValues[column.column_name]"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                          </div>
                        </template>

                      </div>
                    </td>
                    <td :id="index === 0 ? 'column-release' : undefined" class="px-6 py-4 text-sm text-gray-500">
                      <div class="flex items-center space-x-2 relative">
                        <select 
                          :value="column.release_id || ''"
                          @change="updateColumnRelease(column, $event.target.value)"
                          :disabled="!tableDetails.can_edit || updatingRelease[column.column_name]"
                          :class="[
                            'block w-full pl-2 pr-7 py-1 text-xs border-gray-300 rounded-md',
                            column.release_id ? 'bg-blue-50 text-blue-800' : '',
                            !tableDetails.can_edit ? 'opacity-50 cursor-not-allowed' : '',
                            updatingRelease[column.column_name] ? 'opacity-50' : ''
                          ]"
                        >
                          <option value="">None</option>
                          <option v-for="release in availableReleases" :key="release.id" :value="release.id">
                            {{ release.display_name }}
                          </option>
                        </select>
                        <div v-if="updatingRelease[column.column_name]" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                          <svg class="animate-spin h-3 w-3 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                        </div>
                      </div>
                    </td>
                    <td :id="index === 0 ? 'column-history' : undefined" class="px-4 py-3 text-sm">
                      <SecondaryButton @click="showAuditLogs(column.column_name)" :disabled="loadingAuditLogs && currentColumn === column.column_name">
                        <span v-if="!(loadingAuditLogs && currentColumn === column.column_name)">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          </svg>
                        </span>
                        <span v-else>
                          <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                        </span>
                      </SecondaryButton>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Index -->
          <div id="table-indexes" class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
              <h3 class="text-lg font-medium text-gray-900">
                <svg class="h-5 w-5 text-gray-500 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                </svg>
                Index
              </h3>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead>
                  <tr class="bg-gray-50">
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="index in tableDetails.indexes" 
                      :key="index.index_name"
                      class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {{ index.index_name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                      {{ index.index_type }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 align-top">
                      <div class="font-mono max-w-xs max-h-24 overflow-y-auto whitespace-pre-wrap break-words"> 
                        {{ index.columns }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <div class="flex gap-2">
                        <span v-if="index.is_primary_key" 
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                          Primary Key
                        </span>
                        <span v-if="index.is_unique" 
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                          Unique
                        </span>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!tableDetails.indexes?.length">
                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                      No index found
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Relations -->
          <div id="table-relations" class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">
                  <svg class="h-5 w-5 text-gray-500 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                  </svg>
                  Relations
                <span v-if="searchQuery" class="text-sm font-normal text-gray-600 ml-2">
                    ({{ filteredColumns.length }}/{{ tableDetails.columns.length }})
                  </span>
                </h3>
                <PrimaryButton v-if="tableDetails.can_add_columns" @click="showAddColumnModal = true">
                  Add a column
                </PrimaryButton>
              </div>
            </div>

            <!-- Modal d'ajout de relation -->
            <div v-if="showAddRelationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
              <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div v-if="addingRelation" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 rounded-md">
                  <div class="flex flex-col items-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
                    <p class="text-gray-600 text-sm">Add relation...</p>
                  </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-medium text-gray-900">Add a relation</h3>
                  <button @click="showAddRelationModal = false" class="text-gray-400 hover:text-gray-500" :disabled="addingRelation">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
                
                <form @submit.prevent="addNewRelation" :class="{ 'opacity-50 pointer-events-none': addingRelation }">
                  <div class="space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Constraint name</label>
                      <input 
                        v-model="newRelation.constraint_name" 
                        type="text" 
                        required
                        :disabled="addingRelation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="ex: FK_TableA_TableB"
                      >
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Column origin</label>
                      <select 
                        v-model="newRelation.column_name"
                        required
                        :disabled="addingRelation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      >
                        <option value="">Select a column</option>
                        <option v-for="column in tableDetails.columns" :key="column.column_name" :value="column.column_name">
                          {{ column.column_name }}
                        </option>
                      </select>
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700">referenced table</label>
                      <input 
                        v-model="newRelation.referenced_table" 
                        type="text" 
                        required
                        :disabled="addingRelation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      >
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Referenced Column</label>
                      <input 
                        v-model="newRelation.referenced_column" 
                        type="text" 
                        required
                        :disabled="addingRelation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      >
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Action ON DELETE</label>
                      <select 
                        v-model="newRelation.delete_rule"
                        :disabled="addingRelation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      >
                        <option value="NO ACTION">NO ACTION</option>
                        <option value="CASCADE">CASCADE</option>
                        <option value="SET NULL">SET NULL</option>
                        <option value="SET DEFAULT">SET DEFAULT</option>
                        <option value="RESTRICT">RESTRICT</option>
                      </select>
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Action ON UPDATE</label>
                      <select 
                        v-model="newRelation.update_rule"
                        :disabled="addingRelation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      >
                        <option value="NO ACTION">NO ACTION</option>
                        <option value="CASCADE">CASCADE</option>
                        <option value="SET NULL">SET NULL</option>
                        <option value="SET DEFAULT">SET DEFAULT</option>
                        <option value="RESTRICT">RESTRICT</option>
                      </select>
                    </div>
                  </div>
                  
                  <div class="mt-6 flex justify-end space-x-3">
                    <button 
                      type="button"
                      @click="showAddRelationModal = false"
                      :disabled="addingRelation"
                      class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
                    >
                      Cancel
                    </button>
                    <button 
                      type="submit"
                      :disabled="addingRelation"
                      class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    >
                      <span v-if="!addingRelation">Add</span>
                      <span v-else class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Adding...
                      </span>
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead>
                  <tr class="bg-gray-50">
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Constraint</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Refrenced table</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referenced column</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="relation in tableDetails.relations" 
                      :key="relation.constraint_name"
                      class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {{ relation.constraint_name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                      {{ relation.column_name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <Link 
                        :href="route('table.details', { tableName: relation.referenced_table })"
                        class="text-blue-600 hover:text-blue-900 font-medium hover:underline"
                      >
                        {{ relation.referenced_table }}
                      </Link>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                      {{ relation.referenced_column }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div class="space-y-1 text-xs">
                        <div class="px-2 py-1 bg-gray-100 rounded">
                          ON DELETE {{ relation.delete_rule }}
                        </div>
                        <div class="px-2 py-1 bg-gray-100 rounded">
                          ON UPDATE {{ relation.update_rule }}
                        </div>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!tableDetails.relations?.length">
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                      No relation found
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal pour afficher les audit logs -->
    <div v-if="showAuditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 shadow-lg rounded-md bg-white">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            Modification historic - {{ currentColumn }}
          </h3>
          <button @click="closeAuditModal" class="text-gray-400 hover:text-gray-500">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <div v-if="loadingAuditLogs" class="text-center py-8">
          <div class="flex flex-col items-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-3"></div>
            <p class="text-gray-600">Historic loading...</p>
          </div>
        </div>
        
        <div v-else-if="auditLogs.length === 0" class="text-center py-4 text-gray-500">
          No modification found for this column
        </div>
        
        <div v-else class="overflow-y-auto max-h-96">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old value</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New value</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(log.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ log.user?.name || 'N/A' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span :class="[
                    'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                    log.change_type === 'update' ? 'bg-yellow-100 text-yellow-800' :
                    log.change_type === 'add' ? 'bg-green-100 text-green-800' :
                    'bg-gray-100 text-gray-800'
                  ]">
                    {{ log.change_type }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs overflow-y-auto whitespace-pre-wrap break-words"> 
                  {{ log.old_data || '-' }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs overflow-y-auto whitespace-pre-wrap break-words">
                  {{ log.new_data || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { useToast } from '@/Composables/useToast'
import { useDriver } from '@/Composables/useDriver.js'
import axios from 'axios'

//  Utilisation du toast - renommage pour éviter les conflits
const { success, error: showError, warning, info } = useToast()
const { showTableDetailsGuide } = useDriver()

// Fonction pour relancer le tutoriel
const restartTutorial = () => {
  localStorage.removeItem('table_details_tutorial_shown')
  showTableDetailsGuide()
}

//  Props optimisés avec valeurs par défaut
const props = defineProps({
  tableName: {
    type: String,
    required: true
  },
  tableDetails: {
    type: Object,
    default: () => ({
      description: '',
      columns: [],
      indexes: [],
      relations: [],
      can_edit: false,
      can_add_columns: false,
      can_add_relations: false,
      is_owner: false
    })
  },
  availableReleases: {
    type: Array,
    default: () => []
  },
  permissions: {
    type: Object,
    default: () => ({})
  },
  error: {
    type: String,
    default: null
  }
})

//  Variables réactives simplifiées
const saving = ref(false)
const form = ref({
  description: props.tableDetails.description || ''
})

// Variables pour l'édition inline
const editingDescription = ref({})
const editingDescriptionValue = ref('')
const editingPossibleValues = ref({})
const editingPossibleValuesValue = ref('')
const editingDataType = ref({})
const editingDataTypeValue = ref('')

// Variables pour les spinners
const savingDataType = ref({})
const savingDescription = ref({})
const savingPossibleValues = ref({})
const updatingNullable = ref({})
const updatingRelease = ref({})

// Variables pour les modaux
const showAddColumnModal = ref(false)
const addingColumn = ref(false)
const newColumn = ref({
  column_name: '',
  data_type: '',
  is_nullable: false,
  key_type: 'none',
  description: '',
  possible_values: '',
  release: ''
})

const showAddRelationModal = ref(false)
const addingRelation = ref(false)
const newRelation = ref({
  constraint_name: '',
  column_name: '',
  referenced_table: '',
  referenced_column: '',
  delete_rule: 'NO ACTION',
  update_rule: 'NO ACTION'
})

// Variables pour l'audit
const showAuditModal = ref(false)
const loadingAuditLogs = ref(false)
const auditLogs = ref([])
const currentColumn = ref('')

const showDescriptionModal = ref(false)
const currentDescriptionColumn = ref('')
const currentDescriptionText = ref('')

const openDescriptionModal = (columnName, description) => {
  currentDescriptionColumn.value = columnName
  currentDescriptionText.value = description
  showDescriptionModal.value = true
}

const closeDescriptionModal = () => {
  showDescriptionModal.value = false
  currentDescriptionColumn.value = ''
  currentDescriptionText.value = ''
}

//  Fonction de réinitialisation complète
const resetComponent = () => {
  console.log('🔄 Réinitialisation complète du composant pour table:', props.tableName)
  
  // Réinitialiser le formulaire
  form.value = {
    description: props.tableDetails.description || ''
  }
  
  // Réinitialiser tous les états d'édition
  editingDescription.value = {}
  editingPossibleValues.value = {}
  editingDataType.value = {}
  editingDescriptionValue.value = ''
  editingPossibleValuesValue.value = ''
  editingDataTypeValue.value = ''
  
  // Réinitialiser les états de sauvegarde
  savingDataType.value = {}
  savingDescription.value = {}
  savingPossibleValues.value = {}
  updatingNullable.value = {}
  updatingRelease.value = {}
  
  // Fermer tous les modaux
  showAddColumnModal.value = false
  showAddRelationModal.value = false
  showAuditModal.value = false
  
  // Réinitialiser les nouveaux objets
  newColumn.value = {
    column_name: '',
    data_type: '',
    is_nullable: false,
    key_type: 'none',
    description: '',
    possible_values: '',
    release: ''
  }
  
  newRelation.value = {
    constraint_name: '',
    column_name: '',
    referenced_table: '',
    referenced_column: '',
    delete_rule: 'NO ACTION',
    update_rule: 'NO ACTION'
  }
  
  // Réinitialiser l'audit
  auditLogs.value = []
  currentColumn.value = ''
  
  console.log('✅ Composant réinitialisé avec description:', form.value.description)
}

//  Initialisation avec Inertia
onMounted(() => {
  form.value.description = props.tableDetails.description || ''
  console.log(' [TABLE] Composant monté avec les données:', props.tableDetails)
  console.log(' [TABLE] Table name:', props.tableName)
  
  // Lancer le tutoriel au premier chargement
  const tutorialShown = localStorage.getItem('table_details_tutorial_shown')
  if (!tutorialShown && props.tableDetails && !props.error && props.tableDetails.columns?.length > 0) {
    setTimeout(() => {
      showTableDetailsGuide()
      localStorage.setItem('table_details_tutorial_shown', 'true')
    }, 1000)
  }
})

//  IMPORTANT: Surveiller les changements de table pour Inertia
watch(() => props.tableName, (newTableName, oldTableName) => {
  console.log('🔄 Changement de table Inertia:', { ancien: oldTableName, nouveau: newTableName })
  
  if (newTableName !== oldTableName && oldTableName !== undefined) {
    resetComponent()
  }
}, { immediate: false })

//  Surveiller les changements des détails de la table
watch(() => props.tableDetails, (newTableDetails) => {
  console.log('🔄 Détails de table mis à jour via Inertia:', newTableDetails)
  form.value.description = newTableDetails.description || ''
}, { deep: true, immediate: true })

//  Fonction de sauvegarde avec Inertia et Toast
const saveTableStructure = async () => {
  try {
    saving.value = true
    
    const tableData = {
      description: form.value.description || null,
    }
    
    const response = await axios.post(`/table/${props.tableName}/save-description`, tableData, {
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (response.data.success) {
      //  Recharger les données avec Inertia
      router.reload({
        only: ['tableDetails'],
        preserveScroll: true,
        onSuccess: () => {
          console.log('✅ Données rechargées avec succès')
          //  Toast de succès avec emoji
          success(`📝 Description de la table ${props.tableName} enregistrée avec succès!`)
        },
        onError: (errors) => {
          console.error('❌ Erreur lors du rechargement:', errors)
          showError('❌ Erreur lors du rechargement des données')
        }
      })
    } else {
      throw new Error(response.data.error || 'Erreur lors de la sauvegarde')
    }
  } catch (error) {
    console.error('❌ Erreur lors de la sauvegarde:', error)
    //  Toast d'erreur au lieu d'alert
    showError(`❌ Erreur lors de la sauvegarde: ${error.response?.data?.error || error.message}`)
  } finally {
    saving.value = false
  }
}

//  Fonctions d'édition avec Toast
const startEdit = (type, columnName, currentValue) => {
  if (!props.tableDetails.can_edit) {
    //  Toast de warning au lieu d'alert
    warning('🚫 Vous n\'avez pas les permissions pour modifier cette table')
    return
  }
  
  if (type === 'description') {
    editingDescription.value = { [columnName]: true }
    editingDescriptionValue.value = currentValue || ''
    info(`✏️ Édition de la description de la colonne ${columnName}`)
  } else if (type === 'possibleValues') {
    editingPossibleValues.value = { [columnName]: true }
    editingPossibleValuesValue.value = currentValue || ''
    info(`📋 Édition des valeurs possibles de la colonne ${columnName}`)
  } else if (type === 'dataType') {
    editingDataType.value = { [columnName]: true }
    editingDataTypeValue.value = currentValue || ''
    info(`🔧 Édition du type de données de la colonne ${columnName}`)
  }
}

const cancelEdit = (type, columnName) => {
  if (type === 'description') {
    editingDescription.value = { [columnName]: false }
    editingDescriptionValue.value = ''
  } else if (type === 'possibleValues') {
    editingPossibleValues.value = { [columnName]: false }
    editingPossibleValuesValue.value = ''
  } else if (type === 'dataType') {
    editingDataType.value = { [columnName]: false }
    editingDataTypeValue.value = ''
  }
  // info('↩️ Édition annulée')
}

const saveDescription = async (columnName) => {
  try {
    savingDescription.value[columnName] = true
    
    const response = await axios.post(`/table/${props.tableName}/column/${columnName}/description`, {
      description: editingDescriptionValue.value
    })
    
    if (response.data.success) {
      const column = props.tableDetails.columns.find(c => c.column_name === columnName)
      if (column) {
        column.description = editingDescriptionValue.value
      }
      cancelEdit('description', columnName)
      //  Toast de succès
      success(`✅ Description de la colonne ${columnName} mise à jour!`)
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    //  Toast d'erreur
    showError(`❌ Erreur lors de la sauvegarde: ${error.response?.data?.error || error.message}`)
  } finally {
    savingDescription.value[columnName] = false
  }
}

const savePossibleValues = async (columnName) => {
  try {
    savingPossibleValues.value[columnName] = true
    
    const response = await axios.post(`/table/${props.tableName}/column/${columnName}/possible-values`, {
      possible_values: editingPossibleValuesValue.value
    })
    
    if (response.data.success) {
      const column = props.tableDetails.columns.find(c => c.column_name === columnName)
      if (column) {
        column.possible_values = editingPossibleValuesValue.value
      }
      cancelEdit('possibleValues', columnName)
      //  Toast de succès
      success(` Valeurs possibles de la colonne ${columnName} mises à jour!`)
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    //  Toast d'erreur
    showError(`❌ Erreur lors de la sauvegarde: ${error.response?.data?.error || error.message}`)
  } finally {
    savingPossibleValues.value[columnName] = false
  }
}

const saveDataType = async (columnName) => {
  try {
    savingDataType.value[columnName] = true
    
    const column = props.tableDetails.columns.find(c => c.column_name === columnName)
    const response = await axios.post(`/table/${props.tableName}/column/${columnName}/properties`, {
      column_name: columnName,
      data_type: editingDataTypeValue.value,
      is_nullable: column.is_nullable,
      is_primary_key: column.is_primary_key,
      is_foreign_key: column.is_foreign_key
    })
    
    if (response.data.success) {
      if (column) {
        column.data_type = editingDataTypeValue.value
      }
      cancelEdit('dataType', columnName)
      //  Toast de succès
      success(`🔧 Type de données de la colonne ${columnName} mis à jour!`)
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    //  Toast d'erreur
    showError(`❌ Erreur lors de la sauvegarde: ${error.response?.data?.error || error.message}`)
  } finally {
    savingDataType.value[columnName] = false
  }
}

const updateNullable = async (column, isNullable) => {
  try {
    updatingNullable.value[column.column_name] = true
    
    if (typeof isNullable === 'string') {
      isNullable = isNullable === 'true'
    }
    
    if (column.is_nullable === isNullable) {
      return
    }
    
    const response = await axios.post(`/table/${props.tableName}/column/${column.column_name}/properties`, {
      column_name: column.column_name,
      data_type: column.data_type,
      is_nullable: isNullable,
      is_primary_key: column.is_primary_key,
      is_foreign_key: column.is_foreign_key
    })
    
    if (response.data.success) {
      column.is_nullable = isNullable
      //  Toast de succès discret
      success(`✅ Propriété nullable de ${column.column_name} mise à jour`)
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    //  Toast d'erreur
    showError(`❌ Erreur lors de la mise à jour: ${error.response?.data?.error || error.message}`)
  } finally {
    updatingNullable.value[column.column_name] = false
  }
}

const updateColumnRelease = async (column, releaseId) => {
  try {
    updatingRelease.value[column.column_name] = true
    
    const finalReleaseId = releaseId === '' ? null : parseInt(releaseId)
    
    const response = await axios.post(`/table/${props.tableName}/column/${column.column_name}/release`, {
      release_id: finalReleaseId
    })
    
    if (response.data.success) {
      column.release_id = finalReleaseId
      const selectedRelease = props.availableReleases.find(r => r.id === finalReleaseId)
      column.release_version = selectedRelease ? selectedRelease.version_number : ''
      
      //  Toast de succès avec info de la release
      const releaseInfo = selectedRelease ? selectedRelease.version_number : 'aucune'
      success(`🚀 Release de ${column.column_name} mise à jour: ${releaseInfo}`)
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    //  Toast d'erreur
    showError(`❌ Erreur lors de la mise à jour: ${error.response?.data?.error || error.message}`)
  } finally {
    updatingRelease.value[column.column_name] = false
  }
}

const addNewColumn = async () => {
  try {
    if (!props.tableDetails.can_add_columns) {
      //  Toast de warning
      warning('🚫 Permissions insuffisantes pour ajouter une colonne')
      return
    }
    
    //  Validation avec toast
    if (!newColumn.value.column_name?.trim()) {
      warning('📝 Le nom de la colonne est requis')
      return
    }
    
    if (!newColumn.value.data_type?.trim()) {
      warning('🔧 Le type de données est requis')
      return
    }
    
    addingColumn.value = true
    
    const columnData = {
      column_name: newColumn.value.column_name,
      data_type: newColumn.value.data_type,
      is_nullable: newColumn.value.is_nullable,
      is_primary_key: newColumn.value.key_type === 'PK',
      is_foreign_key: newColumn.value.key_type === 'FK',
      description: newColumn.value.description,
      possible_values: newColumn.value.possible_values,
      release: newColumn.value.release
    }
    
    const response = await axios.post(`/table/${props.tableName}/column/add`, columnData)
    
    if (response.data.success) {
      showAddColumnModal.value = false
      const columnName = newColumn.value.column_name
      newColumn.value = {
        column_name: '',
        data_type: '',
        is_nullable: false,
        key_type: 'none',
        description: '',
        possible_values: '',
        release: ''
      }
      
      //  Toast de succès avant le rechargement
      success(`✨ Colonne ${columnName} ajoutée avec succès!`)
      
      //  Utiliser Inertia au lieu de window.location.reload()
      router.reload({
        only: ['tableDetails'],
        preserveScroll: true
      })
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    //  Toast d'erreur
    showError(`❌ Erreur lors de l'ajout: ${error.response?.data?.error || error.message}`)
  } finally {
    addingColumn.value = false
  }
}

const addNewRelation = async () => {
  try {
    if (!props.tableDetails.can_add_relations) {
      //  Toast de warning
      warning('🚫 Permissions insuffisantes pour ajouter une relation')
      return
    }
    
    //  Validation avec toast
    if (!newRelation.value.constraint_name?.trim()) {
      warning('📝 Le nom de la contrainte est requis')
      return
    }
    
    if (!newRelation.value.column_name?.trim()) {
      warning('📝 La colonne source est requise')
      return
    }
    
    if (!newRelation.value.referenced_table?.trim()) {
      warning('📝 La table référencée est requise')
      return
    }
    
    if (!newRelation.value.referenced_column?.trim()) {
      warning('📝 La colonne référencée est requise')
      return
    }
    
    addingRelation.value = true
    
    const response = await axios.post(`/table/${props.tableName}/relation/add`, newRelation.value)
    
    if (response.data.success) {
      showAddRelationModal.value = false
      const constraintName = newRelation.value.constraint_name
      newRelation.value = {
        constraint_name: '',
        column_name: '',
        referenced_table: '',
        referenced_column: '',
        delete_rule: 'NO ACTION',
        update_rule: 'NO ACTION'
      }
      
      //  Toast de succès avant le rechargement
      success(`🔗 Relation ${constraintName} ajoutée avec succès!`)
      
      //  Utiliser Inertia au lieu de window.location.reload()
      router.reload({
        only: ['tableDetails'],
        preserveScroll: true
      })
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    //  Toast d'erreur
    showError(`❌ Erreur lors de l'ajout: ${error.response?.data?.error || error.message}`)
  } finally {
    addingRelation.value = false
  }
}

const showAuditLogs = async (columnName) => {
  showAuditModal.value = true
  loadingAuditLogs.value = true
  currentColumn.value = columnName
  
  //  Toast d'info pour le chargement
  info(`📋 Chargement de l'historique de ${columnName}...`)
  
  try {
    const response = await axios.get(`/table/${props.tableName}/column/${columnName}/audit-logs`)
    auditLogs.value = response.data
    
    //  Toast de succès discret
    if (response.data.length > 0) {
      success(`📊 ${response.data.length} modification(s) trouvée(s) pour ${columnName}`)
    } else {
      info(`📋 Aucune modification trouvée pour ${columnName}`)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    // Toast d'erreur
    showError('❌ Erreur lors du chargement de l\'historique')
  } finally {
    loadingAuditLogs.value = false
  }
}

const closeAuditModal = () => {
  showAuditModal.value = false
  auditLogs.value = []
  currentColumn.value = ''
}

const formatDate = (date) => {
  return new Date(date).toLocaleString('fr-FR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>