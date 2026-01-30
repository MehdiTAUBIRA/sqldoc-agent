<!-- resources/js/Pages/Agent/AgentLogin.vue -->
<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-md">
            <div>
                <h2 class="text-center text-3xl font-extrabold text-gray-900">
                    <img
                        src="/images/openart-image_GwOKeCKx_1750239441227_raw.jpg"
                        class="h-10 w-auto mx-auto mb-4"
                    />
                    Agent Connection
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Connect this agent to your web application
                </p>
            </div>

            <!-- ✅ Historique des connexions -->
            <div v-if="agentHistory && agentHistory.length > 0" class="space-y-3">
                <h3 class="text-sm font-medium text-gray-700">Recent Connections</h3>
                
                <div
                    v-for="agent in agentHistory"
                    :key="agent.id"
                    class="bg-gray-50 border border-gray-200 rounded-md p-4 hover:bg-gray-100 transition"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <!-- Mode édition -->
                            <div v-if="editingAgent === agent.id" class="flex gap-2">
                                <input
                                    v-model="editForm.organization_name"
                                    type="text"
                                    class="flex-1 px-2 py-1 border rounded text-sm"
                                    @keyup.enter="saveEdit"
                                    @keyup.esc="editingAgent = null"
                                />
                                <button
                                    @click="saveEdit"
                                    class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700"
                                >
                                    Save
                                </button>
                                <button
                                    @click="editingAgent = null"
                                    class="px-2 py-1 bg-gray-600 text-white rounded text-xs hover:bg-gray-700"
                                >
                                    Cancel
                                </button>
                            </div>
                            <!-- Mode affichage -->
                            <div v-else>
                                <p class="text-sm font-semibold text-gray-900 truncate">
                                    {{ agent.organization_name || agent.tenant_name }}
                                </p>
                                <p class="text-xs text-gray-500 truncate">{{ agent.api_url }}</p>
                                <p class="text-xs text-gray-400">{{ agent.last_connected_at }}</p>
                            </div>
                        </div>
                        
                        <div class="flex gap-2 ml-4">
                            <button
                                v-if="editingAgent !== agent.id"
                                @click="startEdit(agent)"
                                class="px-2 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700"
                                title="Rename"
                            >
                                ✏️
                            </button>
                            <button
                                @click="reconnect(agent.id)"
                                :disabled="reconnecting === agent.id"
                                class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ reconnecting === agent.id ? '...' : 'Connect' }}
                            </button>
                            <button
                                @click="deleteAgent(agent.id)"
                                class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700"
                                title="Delete"
                            >
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    @click="showNewForm = !showNewForm"
                    class="w-full text-center py-2 text-sm text-blue-600 hover:text-blue-800"
                >
                    {{ showNewForm ? '− Hide Form' : '+ Connect New Organization' }}
                </button>
            </div>

            <!-- ✅ Formulaire pour nouvelle connexion -->
            <div v-if="!agentHistory || agentHistory.length === 0 || showNewForm">
                <div v-if="agentHistory && agentHistory.length > 0" class="relative mb-4">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Or enter new credentials</span>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- API URL -->
                    <div>
                        <label for="api_url" class="block text-sm font-medium text-gray-700">
                            API URL
                        </label>
                        <input
                            id="api_url"
                            v-model="form.api_url"
                            type="url"
                            placeholder="https://your-tenant.domain.com"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            :class="{ 'border-red-500': errors.api_url }"
                            required
                        />
                        <p v-if="errors.api_url" class="mt-1 text-sm text-red-600">
                            {{ errors.api_url }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            ⚠️ Enter only the base URL
                            <br>
                            ✅ Example: https://test.test-sqlinfo.io
                        </p>
                    </div>

                    <!-- Agent Token -->
                    <div>
                        <label for="token" class="block text-sm font-medium text-gray-700">
                            Agent Token
                        </label>
                        <input
                            id="token"
                            v-model="form.token"
                            type="text"
                            placeholder="Paste your 64-character token here"
                            minlength="64"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                            :class="{ 'border-red-500': errors.token }"
                            required
                        />
                        <p v-if="errors.token" class="mt-1 text-sm text-red-600">
                            {{ errors.token }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            Get this token from your web application
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            <span v-if="form.processing" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Connecting...
                            </span>
                            <span v-else>Connect Agent</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 text-center text-sm text-gray-600">
                <p>Need help? Check the documentation</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    errors: Object,
    agentHistory: Array,
});

const form = useForm({
    api_url: '',
    token: '',
});

const editForm = useForm({
    agent_id: null,
    organization_name: '',
});

const showNewForm = ref(false);
const reconnecting = ref(null);
const editingAgent = ref(null);

const submit = () => {
    form.post('/agent/login');
};

const reconnect = (agentId) => {
    reconnecting.value = agentId;
    
    router.post('/agent/reconnect', {
        agent_id: agentId
    }, {
        onFinish: () => {
            reconnecting.value = null;
        }
    });
};

const deleteAgent = (agentId) => {
    if (confirm('Delete this agent from history?')) {
        router.delete(`/agent/${agentId}`);
    }
};

const startEdit = (agent) => {
    editingAgent.value = agent.id;
    editForm.agent_id = agent.id;
    editForm.organization_name = agent.organization_name || agent.tenant_name;
};

const saveEdit = () => {
    editForm.put(`/agent/${editForm.agent_id}/name`, {
        onSuccess: () => {
            editingAgent.value = null;
        },
    });
};
</script>