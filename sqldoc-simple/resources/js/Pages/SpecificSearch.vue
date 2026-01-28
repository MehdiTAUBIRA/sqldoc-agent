<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800">
          <span class="text-gray-500 font-normal">Specific search</span>
        </h2>
      </div>
    </template>

    <div class="p-6">
      <!-- Filtres -->
      <form @submit.prevent="performSearch" class="mb-4 space-y-4">
        <div class="flex items-center space-x-4">
          <label>
            <input type="checkbox" v-model="searchInTables" />
            <span class="ml-1">Search for column in Table</span>
          </label>
          <label>
            <input type="checkbox" v-model="searchInViews" />
            <span class="ml-1">Search for column in View</span>
          </label>
          <label>
            <input type="checkbox" v-model="searchInIndex" />
            <span class="ml-1">Search for index</span>
          </label>
          <label>
            <input type="checkbox" v-model="searchInPk" />
            <span class="ml-1">Search for primary key</span>
          </label>
          <label>
            <input type="checkbox" v-model="searchInFk" />
            <span class="ml-1">Search for foreign key</span>
          </label>
          <label>
            <input type="checkbox" v-model="searchInDesc" />
            <span class="ml-1">Search for descriptions</span>
          </label>
        </div>

        <div class="relative w-full">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="w-4 h-4 text-gray-500" aria-hidden="true" fill="none" viewBox="0 0 20 20">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
            </svg>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Searching for..."
            class="w-full pl-10 pr-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <button
          type="submit"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Search
        </button>
      </form>

      <!-- Résultats des tables -->
      <div v-if="searchInTables && tableResults.length">
        <h3 class="text-lg font-semibold mb-2">Result - Tables</h3>
        <table class="min-w-full divide-y divide-gray-200 border mb-6">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nullable</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Key</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Range Value</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Release</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in tableResults" :key="item.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <Link 
                  :href="route('table.details', { tableName: item.table_description?.tablename || 'unknown' })"
                  class="text-blue-600 hover:text-blue-900 font-medium hover:underline"
                >
                  {{ item.table_description?.tablename || 'N/A' }}
                </Link>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.column }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.type }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.nullable }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.key }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <span
                  class="block w-full h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words pr-8"
                >
                  {{ item.description }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><span
                  class="block w-full h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words pr-8"
                >
                  {{ item.rangevalues }}
                </span>

              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.release_id }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Résultats des vues -->
      <div v-if="searchInViews && viewResults.length">
        <h3 class="text-lg font-semibold mb-2">Result - View</h3>
        <table class="min-w-full divide-y divide-gray-200 border">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">View</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nullable</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Length</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precision</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scale</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Range Value</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Release</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in viewResults" :key="item.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <Link 
                  :href="route('view.details', { viewName: item.view_description?.viewname || 'unknown' })"
                  class="text-blue-600 hover:text-blue-900 font-medium hover:underline"
                >
                  {{ item.view_description?.viewname || 'N/A' }}
                </Link>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.type }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.nullable }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.max_length }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.precision }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.scale }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <span
                  class="block w-full h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words pr-8"
                >
                  {{ item.description }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><span
                  class="block w-full h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words pr-8"
                >
                  {{ item.rangevalues }}
                </span>

              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.release_id }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Résultats des index -->
      <div v-if="searchInIndex && IndexResults.length">
        <h3 class="text-lg font-semibold mb-2">Result - Index</h3>
        <table class="min-w-full divide-y divide-gray-200 border mb-6">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">column</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">properties</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in IndexResults" :key="item.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <Link 
                  :href="route('table.details', { tableName: item.table_description?.name || 'unknown' })"
                  class="text-blue-600 hover:text-blue-900 font-medium hover:underline"
                >
                  {{ item.table_description?.name || 'N/A' }}
                </Link>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.type }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.column }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.properties }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Résultats des Pk -->
      <div v-if="searchInPk && PkResults.length">
        <h3 class="text-lg font-semibold mb-2">Result - Primary keys</h3>
        <table class="min-w-full divide-y divide-gray-200 border mb-6">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">column</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">properties</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in PkResults" :key="item.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <Link 
                  :href="route('table.details', { tableName: item.table_description?.tablename || 'unknown' })"
                  class="text-blue-600 hover:text-blue-900 font-medium hover:underline"
                >
                  {{ item.table_description?.tablename || 'N/A' }}
                </Link>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.tablename }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.type }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.column }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.properties }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Résultats des Fk -->
      <div v-if="searchInFk && FkResults.length">
        <h3 class="text-lg font-semibold mb-2">Result - Foreign keys</h3>
        <table class="min-w-full divide-y divide-gray-200 border mb-6">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">constraints</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">column</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">referenced_table</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">referenced_column</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">action</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in FkResults" :key="item.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <Link 
                  :href="route('table.details', { tableName: item.table_description?.name || 'unknown' })"
                  class="text-blue-600 hover:text-blue-900 font-medium hover:underline"
                >
                  {{ item.table_description?.name || 'N/A' }}
                </Link>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.constraints }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.column }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <Link 
                  :href="route('table.details', { tableName: item.referenced_table })"
                  class="text-blue-600 hover:text-blue-900 font-medium hover:underline"
                >
                  {{ item.referenced_table }}
                </Link>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.referenced_column }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.action }}</td>  
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Résultats des descriptions -->
      <div v-if="searchInDesc && descriptionResults.length">
        <h3 class="text-lg font-semibold mb-2">Result - Descriptions</h3>
        <table class="min-w-full divide-y divide-gray-200 border mb-6">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nullable</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Key</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Range Value</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Release</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in descriptionResults" :key="item.id">
              <td class="px-6 py-2">
                <Link 
                  :href="route('table.details', { tableName: item.table_description?.tablename || 'unknown' })"
                  class="text-blue-600 hover:text-blue-900 font-medium hover:underline"
                >
                  {{ item.table_description?.tablename || 'N/A' }}
                </Link>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.column }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.type }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.nullable }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.key }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <span
                  class="block w-full h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words pr-8"
                >
                  {{ item.description }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><span
                  class="block w-full h-[80px] text-sm border rounded px-2 py-1 overflow-y-auto whitespace-pre-wrap break-words pr-8"
                >
                  {{ item.rangevalues }}
                </span>

              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ item.release_id }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Aucun résultat -->
      <div
        v-if="hasSearched && (tableResults.length === 0 && viewResults.length === 0 && IndexResults.length === 0 && PkResults.length === 0 && FkResults.length === 0 && descriptionResults.length === 0)"
        class="mt-6 text-gray-500"
      >
        No result matched your research.
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const searchQuery = ref('');
const searchInTables = ref(false);
const searchInViews = ref(false);
const searchInIndex = ref(false);
const searchInPk = ref(false);
const searchInFk = ref(false);
const searchInDesc = ref(false);

const tableResults = ref([]);
const viewResults = ref([]);
const IndexResults = ref([]);
const PkResults = ref([]);
const FkResults = ref([]);
const descriptionResults = ref([]);
const hasSearched = ref(false);

const performSearch = () => {
  hasSearched.value = true;

  router.get(
    '/specific-search',
    {
      column: searchQuery.value,
      in_tables: searchInTables.value,
      in_views: searchInViews.value,
      in_index: searchInIndex.value,
      in_pk: searchInPk.value,
      in_fk: searchInFk.value,
      in_descriptions: searchInDesc.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        tableResults.value = page.props.tableResults || [];
        viewResults.value = page.props.viewResults || [];
        IndexResults.value = page.props.IndexResults || [];
        PkResults.value = page.props.PkResults || [];
        FkResults.value = page.props.FkResults || [];
        descriptionResults.value = page.props.descriptionResults || [];
      },
    }
  );
};

</script>
