<template>
    <div class="flex items-center justify-center min-h-screen p-6 bg-gray-50">
        <div class="flex w-full max-w-5xl overflow-hidden bg-white rounded-lg shadow-xl">
            <slot />
        </div>
    </div>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
</script>
