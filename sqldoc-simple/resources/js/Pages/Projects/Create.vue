<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import { useDriver } from '@/Composables/useDriver.js'; 

const { showCreateProjectGuide } = useDriver(); 

defineProps({
    dbTypes: Object
});

const form = useForm({
    name: '',
    db_type: '',
    description: '', 
});

const submit = () => {
    form.post(route('projects.store'));
};

// ✅ Bouton pour relancer le tutoriel
const restartTutorial = () => {
    localStorage.removeItem('create_project_tutorial_shown');
    showCreateProjectGuide();
};

// ✅ Lancer le tutoriel au montage
onMounted(() => {
    const tutorialShown = localStorage.getItem('create_project_tutorial_shown');
    
    if (!tutorialShown) {
        setTimeout(() => {
            showCreateProjectGuide();
            localStorage.setItem('create_project_tutorial_shown', 'true');
        }, 800);
    }
});
</script>

<template>
    <div>
    <Head title="Nouveau projet" />
    
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Create a new project
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
                    <form @submit.prevent="submit" class="max-w-lg mx-auto">
                        <!-- ✅ ID ajouté -->
                        <div class="mb-6">
                            <InputLabel for="name" value="Project name" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.name"
                                required
                                autofocus
                                placeholder="e.g., Customer Management System"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- ✅ ID ajouté pour la description -->
                        <div class="mb-6">
                            <InputLabel for="description" value="Description" />
                            <textarea
                                id="description"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                v-model="form.description"
                                rows="3"
                                placeholder="Brief description of your project (optional)"
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <!-- ✅ ID ajouté pour la section DB type -->
                        <div id="db-type-section" class="mb-6">
                            <InputLabel for="db_type" value="Database type" />
                            <div class="mt-1">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div 
                                        v-for="(label, value) in dbTypes" 
                                        :key="value"
                                        class="border rounded-lg p-4 cursor-pointer transition-all"
                                        :class="form.db_type === value ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200' : 'border-gray-200 hover:border-gray-300 hover:shadow-sm'"
                                        @click="form.db_type = value"
                                    >
                                        <div class="flex items-center justify-center">
                                            <input
                                                type="radio"
                                                :id="value"
                                                :value="value"
                                                v-model="form.db_type"
                                                class="hidden"
                                            />
                                            <label :for="value" class="text-center w-full cursor-pointer">
                                                <span class="block font-medium text-gray-900">{{ label }}</span>
                                                <span v-if="form.db_type === value" class="block text-xs text-indigo-600 mt-1">✓ Selected</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="form.errors.db_type" />
                        </div>

                        <div class="flex items-center justify-end mt-8 space-x-4">
                            <!-- ✅ ID ajouté pour le bouton Cancel -->
                            <Link
                                id="cancel-button"
                                :href="route('projects.index')"
                                class="inline-flex items-center px-4 py-2 bg-gray-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                Cancel
                            </Link>
                            <!-- ✅ ID ajouté pour le bouton Submit -->
                            <PrimaryButton 
                                id="submit-button"
                                :class="{ 'opacity-25': form.processing }" 
                                :disabled="form.processing"
                            >
                                Create the project
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    </div>
</template>