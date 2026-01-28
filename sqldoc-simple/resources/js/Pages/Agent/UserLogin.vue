<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-md">
            <div>
                <h2 class="text-center text-3xl font-extrabold text-gray-900">
                    <img
                        src="/images/openart-image_GwOKeCKx_1750239441227_raw.jpg"
                        class="h-10 w-auto mx-auto mb-4"
                    />
                    User Login
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Connected to: <strong>{{ tenantName }}</strong>
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        placeholder="your@email.com"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        :class="{ 'border-red-500': errors.email }"
                        required
                    />
                    <p v-if="errors.email" class="mt-1 text-sm text-red-600">
                        {{ errors.email }}
                    </p>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        :class="{ 'border-red-500': errors.password }"
                        required
                    />
                    <p v-if="errors.password" class="mt-1 text-sm text-red-600">
                        {{ errors.password }}
                    </p>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <label for="remember" class="ml-2 block text-sm text-gray-900">
                        Remember me
                    </label>
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
                            Logging in...
                        </span>
                        <span v-else>Log in</span>
                    </button>
                </div>
            </form>

            <!-- Disconnect Agent -->
            <div class="text-center pt-4 border-t border-gray-200">
                <button
                    @click="disconnectAgent"
                    class="text-sm text-gray-600 hover:text-gray-900 hover:underline"
                >
                    🔄 Disconnect agent and switch tenant
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    errors: Object,
    tenantName: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('user.login.submit'));
};

const disconnectAgent = () => {
    if (confirm('Are you sure you want to disconnect the agent? You will need to reconnect with the token.')) {
        router.post(route('agent.logout'));
    }
};
</script>