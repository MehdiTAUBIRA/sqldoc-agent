<template>
    <div>
    <Head title="Connexion au projet" />
    
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Connection to the project: {{ project.name }}
                </h2>
                <!-- ✅ Bouton d'aide flottant -->
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
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="mb-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900">Login information</h3>
                                <p class="text-sm text-gray-600">Database type: {{ getDbTypeName(project.db_type) }}</p>

                                <div class="inline-flex items-start mt-1 mb-2 text-sm text-red-600 bg-blue-50 p-2 rounded-md">
                                    <svg class="w-4 h-4 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Make sure the port is accessible and not blocked by a firewall, check with your admin.</span>
                                </div>

                            </div>
                            <Link
                                :href="route('projects.index')"
                                class="inline-flex items-center px-3 py-2 bg-gray-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                :class="{ 'pointer-events-none opacity-50': form.processing }"
                            >
                                Back to project
                            </Link>
                        </div>
                        
                        <!-- Overlay de chargement -->
                        <div v-if="form.processing" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                            <div class="bg-white rounded-lg p-6 flex flex-col items-center shadow-xl">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mb-4"></div>
                                <p class="text-gray-700 font-medium">data transfer in progress...</p>
                                <p class="text-gray-500 text-sm mt-1">Please wait while we saving the data</p>
                            </div>
                        </div>
                        
                        <form @submit.prevent="submit" class="max-w-lg" :class="{ 'opacity-50 pointer-events-none': form.processing }">
                            <!-- ✅ ID ajouté -->
                            <div class="mb-4">
                                <InputLabel for="server" value="Server" />
                                <TextInput
                                    id="server"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.server"
                                    required
                                    placeholder="IP address"
                                    :disabled="form.processing"
                                />
                                <InputError class="mt-2" :message="form.errors.server" />
                            </div>

                            <!-- ✅ ID ajouté -->
                            <div class="mb-4">
                                <InputLabel for="database" value="Database" />
                                <TextInput
                                    id="database"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.database"
                                    required
                                    placeholder="Database name"
                                    :disabled="form.processing"
                                />
                                <InputError class="mt-2" :message="form.errors.database" />
                            </div>

                            <!-- ✅ ID ajouté -->
                            <div class="mb-4">
                                <InputLabel for="port" value="Port" />
                                <TextInput
                                    id="port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    class="mt-1 block w-full"
                                    v-model="form.port"
                                    required
                                    :placeholder="getPortPlaceholder()"
                                    :disabled="form.processing"
                                />
                                <InputError class="mt-2" :message="form.errors.port" />
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ getPortInfo() }}
                                </p>
                            </div>

                            <!-- ✅ ID ajouté pour la section d'authentification -->
                            <div v-if="project.db_type === 'sqlserver'" id="auth-mode-section" class="mb-4">
                                <InputLabel value="Authentication mode" />
                                <div class="mt-2 space-y-2">
                                    <div class="flex items-center">
                                        <input
                                            id="windows-auth"
                                            type="radio"
                                            value="windows"
                                            name="authMode"
                                            :checked="authMode === 'windows'"
                                            @change="updateAuthMode('windows')"
                                            :disabled="form.processing"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                        />
                                        <label for="windows-auth" class="ml-2 block text-sm text-gray-900">
                                            Windows Authentication
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            id="sql-auth"
                                            type="radio"
                                            value="sql"
                                            name="authMode"
                                            :checked="authMode === 'sql'"
                                            @change="updateAuthMode('sql')"
                                            :disabled="form.processing"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                        />
                                        <label for="sql-auth" class="ml-2 block text-sm text-gray-900">
                                            SQL Server Authentication
                                        </label>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.authMode" />
                            </div>

                            <!-- ✅ ID ajouté -->
                            <div v-if="showAuthFields" class="mb-4">
                                <InputLabel for="username" value="Username" />
                                <TextInput
                                    id="username"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.username"
                                    required
                                    placeholder="Database username"
                                    :disabled="form.processing"
                                />
                                <InputError class="mt-2" :message="form.errors.username" />
                            </div>

                            <!-- ✅ ID ajouté -->
                            <div v-if="showAuthFields" class="mb-4">
                                <InputLabel for="password" value="Password" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    v-model="form.password"
                                    required
                                    placeholder="Database password"
                                    :disabled="form.processing"
                                />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>

                            <!--  ID ajouté -->
                            <div class="mb-4">
                                <InputLabel for="description" value="Description (optional)" />
                                <TextareaInput
                                    id="description"
                                    class="mt-1 block w-full"
                                    v-model="form.description"
                                    rows="4"
                                    placeholder="Add a description for this connection..."
                                    :disabled="form.processing"
                                />
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <div class="flex items-center justify-between mt-6">
                                <!--  ID ajouté -->
                                <!-- <button
                                    id="test-connection-button"
                                    type="button"
                                    @click="testConnection"
                                    :disabled="form.processing"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                                >
                                    Test Connection
                                </button> -->
                                
                                <!-- ✅ ID ajouté -->
                                <PrimaryButton 
                                    id="submit-connection-button"
                                    :class="{ 'opacity-25': form.processing }" 
                                    :disabled="form.processing"
                                    class="relative"
                                >
                                    <span v-if="!form.processing">Connect</span>
                                    <span v-else class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Connecting...
                                    </span>
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div :class="getToastClasses">
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div :class="['rounded-full p-2', getToastIconAndColor.bgColor]">
                            <svg class="h-5 w-5" :class="getToastIconAndColor.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getToastIconAndColor.icon"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p :class="['text-sm font-medium', getToastIconAndColor.titleColor]">
                            {{ toastType === 'error' ? 'Connection Error' : toastType === 'success' ? 'Success' : 'Information' }}
                        </p>
                        <p :class="['mt-1 text-sm', getToastIconAndColor.messageColor]">
                            {{ toastMessage }}
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button @click="hideToast" class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <span class="sr-only">Close</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import { useDriver } from '@/Composables/useDriver.js';

