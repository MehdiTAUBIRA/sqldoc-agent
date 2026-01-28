<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
	password: '',
});

const submit = () => {
	form.post(route('password.confirm'), {
		onFinish: () => form.reset(),
	})
};
</script>

<template>
	<GuestLayout>
		<Head title="Confirm Password" />
		
		<div class="w-full max-w-md mx-auto mt-8 px-4">
			<!-- En-tête -->
			<div class="text-center mb-6">
				<div class="inline-flex h-12 w-12 bg-amber-100 rounded-full items-center justify-center mb-3">
					<svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
					</svg>
				</div>
				<h2 class="text-2xl font-bold text-gray-900 mb-2">
					Secure Area
				</h2>
				<p class="text-sm text-gray-600">
					Please confirm your password to continue
				</p>
			</div>

			<!-- Formulaire -->
			<div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
				<form @submit.prevent="submit" class="space-y-4">
					<!-- Champ Password -->
					<div>
						<InputLabel for="password" value="Password" class="text-gray-700 font-medium mb-1.5" />
						<div class="relative">
							<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
								<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
								</svg>
							</div>
							<TextInput 
								id="password" 
								type="password" 
								class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all" 
								v-model="form.password" 
								required
								autocomplete="current-password" 
								autofocus 
								placeholder="Enter your password"
							/>
						</div>
						<InputError class="mt-1.5" :message="form.errors.password" />
					</div>

					<!-- Bouton de confirmation -->
					<PrimaryButton 
						class="w-full justify-center py-2.5 px-4 font-semibold rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200" 
						:class="{ 'opacity-50 cursor-not-allowed': form.processing }" 
						:disabled="form.processing"
					>
						<svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
							<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
							<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
						</svg>
						<span v-if="!form.processing">Confirm</span>
						<span v-else>Confirming...</span>
					</PrimaryButton>
				</form>
			</div>

			<!-- Note de sécurité -->
			<div class="mt-4 text-center">
				<p class="text-xs text-gray-500 flex items-center justify-center">
					<svg class="h-3 w-3 text-amber-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
						<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
					</svg>
					This action requires password confirmation for security
				</p>
			</div>
		</div>
	</GuestLayout>
</template>
