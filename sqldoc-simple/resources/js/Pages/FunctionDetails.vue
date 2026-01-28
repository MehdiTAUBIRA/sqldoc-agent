<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 id="function-header" class="text-xl font-semibold text-gray-800">
          <span class="text-gray-500 font-normal">Function :</span>
          {{ functionName }}
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
        <div v-if="loading" class="bg-white rounded-lg shadow-sm p-6">
          <div class="animate-pulse space-y-4">
            <div class="h-4 bg-gray-200 rounded w-1/4"></div>
            <div class="space-y-3">
              <div class="h-4 bg-gray-200 rounded"></div>
              <div class="h-4 bg-gray-200 rounded"></div>
              <div class="h-4 bg-gray-200 rounded"></div>
            </div>
          </div>
        </div>

        <div v-else-if="currentError"
             class="bg-red-50 border-l-4 border-red-400 p-6 rounded-lg shadow-sm">
          <div class="flex items-center">
            <svg class="h-5 w-5 text-red-400 mr-3" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <div class="text-red-700">{{ currentError }}</div>
          </div>
        </div>

        <div v-else>
          <!-- ✅ ID ajouté - Description -->
          <div id="function-description" class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                  </svg>
                  Description
                </h3>
                <!-- ✅ ID ajouté -->
                <button 
                  id="save-description-button"
                  v-if="canEdit"
                  @click="saveDescription" 
                  class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :disabled="saving"
                >
                  <span v-if="!saving">Save description</span>
                  <span v-else class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                  </span>
                </button>
                <span v-else class="text-sm text-gray-500 italic">
                  Read only access
                </span>
              </div>
            </div>
            <div class="p-6">
              <textarea
                v-model="procedureForm.description"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                :class="{ 'opacity-50 cursor-not-allowed bg-gray-100': !canEdit }"
                placeholder="Optional description (use, environment, content...)"
                :disabled="!canEdit || saving"
                :readonly="!canEdit"
              ></textarea>
            </div>
          </div>

          <!-- ✅ ID ajouté - Informations -->
          <div id="function-info" class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
              <div class="flex items-center">
                <svg class="h-5 w-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900">Informations</h3>
              </div>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <h4 class="text-sm font-semibold text-gray-500 mb-1">Name</h4>
                  <div class="bg-gray-50 p-3 rounded">
                    <p class="text-gray-800">{{ functionData.name || 'Not specified' }}</p>
                  </div>
                </div>
                <div>
                  <h4 class="text-sm font-semibold text-gray-500 mb-1">Type</h4>
                  <div class="bg-gray-50 p-3 rounded">
                    <p class="text-gray-800">{{ functionData.function_type || 'Not specified' }}</p>
                  </div>
                </div>
                <div>
                  <h4 class="text-sm font-semibold text-gray-500 mb-1">Return type</h4>
                  <div class="bg-gray-50 p-3 rounded">
                    <p class="text-gray-800">{{ functionData.return_type || 'Not specified' }}</p>
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                  <h4 class="text-sm font-semibold text-gray-500 mb-1">Creation Date</h4>
                  <div class="bg-gray-50 p-3 rounded">
                    <p class="text-gray-800">{{ formatDate(functionData.create_date) }}</p>
                  </div>
                </div>
                <div>
                  <h4 class="text-sm font-semibold text-gray-500 mb-1">Last Modification</h4>
                  <div class="bg-gray-50 p-3 rounded">
                    <p class="text-gray-800">{{ formatDate(functionData.modify_date) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ✅ ID ajouté - Tabs -->
          <div id="function-tabs" class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
              <a
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="[
                  activeTab === tab.id
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                  'whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm cursor-pointer'
                ]"
              >
                {{ tab.name }}
              </a>
            </nav>
          </div>

          <div v-show="activeTab === 'parameters'">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <div class="flex items-center">
                  <svg class="h-5 w-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                  </svg>
                  <h3 class="text-lg font-medium text-gray-900">Parameters</h3>
                </div>
              </div>
              <!--  ID ajouté -->
              <div id="parameters-table" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead>
                    <tr class="bg-gray-50">
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Name
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Type
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Output
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Description
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        RANGE VALUE
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        RELEASE
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        HISTORY
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(param, index) in functionData.parameters"
                        :key="param.parameter_id"
                        class="hover:bg-gray-50 transition-colors">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ param.parameter_name }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <span class="font-mono">{{ param.data_type }}</span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span :class="[
                          'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                          param.is_output
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-gray-100 text-gray-800'
                        ]">
                          {{ param.is_output ? 'Yes' : 'No' }}
                        </span>
                      </td>
                      <!-- ID ajouté à la première ligne de description -->
                      <td :id="index === 0 ? 'parameter-description' : undefined" class="px-6 py-4 text-sm text-gray-500">
                        <div class="flex items-center space-x-2">
                          
                          <template v-if="!editingParamId || editingParamId !== param.parameter_id">
                            <span
                              v-if="param.description"
                              class="block w-[300px] h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words"
                            >
                              {{ param.description }}
                            </span>
                            <span v-else class="text-gray-400">-</span>

                            <button
                              v-if="canEdit"
                              @click="startEdit(param)"
                              class="p-1 text-gray-400 hover:text-gray-600"
                              title="Edit description"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                              </svg>
                            </button>
                          </template>

                          <template v-else>
                            <textarea
                              v-model="editingValue"
                              class="px-2 py-1 text-sm border rounded focus:ring-blue-500 focus:border-blue-500 w-[300px] h-[80px] resize-none overflow-y-auto"
                              :disabled="!canEdit"
                              @keydown.ctrl.enter="saveParameterDescription(param)"
                              @keydown.esc="cancelEdit"
                            ></textarea>

                            <div class="flex space-x-1">
                              <button
                                @click="saveParameterDescription(param)"
                                class="p-1 text-green-600 hover:text-green-700"
                                :disabled="saving"
                              >
                                <svg v-if="!saving" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <svg v-else class="animate-spin h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                  <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                              </button>
                              <button
                                @click="cancelEdit"
                                class="p-1 text-red-600 hover:text-red-700"
                                :disabled="saving"
                              >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                              </button>
                            </div>
                          </template>

                        </div>
                      </td>

                      <!--  ID ajouté à la première ligne de range -->
                    <td :id="index === 0 ? 'parameter-range' : undefined" class="px-6 py-4 text-sm text-gray-500">
                      <div class="flex items-center space-x-2">

                        <template v-if="!editingRangeValues[param.parameter_id]">
                          <span
                            v-if="param.rangevalues"
                            class="block w-[300px] h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words"
                          >
                            {{ param.rangevalues }}
                          </span>
                          <span v-else class="text-gray-400">-</span>

                          <button
                            v-if="canEdit"
                            @click="startEditAdvanced('rangeValue', param.parameter_id, param.rangevalues)"
                            class="p-1 text-gray-400 hover:text-gray-600"
                            title="Edit range values"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                          </button>
                        </template>

                        <template v-else>
                          <textarea
                            v-model="editingRangeValuesValue"
                            class="px-2 py-1 text-sm border rounded focus:ring-blue-500 focus:border-blue-500 w-[300px] h-[80px] resize-none overflow-y-auto"
                            :disabled="!canEdit"
                            @keydown.ctrl.enter="saveRangeValues(param.parameter_name)"
                            @keydown.esc="cancelEditAdvanced('rangeValue', param.parameter_name)"
                          ></textarea>

                          <div v-if="canEdit" class="flex space-x-1">
                            <button
                              @click="saveRangeValues(param.parameter_name)"
                              class="p-1 text-green-600 hover:text-green-700"
                              :disabled="savingRangeValues[param.parameter_name]"
                            >
                              <svg v-if="!savingRangeValues[param.parameter_name]" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                              </svg>
                              <svg v-else class="animate-spin h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                              </svg>
                            </button>
                            <button
                              @click="cancelEditAdvanced('rangeValues', param.parameter_name)"
                              class="p-1 text-red-600 hover:text-red-700"
                              :disabled="savingRangeValues[param.parameter_name]"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                          </div>
                        </template>

                      </div>
                    </td>
                    
                    <!--  ID ajouté à la première ligne de release -->
                    <td :id="index === 0 ? 'parameter-release' : undefined" class="px-6 py-4 text-sm text-gray-500">
                      <div class="flex items-center space-x-2 relative">
                        <select 
                          :value="param.release_id || ''"
                          @change="updateColumnRelease(param, $event.target.value)"
                          :disabled="!canEdit || updatingRelease[param.parameter_name]"
                          :class="[
                            'block w-full pl-2 pr-7 py-1 text-xs border-gray-300 rounded-md',
                            param.release_id ? 'bg-blue-50 text-blue-800' : '',
                            !canEdit ? 'opacity-50 cursor-not-allowed' : '',
                            updatingRelease[param.parameter_name] ? 'opacity-50' : ''
                          ]"
                        >
                          <option value="">None</option>
                          <option v-for="release in availableReleases" :key="release.id" :value="release.id">
                            {{ release.display_name }}
                          </option>
                        </select>
                        <div v-if="updatingRelease[param.parameter_name]" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                          <svg class="animate-spin h-3 w-3 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                        </div>
                      </div>
                    </td>

                    <!--  ID ajouté à la première ligne d'historique -->
                    <td :id="index === 0 ? 'parameter-history' : undefined" class="px-4 py-3 text-sm">
                      <SecondaryButton @click="showAuditLogs(param.parameter_name)" :disabled="loadingAuditLogs && currentColumn === param.parameter_name">
                        <span v-if="!(loadingAuditLogs && currentColumn === param.parameter_name)">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                          </svg>
                        </span>
                        <span v-else>
                          <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                        </span>
                      </SecondaryButton>
                    </td>

                    </tr>
                    <tr v-if="!functionData.parameters || functionData.parameters.length === 0">
                      <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                        No parameters found
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-show="activeTab === 'definition'">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <div class="flex items-center">
                  <h3 class="text-lg font-medium text-gray-900 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                    Definition
                  </h3>
                </div>
              </div>
              <div class="p-6">
                <div class="bg-gray-900 p-4 rounded overflow-auto max-h-96">
                  <pre class="text-gray-200 text-sm whitespace-pre-wrap">{{ functionData.definition || 'Definition not available' }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal pour les audit logs -->
        <div v-if="showAuditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 shadow-lg rounded-md bg-white">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              Modification History - {{ currentColumn }}
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
              <p class="text-gray-600">Loading history...</p>
            </div>
          </div>
          
          <div v-else-if="auditLogs.length === 0" class="text-center py-4 text-gray-500">
            No modifications found for this parameter
          </div>
          
          <div v-else class="overflow-y-auto max-h-96">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old Value</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Value</th>
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
                  <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                    {{ log.old_data || '-' }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                    {{ log.new_data || '-' }}
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
import { ref, computed, onMounted, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { router } from '@inertiajs/vue3'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { Link } from '@inertiajs/vue3'
import { useToast } from '@/Composables/useToast'
import { useDriver } from '@/Composables/useDriver.js' 
import axios from 'axios'

const { success, error: showError, warning, info } = useToast()
const { showFunctionDetailsGuide } = useDriver() 

// Props
const props = defineProps({
  functionName: {
    type: String,
    required: true
  },
  functionDetails: {
    type: Object,
    required: true
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

// Fonction pour relancer le tutoriel
const restartTutorial = () => {
  localStorage.removeItem('function_details_tutorial_shown')
  showFunctionDetailsGuide()
}

// Computed pour les permissions
const canEdit = computed(() => {
  return props.functionDetails?.can_edit || props.permissions.can_edit || false
})

const isOwner = computed(() => {
  return props.functionDetails?.is_owner || props.permissions.is_owner || false
})

// États locaux
const procedureForm = ref({
  description: props.functionDetails.description || ''
})

const functionData = ref(props.functionDetails)
const loading = ref(false)
const saving = ref(false)
const editingParamId = ref(null)
const editingValue = ref('')

const editingDescription = ref({})
const editingDescriptionValue = ref('')
const editingRangeValues = ref({})
const editingRangeValuesValue = ref('')
const savingDescription = ref({})
const savingRangeValues = ref({})
const updatingRelease = ref({})

const showAuditModal = ref(false)
const loadingAuditLogs = ref(false)
const auditLogs = ref([])
const currentColumn = ref('')

const activeTab = ref('parameters')
const tabs = [
  { id: 'parameters', name: 'Parameters' },
  { id: 'definition', name: 'Definition' }
]

const currentError = computed(() => props.error)

// Watchers
watch(
  () => props.functionDetails,
  (newFunctionDetails) => {
    console.log('🔍 [FUNCTION] Props functionDetails ont changé:', newFunctionDetails)
    
    functionData.value = { ...newFunctionDetails }
    procedureForm.value.description = newFunctionDetails.description || ''
    
    editingParamId.value = null
    editingValue.value = ''
    editingDescription.value = {}
    editingRangeValues.value = {}
    editingDescriptionValue.value = ''
    editingRangeValuesValue.value = ''
  },
  { deep: true, immediate: true }
)

watch(
  () => props.functionName,
  (newFunctionName, oldFunctionName) => {
    if (newFunctionName !== oldFunctionName) {
      console.log(`🔍 [FUNCTION] Nom de fonction changé: ${oldFunctionName} → ${newFunctionName}`)
      
      router.reload({ preserveScroll: true, preserveState: true })
      
      editingParamId.value = null
      editingValue.value = ''
      editingDescription.value = {}
      editingRangeValues.value = {}
      editingDescriptionValue.value = ''
      editingRangeValuesValue.value = ''
    }
  }
)

// Fonctions d'édition
const startEdit = (param) => {
  if (!canEdit.value) {
    warning('You do not have permission to modify this function')
    return
  }
  editingParamId.value = param.parameter_id
  editingValue.value = param.description || ''
}

const startEditAdvanced = (type, parameterName, currentValue) => {
  if (!canEdit.value) {
    warning('You do not have permission to modify this function')
    return
  }
  
  if (type === 'description') {
    editingDescription.value = { [parameterName]: true }
    editingDescriptionValue.value = currentValue || ''
  } else if (type === 'rangeValue' || type === 'rangeValues') {
    editingRangeValues.value = { [parameterName]: true }
    editingRangeValuesValue.value = currentValue || ''
  }
}

const cancelEdit = () => {
  editingParamId.value = null
  editingValue.value = ''
}

const cancelEditAdvanced = (type, parameterName) => {
  if (type === 'description') {
    editingDescription.value = { [parameterName]: false }
    editingDescriptionValue.value = ''
  } else if (type === 'rangeValue' || type === 'rangeValues') {
    editingRangeValues.value = { [parameterName]: false }
    editingRangeValuesValue.value = ''
  }
}

const saveDescription = async () => {
  if (!canEdit.value) {
    warning('You do not have permission to modify this function')
    return
  }
  
  try {
    saving.value = true
    
    router.post(`/function/${props.functionName}/description`, {
      description: procedureForm.value.description
    }, {
      onSuccess: () => {
        success('Description of the function successfully registered!')
        functionData.value.description = procedureForm.value.description
      },
      onError: (errors) => {
        console.error('Erreur lors de la sauvegarde:', errors)
        showError('Error saving description')
      },
      onFinish: () => {
        saving.value = false
      }
    })
  } catch (error) {
    console.error('Erreur:', error)
    saving.value = false
  }
}

const saveParameterDescription = async (param) => {
  try {
    const parameterIdentifier = param.parameter_id
    if (!parameterIdentifier) {
      warning("Unable to save: missing parameter ID.")
      return
    }

    saving.value = true

    router.post(`/function/${props.functionName}/function/${encodeURIComponent(parameterIdentifier)}/description`, {
      description: editingValue.value
    }, {
      onSuccess: () => {
        router.reload({ 
          only: ['functionDetails'],
          preserveScroll: true,
          onSuccess: () => {}
        })
        const index = functionData.value.parameters.findIndex(p => p.parameter_id === parameterIdentifier)
        if (index !== -1) {
          functionData.value.parameters[index].description = editingValue.value
        }
        success('Parameter description successfully saved!')
        cancelEdit()
      },
      onError: (errors) => {
        console.error('Erreur lors de la sauvegarde:', errors)
        showError('Error saving parameter description')
      },
      onFinish: () => {
        saving.value = false
      }
    })
  } catch (error) {
    console.error('Erreur:', error)
    saving.value = false
  }
}

const saveDescriptionAdvanced = async (parameterName) => {
  try {
    savingDescription.value[parameterName] = true
    
    const response = await axios.post(`/function/${props.functionName}/function/${parameterName}/description`, {
      description: editingDescriptionValue.value
    })
    
    if (response.data.success) {
      const parameter = functionData.value.parameters.find(p => p.parameter_name === parameterName)
      if (parameter) {
        parameter.description = editingDescriptionValue.value
      }
      cancelEditAdvanced('description', parameterName)
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    showError('Erreur: ' + (error.response?.data?.error || error.message))
  } finally {
    savingDescription.value[parameterName] = false
  }
}

const saveRangeValues = async (parameterName) => {
  try {
    savingRangeValues.value[parameterName] = true
    
    const response = await axios.post(`/function/${props.functionName}/function/${parameterName}/range-values`, {
      rangevalues: editingRangeValuesValue.value
    })
    
    if (response.data.success) {
      const parameter = functionData.value.parameters.find(p => p.parameter_name === parameterName)
      if (parameter) {
        parameter.rangevalues = editingRangeValuesValue.value
      }
      cancelEditAdvanced('rangeValues', parameterName)
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    showError('Erreur: ' + (error.response?.data?.error || error.message))
  } finally {
    savingRangeValues.value[parameterName] = false
  }
}

const updateColumnRelease = async (parameter, releaseId) => {
  if (!canEdit.value) {
    warning('You are not allowed to modified this function')
    return
  }
  
  try {
    updatingRelease.value[parameter.parameter_name] = true
    
    const finalReleaseId = releaseId === '' ? null : parseInt(releaseId)
    
    const response = await axios.post(`/function/${props.functionName}/parameter/${parameter.parameter_name}/release`, {
      release_id: finalReleaseId
    })
    
    if (response.data.success) {
      parameter.release_id = finalReleaseId
      const selectedRelease = props.availableReleases.find(r => r.id === finalReleaseId)
      parameter.release_version = selectedRelease ? selectedRelease.version_number : ''
    } else {
      throw new Error(response.data.error)
    }
  } catch (error) {
    console.error('❌ Erreur:', error)
    showError('Erreur: ' + (error.response?.data?.error || error.message))
  } finally {
    updatingRelease.value[parameter.parameter_name] = false
  }
}

const showAuditLogs = async (parameterName) => {
  try {
    console.log('🔍 Ouverture audit logs pour paramètre:', parameterName)
    
    showAuditModal.value = true
    loadingAuditLogs.value = true
    currentColumn.value = parameterName
    auditLogs.value = []
    
    const response = await axios.get(`/function/${props.functionName}/function/${parameterName}/audit-logs`)
    
    console.log('📊 Audit logs reçus:', response.data)
    
    if (response.data && Array.isArray(response.data)) {
      auditLogs.value = response.data
    } else {
      auditLogs.value = []
      console.warn('Format de réponse inattendu:', response.data)
    }
    
  } catch (error) {
    console.error('❌ Erreur lors du chargement des audit logs:', error)
    
    let errorMessage = 'Error loading history'
    
    if (error.response) {
      if (error.response.status === 404) {
        errorMessage = 'Function or parameter not found'
      } else if (error.response.status === 400) {
        errorMessage = error.response.data.error || 'Requête invalide'
      } else if (error.response.data && error.response.data.error) {
        errorMessage = error.response.data.error
      }
    } else if (error.request) {
      errorMessage = 'Network error - unable to contact the server'
    }
    
    showError(errorMessage)
    showAuditModal.value = false
    
  } finally {
    loadingAuditLogs.value = false
  }
}

const closeAuditModal = () => {
  showAuditModal.value = false
  auditLogs.value = []
  currentColumn.value = ''
  loadingAuditLogs.value = false
}

const formatDate = (dateString) => {
  if (!dateString) return 'Not specified'
  const date = new Date(dateString)
  if (isNaN(date.getTime())) {
    console.warn("Date invalide:", dateString)
    return 'Invalid Date'
  }
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Lancer le tutoriel au montage
onMounted(() => {
  console.log('🔍 [FUNCTION] Composant monté avec les props:', props)
  console.log('🔍 [FUNCTION] FunctionDetails:', props.functionDetails)
  console.log('🔍 [FUNCTION] Paramètres:', props.functionDetails?.parameters)
  console.log('🔍 [FUNCTION] Nombre de paramètres:', props.functionDetails?.parameters?.length)
  console.log('🔍 [FUNCTION] Can Edit:', canEdit.value)
  console.log('🔍 [FUNCTION] Is Owner:', isOwner.value)
  
  // Lancer le tutoriel au premier chargement
  const tutorialShown = localStorage.getItem('function_details_tutorial_shown')
  if (!tutorialShown && props.functionDetails && !props.error) {
    setTimeout(() => {
      showFunctionDetailsGuide()
      localStorage.setItem('function_details_tutorial_shown', 'true')
    }, 1000)
  }
})
</script>