const { showConnectProjectGuide } = useDriver();

const props = defineProps({
    project: Object
});

const page = usePage();

const authMode = ref(props.project.db_type === 'sqlserver' ? 'windows' : 'sql');
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('error');


const getDefaultPort = () => {
    const ports = {
        mysql: '3306',
        pgsql: '5432',
        sqlserver: '1433'
    };
    return ports[props.project.db_type] || '1433';
};

const getPortPlaceholder = () => `e.g., ${getDefaultPort()}`;

const getPortInfo = () => {
    const portInfo = {
        mysql: 'Default MySQL port: 3306',
        pgsql: 'Default PostgreSQL port: 5432',
        sqlserver: 'Default SQL Server port: 1433'
    };
    return portInfo[props.project.db_type] || 'Enter the database port';
};


const form = useForm({
    server: '',
    database: '',
    port: getDefaultPort(),
    authMode: authMode.value,
    username: '',
    password: '',
    description: ''
});


watch(authMode, (value) => {
    form.authMode = value;
});


watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.error) showErrorToast(flash.error);
        if (flash?.success) showSuccessToast(flash.success);
        if (flash?.warning) showWarningToast(flash.warning);
        if (flash?.info) showWarningToast(flash.info);
    },
    { immediate: true, deep: true }
);

const showAuthFields = computed(() => {
    return (
        props.project.db_type !== 'sqlserver' ||
        authMode.value === 'sql'
    );
});


const submit = () => {
    // SQL Server Windows Auth → do not send credentials
    if (props.project.db_type === 'sqlserver' && authMode.value === 'windows') {
        form.username = null;
        form.password = null;
    }

    form.post(route('projects.handle-connect', props.project.id), {
        onSuccess: (page) => {
            if (page.props.flash?.success) {
                showSuccessToast(page.props.flash.success);
            } else {
                showSuccessToast('Connection successful!');
            }
        },

        onError: (errors) => {
            if (typeof errors === 'string') {
                showErrorToast(errors);
                return;
            }

            const firstError = Object.values(errors)[0];

            if (Array.isArray(firstError)) {
                showErrorToast(firstError[0]);
            } else {
                showErrorToast(firstError || 'Connection failed. Please try again.');
            }
        }
    });
};


const showErrorToast = (message) => {
    toastMessage.value = message;
    toastType.value = 'error';
    showToast.value = true;
    autoHideToast();
};

const showSuccessToast = (message) => {
    toastMessage.value = message;
    toastType.value = 'success';
    showToast.value = true;
    autoHideToast();
};

const showWarningToast = (message) => {
    toastMessage.value = message;
    toastType.value = 'warning';
    showToast.value = true;
    autoHideToast();
};

const hideToast = () => {
    showToast.value = false;
    setTimeout(() => (toastMessage.value = ''), 300);
};

const autoHideToast = () => {
    setTimeout(hideToast, 5000);
};

const getDbTypeName = (type) => {
    const types = {
        mysql: 'MySQL',
        sqlserver: 'SQL Server',
        pgsql: 'PostgreSQL'
    };
    return types[type] || type;
};

const updateAuthMode = (value) => {
    authMode.value = value;
};

const restartTutorial = () => {
    localStorage.removeItem('connect_project_tutorial_shown');
    showConnectProjectGuide(props.project.db_type);
};

onMounted(() => {
    const tutorialShown = localStorage.getItem('connect_project_tutorial_shown');

    if (!tutorialShown) {
        setTimeout(() => {
            showConnectProjectGuide(props.project.db_type);
            localStorage.setItem('connect_project_tutorial_shown', 'true');
        }, 1000);
    }
});
</script>
