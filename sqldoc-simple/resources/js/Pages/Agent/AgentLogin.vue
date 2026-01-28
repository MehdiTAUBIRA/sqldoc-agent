<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-md">
            <div>
                <h2 class="text-center text-3xl font-extrabold text-gray-900">
                    <img
                        src="/images/openart-image_GwOKeCKx_1750239441227_raw.jpg"
                        class="h-10 w-auto"
                    />
                     Agent Connection
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Connect this agent to your web application
                </p>
            </div>

            <!-- Bouton de reconnexion rapide si credentials sauvegardées -->
            <div v-if="hasCredentials" class="bg-blue-50 border border-blue-200 rounded-md p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-900">
                            Saved credentials found
                        </p>
                        <p class="text-xs text-blue-700 mt-1">
                            {{ savedApiUrl }}
                        </p>
                    </div>
                    <button
                        @click="reconnect"
                        :disabled="reconnecting"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm disabled:opacity-50"
                    >
                        {{ reconnecting ? 'Connecting...' : 'Reconnect' }}
                    </button>
                </div>
            </div>

            <div class="relative">
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
                    <!-- ✅ Instructions claires -->
                    <p class="mt-1 text-xs text-gray-500">
                        ⚠️ Enter only the base URL
                        <br>
                        ✅ Example: https://test.test-sqlinfo.io
                        <br>
                        ✅ or: https://www.test.test-sqlinfo.io
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
                        Get this token from your web application during registration
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
    hasCredentials: Boolean,
    savedApiUrl: String,
});

const form = useForm({
    api_url: props.savedApiUrl || '',
    token: '',
});

const reconnecting = ref(false);

const submit = () => {
    form.post(route('agent.login.submit'));
};

const reconnect = () => {
    reconnecting.value = true;
    router.post(route('agent.reconnect'), {}, {
        onFinish: () => {
            reconnecting.value = false;
        }
    });
};
</script>