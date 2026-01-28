<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <!-- Logo à gauche -->
        <div class="hidden h-full md:flex md:w-1/2 items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100 p-8">
            <ApplicationLogo />
        </div>

        <!-- Formulaire à droite -->
        <div class="flex items-center justify-center p-6 sm:p-12 md:w-1/2">
            <div class="w-full max-w-md">
                <!-- Logo du tenant (uniquement s'il existe) -->
                <div v-if="tenant?.logo" class="flex justify-center mb-6">
                    <img 
                        :src="tenant.logo" 
                        :alt="`${tenant.name} logo`"
                        class="h-16 w-auto object-contain"
                    />
                </div>

                <!-- Nom du tenant (uniquement s'il existe) -->
                <div v-if="tenant?.name" class="mb-6 text-center">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        {{ tenant.name }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Connection to your workspace</p>
                </div>

                <h1 class="mb-6 text-2xl font-semibold text-gray-700">Login</h1>

                <!-- Message de statut -->
                <div v-if="status" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm font-medium text-blue-700">{{ status }}</p>
                </div>

        <form @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput 
                            id="email" 
                            type="email" 
                            class="block w-full mt-1"
                            v-model="form.email"
                            required 
                            autofocus 
                            autocomplete="username" 
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-4" id="password-field">
                        <InputLabel for="password" value="Password" />
                        <TextInput 
                            id="password" 
                            type="password" 
                            class="block w-full mt-1"
                            v-model="form.password"
                            required 
                            autocomplete="current-password" 
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between mt-6">
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-gray-600 underline hover:text-blue-900"
                        >
                            Forgot your password?
                        </Link>

                        <PrimaryButton 
                            id="login-button"
                            :class="{ 'opacity-25': form.processing }" 
                            :disabled="form.processing"
                        >
                            Log in
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </GuestLayout>
</template>
