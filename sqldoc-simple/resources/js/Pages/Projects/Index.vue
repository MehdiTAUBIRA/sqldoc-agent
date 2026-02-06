<template>
    <div>
    <Head title="Projects" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Projects
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
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Messages Flash -->
                <div v-if="flashMessage" class="mb-6">
                    <!-- Message de succès -->
                    <div v-if="flashMessage.type === 'success'" class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">{{ flashMessage.message }}</p>
                            </div>
                            <div class="ml-auto pl-3">
                                <button @click="hideFlashMessage" class="text-green-400 hover:text-green-600">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Message d'avertissement -->
                    <div v-else-if="flashMessage.type === 'warning'" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">{{ flashMessage.message }}</p>
                            </div>
                            <div class="ml-auto pl-3">
                                <button @click="hideFlashMessage" class="text-yellow-400 hover:text-yellow-600">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Message d'information -->
                    <div v-else-if="flashMessage.type === 'info'" class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">{{ flashMessage.message }}</p>
                            </div>
                            <div class="ml-auto pl-3">
                                <button @click="hideFlashMessage" class="text-blue-400 hover:text-blue-600">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Message d'erreur -->
                    <div v-else-if="flashMessage.type === 'error'" class="bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">{{ flashMessage.message }}</p>
                            </div>
                            <div class="ml-auto pl-3">
                                <button @click="hideFlashMessage" class="text-red-400 hover:text-red-600">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center mb-6">
                    <!-- afficher les projets supprimés -->
                    <div class="flex items-center space-x-4">
                        <button
                            @click="showDeleted = false"
                            :class="[
                                'px-4 py-2 rounded-md text-sm font-medium',
                                !showDeleted 
                                    ? 'bg-indigo-100 text-indigo-700' 
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Active Projects ({{ activeProjects.length }})
                        </button>
                        <button
                            @click="loadDeletedProjects"
                            :class="[
                                'px-4 py-2 rounded-md text-sm font-medium',
                                showDeleted 
                                    ? 'bg-red-100 text-red-700' 
                                    : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Deleted Projects ({{ props.deletedProjects.length }})
                        </button>
                    </div>

                    <Link
                        id="create-project-button"
                        :href="route('projects.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Create a new project
                    </Link>
                </div>

                <div id="projects-list" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <!-- Projets actifs -->
                    <div v-if="!showDeleted">
                    <div v-if="activeProjects.length === 0" class="text-center py-8">
                        <h3 class="text-lg font-medium text-gray-900">You don't have any projects yet</h3>
                        <p class="mt-2 text-gray-600">Start by creating a new project or ask an administrator to grant you access to existing projects.</p>
                    </div>
    
                        <div v-else class="space-y-8">
                            <!-- Projets dont vous êtes propriétaire -->
                            <div v-if="ownedProjects.length > 0">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Your Projects ({{ ownedProjects.length }})
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div 
                                v-for="(project, index) in ownedProjects" 
                                :key="'owned-' + project.id"
                                :id="index === 0 ? 'project-card-1' : undefined"
                                class="border-2 border-blue-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow bg-blue-50"
                                >
                                        <div class="p-4 border-b bg-blue-100">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <h4 class="text-lg font-semibold text-gray-800">{{ project.name }}</h4>
                                                    <p class="text-sm text-gray-600 mt-1">{{ project.description || 'No description' }}</p>
                                                    <div class="flex items-center space-x-2 mt-2">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ getBdTypeName(project.db_type) }}
                                                        </span>
                                                        <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border', getAccessColor(project.access_level)]">
                                                            {{ getAccessIcon(project.access_level) }} {{ getAccessText(project.access_level) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <!-- Bouton delete seulement pour les propriétaires -->
                                                <button
                                                    @click="confirmDelete(project)"
                                                    class="p-1 text-gray-400 hover:text-red-600 transition-colors"
                                                    title="Delete project"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="red">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="flex justify-end">
                                                <button
                                                    @click="openProject(project)"
                                                    :disabled="openingProject === project.id"
                                                    class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                                >
                                                    <span v-if="openingProject === project.id" class="flex items-center">
                                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        Opening...
                                                    </span>
                                                    <span v-else>Open</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Projets partagés avec vous -->
                            <div v-if="sharedProjects.length > 0">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                    <svg class="h-5 w-5 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                    </svg>
                                    Shared with You ({{ sharedProjects.length }})
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div 
                                        v-for="project in sharedProjects" 
                                        :key="'shared-' + project.id"
                                        class="border-2 border-blue-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow bg-blue-50"
                                    >
                                        <div class="p-4 border-b bg-blue-100">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <h4 class="text-lg font-semibold text-gray-800">{{ project.name }}</h4>
                                                    <p class="text-sm text-gray-600 mt-1">{{ project.description || 'No description' }}</p>
                                                    <p class="text-xs text-gray-500 mt-1">Owner: {{ project.owner_name }}</p>
                                                    <div class="flex items-center space-x-2 mt-2">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            {{ getBdTypeName(project.db_type) }}
                                                        </span>
                                                        <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border', getAccessColor(project.access_level)]">
                                                            {{ getAccessIcon(project.access_level) }} {{ getAccessText(project.access_level) }}
                                                        </span>
                                                    </div>
                                                    <p v-if="project.shared_at" class="text-xs text-gray-500 mt-1">
                                                        Shared: {{ formatDate(project.shared_at) }}
                                                    </p>
                                                </div>
                                                <!-- Pas de bouton delete pour les projets partagés -->
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="flex justify-end">
                                                <button
                                                    @click="openProject(project)"
                                                    :disabled="openingProject === project.id"
                                                    class="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                                >
                                                    <span v-if="openingProject === project.id" class="flex items-center">
                                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        Opening...
                                                    </span>
                                                    <span v-else>Open</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Projets supprimés -->
                    <div v-else>
                        <div v-if="props.deletedProjects.length === 0" class="text-center py-8">
                            <h3 class="text-lg font-medium text-gray-900">No deleted project</h3>
                            <p class="mt-2 text-gray-600">Deleted project will appear here.</p>
                        </div>
                        
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div 
                                v-for="project in props.deletedProjects" 
                                :key="project.id"
                                class="border border-red-200 rounded-lg overflow-hidden bg-red-50"
                            >
                                <div class="p-4 border-b bg-red-100">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800">{{ project.name }}</h3>
                                            <p class="text-sm text-red-600 mt-1">
                                                Deleted on {{ formatDate(project.deleted_at) }}
                                            </p>
                                        </div>
                                        <!-- Bouton restore -->
                                        <button
                                            @click="restoreProject(project)"
                                            class="p-1 text-gray-400 hover:text-green-600 transition-colors"
                                            title="Restore the project"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            @click="restoreProject(project)"
                                            class="inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                        >
                                            Restore
                                        </button>
                                        <button
                                            @click="confirmForceDelete(project)"
                                            class="inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                        >
                                            Delete Forever
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ✅ NOUVEAU MODAL: Aperçu des dépendances -->
        <div v-if="showDependenciesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        Project Dependencies - {{ selectedProject?.name }}
                    </h3>
                    <button @click="closeDependenciesModal" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div v-if="loadingDependencies" class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-600">Loading dependencies...</p>
                </div>
                
                <div v-else>
                    <div v-if="Object.keys(projectDependencies).length === 0" class="text-center text-gray-500 py-8">
                        <svg class="h-16 w-16 text-green-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">No dependencies found</h4>
                        <p>This project can be safely deleted without affecting other data.</p>
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
                            <div class="flex">
                                <svg class="h-5 w-5 text-yellow-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text-sm text-yellow-700">
                                    <strong>Warning:</strong> This project contains data that will be permanently deleted.
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div v-for="(count, type) in projectDependencies" :key="type" 
                                 class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border">
                                <div class="flex items-center">
                                    <div class="h-3 w-3 bg-red-400 rounded-full mr-3"></div>
                                    <span class="font-medium text-gray-900 capitalize">{{ type.replace('_', ' ') }}</span>
                                </div>
                                <span class="bg-red-100 text-red-800 text-sm px-2 py-1 rounded-full font-medium">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button 
                            @click="closeDependenciesModal"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="proceedWithForceDelete"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                        >
                            Proceed with Deletion
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal confirmation suppression -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
                <div class="flex flex-col items-center">
                    <svg class="h-16 w-16 text-red-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2 text-center">
                        Delete the project
                    </h3>
                    <p class="text-sm text-gray-500 text-center mb-6">
                        Do you really want to delete the project <span class="font-semibold">{{ selectedProject?.name }}</span> ?<br>
                        The project won't be available anymore.
                    </p>
                    
                    <div class="flex justify-center space-x-4 w-full">
                        <button 
                            @click="showDeleteModal = false"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="deleteProject"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                            :disabled="deleting"
                        >
                            {{ deleting ? 'Deletion...' : 'Delete' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal confirmation suppression définitive -->
        <div v-if="showForceDeleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
                <!-- Overlay de chargement pour le modal -->
                <div v-if="forceDeleting" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 rounded-md">
                    <div class="flex flex-col items-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600 mb-4"></div>
                        <p class="text-gray-600 text-lg font-medium">Deleting project...</p>
                        <p class="text-gray-500 text-sm mt-2">Please wait, this may take a moment</p>
                    </div>
                </div>

                <div class="flex flex-col items-center">
                    <svg class="h-20 w-20 text-red-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 text-center">
                        ⚠️ PERMANENT DELETION
                    </h3>
                    <div class="text-center mb-6 space-y-2 w-full">
                        <p class="text-lg font-semibold text-red-600">
                            Delete project "{{ selectedProject?.name }}" FOREVER?
                        </p>
                        <div class="bg-red-50 border border-red-200 rounded-md p-4 text-left">
                            <p class="text-sm text-red-800 font-medium mb-2">⚠️ This will permanently delete:</p>
                            <ul class="text-xs text-red-700 space-y-1 list-disc list-inside">
                                <li>All databases and tables</li>
                                <li>All columns, indexes, and relations</li>
                                <li>All triggers and procedures</li>
                                <li>All project data and metadata</li>
                                <li>All user permissions for this project</li>
                            </ul>
                            <p class="text-sm text-red-800 font-bold mt-3">
                                🚨 THIS ACTION CANNOT BE UNDONE!
                            </p>
                        </div>

                        <!-- ✅ AJOUT: Champ de confirmation -->
                        <div class="mt-6 text-left">
                            <label for="delete-confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                Type <span class="font-bold text-red-600">DELETE</span> to confirm:
                            </label>
                            <input
                                id="delete-confirmation"
                                v-model="deleteConfirmationText"
                                type="text"
                                placeholder="Type DELETE here"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                :class="{ 'border-red-500': deleteConfirmationError }"
                                :disabled="forceDeleting"
                                @input="deleteConfirmationError = false"
                                @keyup.enter="forceDeleteProject"
                            />
                            <p v-if="deleteConfirmationError" class="mt-1 text-sm text-red-600">
                                Please type "DELETE" exactly to confirm
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-center space-x-4 w-full">
                        <button 
                            @click="cancelForceDelete"
                            :disabled="forceDeleting"
                            class="px-6 py-3 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="forceDeleteProject"
                            :disabled="forceDeleting || deleteConfirmationText.toUpperCase() !== 'DELETE'"
                            class="px-6 py-3 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed font-bold"
                        >
                            {{ forceDeleting ? 'DELETING...' : 'DELETE FOREVER' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { useToast } from '@/Composables/useToast'
import axios from 'axios';
import { useDriver } from '@/Composables/useDriver.js';

const { success, error: showError, warning, info, confirmToast } = useToast()
const { showProjectsGuide } = useDriver();

const props = defineProps({
    projects: Array,
    deletedProjects: Array,
    stats: Object
});

const page = usePage();

// États
const showDeleted = ref(false);
const showDeleteModal = ref(false);
const showForceDeleteModal = ref(false);
const selectedProject = ref(null);
const deleting = ref(false);
const openingProject = ref(null);
const flashMessage = ref(null);

const showDependenciesModal = ref(false);
const projectDependencies = ref({});
const loadingDependencies = ref(false);
const forceDeleting = ref(false);

const deleteConfirmationText = ref('');
const deleteConfirmationError = ref(false);

// Projets actifs (non supprimés)
const activeProjects = computed(() => {
    return props.projects || [];
});

const ownedProjects = computed(() => {
    return activeProjects.value.filter(project => project.is_owner);
});

const sharedProjects = computed(() => {
    return activeProjects.value.filter(project => !project.is_owner);
});

const getAccessIcon = (accessLevel) => {
    switch (accessLevel) {
        case 'owner':
        case 'Admin':
            return '👑';
        case 'write':
            return '✏️';
        case 'read':
            return '👁️';
        default:
            return '❓';
    }
};

const getAccessColor = (accessLevel) => {
    switch (accessLevel) {
        case 'owner':
        case 'Admin':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'write':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'read':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const getAccessText = (accessLevel) => {
    switch (accessLevel) {
        case 'owner':
            return 'Owner';
        case 'Admin':
            return 'Full Admin';
        case 'write':
            return 'Read/Write';
        case 'read':
            return 'Read Only';
        default:
            return 'Unknown';
    }
};

// Surveiller les messages flash
watch(
  () => page.props.flash,
  (flash) => {
    if (!flash || Object.keys(flash).length === 0) return;

    const types = ['success', 'error', 'warning', 'info'];

    for (const type of types) {
      if (flash[type]) {
        flashMessage.value = {
          type,
          message: flash[type],
        };
        break;
      }
    }

    if (flashMessage.value?.type !== 'error') {
      setTimeout(hideFlashMessage, 8000);
    }
  },
  { immediate: true });

const hideFlashMessage = () => {
    flashMessage.value = null;
};

const getBdTypeName = (type) => {
    const types = {
        'mysql': 'MySQL',
        'sqlserver': 'SQL Server',
        'pgsql': 'PostgreSQL'
    };
    return types[type] || type;
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const openProject = (project) => {
    if (openingProject.value) return;
    
    openingProject.value = project.id;
    
    router.get(route('projects.open', project.id));
};


const openProjectWithPreload = async (project) => {
  if (!project) {
    Error('No projects provided to openProjectWithPreload');
    return;
  }

  openingProject.value = project.id;

  try {
    preloadDashboard();

    router.get(route('projects.open', project.id), {}, {
      preserveState: false,
      preserveScroll: false,
      onFinish: () => {
        openingProject.value = null;
      }
    });

  } catch (error) {
    console.error('Error:', error);
    flashMessage.value = {
      type: 'error',
      message: 'Failed to open project.'
    };
    openingProject.value = null;
  }
};


const loadDeletedProjects = () => {
    showDeleted.value = true;
    
    // Vérifier si l'utilisateur a les droits admin
    if (!props.deletedProjects || props.deletedProjects.length === 0) {
        flashMessage.value = {
            type: 'warning',
            message: 'No deleted projects or insufficient permissions.'
        };
    }
};

const confirmDelete = (project) => {
    selectedProject.value = project;
    showDeleteModal.value = true;
};

// ✅ NOUVELLE FONCTION: Afficher l'aperçu des dépendances avant suppression forcée
const showProjectDependencies = async (project) => {
    try {
        selectedProject.value = project;
        loadingDependencies.value = true;
        showDependenciesModal.value = true;
        
        console.log('🔍 Loading dependencies for the project:', project.id);
        
        const response = await axios.get(`/projects/${project.id}/deletion-preview`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            }
        });
        
        if (response.data.success) {
            projectDependencies.value = response.data.dependencies || {};
            success('📊 Dependencies charged:', projectDependencies.value);
        } else {
            showError(response.data.error || 'Error while charging dependencies');
        }
    } catch (error) {
        console.error('❌ Error while charging dependencies:', error);
        flashMessage.value = { 
            type: 'error', 
            message: 'Error while charging dependencies: ' + (error.response?.data?.error || error.message)
        };
    } finally {
        loadingDependencies.value = false;
    }
};

// Confirmer la suppression définitive
const confirmForceDelete = (project) => {
    // Afficher directement les dépendances
    showProjectDependencies(project);
};

// Suppression forcée avec router Inertia
const forceDeleteProject = async () => {
    if (!selectedProject.value) {
        flashMessage.value = { type: 'error', message: 'No project selected' };
        return;
    }

    // Vérifier la confirmation
    if (deleteConfirmationText.value.toUpperCase() !== 'DELETE') {
        deleteConfirmationError.value = true;
        return;
    }

    try {
        forceDeleting.value = true;
        console.log('🗑️ Forced project deletion begins:', selectedProject.value.id);
        
        // ROUTER INERTIA 
        router.delete(`/projects/${selectedProject.value.id}/force`, {
            preserveState: false,
            onSuccess: (page) => {
                console.log('✅ Project successfully deleted');
                showForceDeleteModal.value = false;
                selectedProject.value = null;
                projectDependencies.value = {};
                deleteConfirmationText.value = '';
                deleteConfirmationError.value = false;
                
                flashMessage.value = { 
                    type: 'success', 
                    message: 'Project and all its dependencies permanently deleted!' 
                };
                
                // Recharger les projets supprimés si on est dans cette vue
                if (showDeleted.value) {
                    router.reload({ only: ['deletedProjects'] });
                } else {
                    // Sinon recharger la page
                    router.reload({ only: ['projects'] });
                }
            },
            onError: (errors) => {
                console.error('❌ Error during forced deletion:', errors);
                
                let errorMessage = 'Error during force deletion';
                if (typeof errors === 'object' && errors !== null) {
                    const firstError = Object.values(errors)[0];
                    if (typeof firstError === 'string') {
                        errorMessage = firstError;
                    } else if (Array.isArray(firstError) && firstError.length > 0) {
                        errorMessage = firstError[0];
                    }
                } else if (typeof errors === 'string') {
                    errorMessage = errors;
                }
                
                flashMessage.value = { 
                    type: 'error', 
                    message: errorMessage
                };
            },
            onFinish: () => {
                forceDeleting.value = false;
                console.log('🏁 Forced deletion finished');
            }
        });

    } catch (error) {
        console.error('❌ Error during forced deletion:', error);
        flashMessage.value = { 
            type: 'error', 
            message: 'Error: ' + (error.response?.data?.error || error.message) 
        };
        forceDeleting.value = false;
    }
};

// ✅ FONCTION pour fermer le modal des dépendances
const closeDependenciesModal = () => {
    showDependenciesModal.value = false;
    selectedProject.value = null;
    projectDependencies.value = {};
};

// ✅ FONCTION pour procéder à la suppression depuis le modal des dépendances
const proceedWithForceDelete = () => {
    showDependenciesModal.value = false;
    showForceDeleteModal.value = true;
    deleteConfirmationText.value = ''; // Réinitialiser le champ
    deleteConfirmationError.value = false;
};

const cancelForceDelete = () => {
    showForceDeleteModal.value = false;
    selectedProject.value = null;
    deleteConfirmationText.value = '';
    deleteConfirmationError.value = false;
};

const deleteProject = async () => {
    deleting.value = true;
    
    try {
        // router Inertia pour la suppression soft
        router.delete(`/projects/${selectedProject.value.id}/soft`, {
            preserveState: false,
            onSuccess: () => {
                showDeleteModal.value = false;
                selectedProject.value = null;
                flashMessage.value = { 
                    type: 'success', 
                    message: 'Project successfully deleted' 
                };
            },
            onError: (errors) => {
                console.error('Error while deleting:', errors);
                flashMessage.value = { 
                    type: 'error', 
                    message: 'Error: ' + (Object.values(errors)[0] || 'Unknown error')
                };
            },
            onFinish: () => {
                deleting.value = false;
            }
        });
    } catch (error) {
        console.error('Error while deleting:', error);
        flashMessage.value = { 
            type: 'error', 
            message: 'Error: ' + (error.response?.data?.error || error.message) 
        };
        deleting.value = false;
    }
};

const restoreProject = (project) => {
    router.post(`/projects/${project.id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            
        }
    })
}

const isAdmin = computed(() => {
    return window.Laravel?.user?.role === 'Admin' || 
           page.props.auth?.user?.role === 'Admin';
});

const preloadDashboard = () => import('@/Pages/Dashboard.vue');

onMounted(() => {
  console.log('Projects component mounted');
  console.log('Active projects:', activeProjects.value.length);

  preloadDashboard(); 
  //openProjectWithPreload();

  // Vérifier si le tutoriel a déjà été montré
    const tutorialShown  = localStorage.getItem('projects_tutorial_shown');
    if (!tutorialShown) {
    // Attendre que le DOM soit complètement chargé
    setTimeout(() => {
      showProjectsGuide();
      localStorage.setItem('projects_tutorial_shown', 'true');
    }, 1000); 
  }
});

const restartTutorial = () => {
  localStorage.removeItem('projects_tutorial_shown');
  showProjectsGuide();
};

</script>