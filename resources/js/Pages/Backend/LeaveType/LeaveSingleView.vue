<script setup>
import { defineProps } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';

const props = defineProps({
    leaveDetails: Object,
    pageTitle: String,
    breadcrumbs: Array
});
</script>

<template>
    <BackendLayout>
        <div class="w-full p-4 mt-3 bg-white rounded-md shadow-md dark:bg-slate-100">
            <h1 class="py-2 text-xl font-bold text-center dark:text-white">{{ pageTitle }}</h1>

            <nav aria-label="Breadcrumb" class="text-center">
                <ol class="inline-flex space-x-2 text-gray-500">
                    <li v-for="(breadcrumb, index) in breadcrumbs" :key="index">
                        <router-link v-if="breadcrumb.link" :to="breadcrumb.link">{{ breadcrumb.title }}</router-link>
                        <span v-else>{{ breadcrumb.title }}</span>
                    </li>
                </ol>
            </nav>

            <!-- Simple Leave Details Card -->
            <div class="my-4 p-4 border rounded-lg shadow-md bg-gray-50 dark:bg-gray-800 flex flex-col items-center">
                <h2 class="text-lg font-semibold text-center">Leave Details</h2>
                <div class="mt-2 text-left">
                    <p><strong>ID:</strong> {{ leaveDetails.id }}</p>
                    <p><strong>Apply Date:</strong> {{ leaveDetails.apply_date }}</p>
                    <p><strong>From:</strong> {{ leaveDetails.from }}</p>
                    <p><strong>To:</strong> {{ leaveDetails.to }}</p>
                    <p><strong>Reason:</strong> {{ leaveDetails.reason }}</p>
                    <p><strong>Status:</strong> {{ leaveDetails.status }}</p>
                    <p v-if="leaveDetails.attachment">
                        <strong>Attachment:</strong> 
                        <a :href="leaveDetails.attachment" class="text-blue-500 underline" target="_blank">View Attachment</a>
                    </p>
                    <p><strong>Created At:</strong> {{ leaveDetails.created_at }}</p>
                    <p><strong>Updated At:</strong> {{ leaveDetails.updated_at }}</p>
                    <p v-if="leaveDetails.deleted_at"><strong>Deleted At:</strong> {{ leaveDetails.deleted_at }}</p>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>
