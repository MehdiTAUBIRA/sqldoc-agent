<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

defineProps({
	status: {
		type: String,
	},
});

const form = useForm({
	email: '',
});

const submit = () => {
	form.post(route('password.email'));
};
</script>

<template>
	<GuestLayout>
		<Head title="Forgot Password" />
		
		<div class="w-full max-w-md mx-auto mt-8 px-4">
			<!-- En-tête -->
			<div class="text-center mb-6">
				<div class="inline-flex h-12 w-12 bg-indigo-100 rounded-full items-center justify-center mb-3">
					<svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
					</svg>
				</div>
				<h2 class="text-2xl font-bold text-gray-900 mb-2">
					Forgot password?
				</h2>
				<p class="text-sm text-gray-600">
					Enter your email to receive a reset link
				</p>
			</div>

			<!-- Message de succès -->
			<div 
				v-if="status" 
				class="rounded-lg bg-green-50 border border-green-200 p-3 mb-4"
			>
				<div class="flex items-center">
					<svg class="h-4 w-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
					</svg>
					<p class="text-sm text-green-800">
						{{ status }}
					</p>
				</div>
			</div>

			<!-- Formulaire -->
			<div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
				<form @submit.prevent="submit" class="space-y-4">
					<!-- Champ Email -->
					<div>
						<InputLabel for="email" value="Email" class="text-gray-700 font-medium mb-1.5" />
						<div class="relative">
							<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
								<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
								</svg>
							</div>
							<TextInput 
								id="email" 
								type="email" 
								class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all" 
								v-model="form.email" 
								required 
								autofocus 
								autocomplete="username"
								placeholder="you@example.com"
							/>
						</div>
						<InputError class="mt-1.5" :message="form.errors.email" />
					</div>

					<!-- Bouton de soumission -->
					<PrimaryButton 
						class="w-full justify-center py-2.5 px-4 font-semibold rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200" 
						:class="{ 'opacity-50 cursor-not-allowed': form.processing }" 
						:disabled="form.processing"
					>
						<svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
							<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
							<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
						</svg>
						<span v-if="!form.processing">Send Reset Link</span>
						<span v-else>Sending...</span>
					</PrimaryButton>

					<!-- Lien de retour -->
					<div class="text-center pt-3 border-t border-gray-100">
						<Link 
							:href="route('login')" 
							class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500 transition-colors"
						>
							<svg class="h-4 w-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
							</svg>
							Back to login
						</Link>
					</div>
				</form>
			</div>
		</div>
	</GuestLayout>
</template>
