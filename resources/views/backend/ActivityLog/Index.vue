<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Activity Logs</h1>
            <p class="text-gray-600 mt-2">Track all user activities and system operations</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Module</label>
                    <select v-model="filters.module" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">All Modules</option>
                        <option v-for="module in modules" :key="module" :value="module">
                            {{ module }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                    <select v-model="filters.action" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">All Actions</option>
                        <option v-for="action in actions" :key="action" :value="action">
                            {{ action }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input v-model="filters.date_from" type="date" class="w-full px-3 py-2 border rounded-lg">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input v-model="filters.date_to" type="date" class="w-full px-3 py-2 border rounded-lg">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input v-model="filters.search" type="text" placeholder="Search description..." 
                        class="w-full px-3 py-2 border rounded-lg">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        🔍 Search
                    </button>
                    <button type="button" @click="clearFilters" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Clear
                    </button>
                </div>
            </form>

            <div class="mt-4 flex gap-2">
                <a href="/backend/activity-logs/export" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                    📥 Export CSV
                </a>
            </div>
        </div>

        <!-- Activity Logs Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Module</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="log in activityLogs.data" :key="log.id" class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-600">
                            {{ formatDate(log.created_at) }}
                        </td>
                        <td class="px-6 py-3 text-sm font-medium text-gray-900">
                            {{ log.user_name }}
                        </td>
                        <td class="px-6 py-3 text-sm">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">
                                {{ log.module }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm font-medium">
                            <span class="action-badge" :class="getActionClass(log.action)">
                                {{ getActionLabel(log.action) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-600 max-w-xs truncate">
                            {{ log.description }}
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-600">
                            {{ log.ip_address || 'N/A' }}
                        </td>
                        <td class="px-6 py-3 text-sm">
                            <span :class="getStatusClass(log.status)" class="px-2 py-1 rounded text-xs font-medium">
                                {{ log.status | capitalize }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm">
                            <a :href="`/backend/activity-logs/${log.id}`" class="text-blue-500 hover:text-blue-700">
                                View
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty State -->
            <div v-if="activityLogs.data.length === 0" class="p-8 text-center text-gray-500">
                <p class="text-lg">No activity logs found</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="activityLogs.links" class="mt-6 flex justify-center gap-2">
            <a v-for="link in activityLogs.links" :key="link.label"
                :href="link.url"
                class="px-3 py-2 border rounded"
                :class="{
                    'bg-blue-500 text-white border-blue-500': link.active,
                    'text-gray-500': !link.active && !link.url,
                    'hover:bg-gray-50': link.url && !link.active
                }"
                v-html="link.label">
            </a>
        </div>
    </div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
    props: {
        activityLogs: Object,
        modules: Array,
        actions: Array,
        filters: Object
    },
    data() {
        return {
            searchFilters: { ...this.filters }
        }
    },
    methods: {
        formatDate(date) {
            return new Date(date).toLocaleString('en-UK', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            })
        },
        getActionLabel(action) {
            const labels = {
                'CREATE': '✚ Created',
                'UPDATE': '✎ Updated',
                'DELETE': '✕ Deleted',
                'VIEW': '👁 Viewed',
                'DOWNLOAD': '⬇ Downloaded',
                'LOGIN': '→ Login',
                'LOGOUT': '← Logout'
            }
            return labels[action] || action
        },
        getActionClass(action) {
            const classes = {
                'CREATE': 'bg-green-100 text-green-800',
                'UPDATE': 'bg-blue-100 text-blue-800',
                'DELETE': 'bg-red-100 text-red-800',
                'VIEW': 'bg-yellow-100 text-yellow-800',
                'DOWNLOAD': 'bg-purple-100 text-purple-800',
                'LOGIN': 'bg-indigo-100 text-indigo-800',
                'LOGOUT': 'bg-gray-100 text-gray-800'
            }
            return classes[action] || 'bg-gray-100 text-gray-800'
        },
        getStatusClass(status) {
            return status === 'success' 
                ? 'bg-green-100 text-green-800'
                : 'bg-red-100 text-red-800'
        },
        applyFilters() {
            // Submit form with filters
            window.location.href = this.buildFilterUrl()
        },
        clearFilters() {
            this.searchFilters = {
                module: '',
                action: '',
                user_id: '',
                date_from: '',
                date_to: '',
                status: '',
                search: ''
            }
            window.location.href = '/backend/activity-logs'
        },
        buildFilterUrl() {
            const params = new URLSearchParams()
            Object.entries(this.searchFilters).forEach(([key, value]) => {
                if (value) params.append(key, value)
            })
            return `/backend/activity-logs?${params.toString()}`
        }
    },
    filters: {
        capitalize(value) {
            if (!value) return ''
            value = value.toString()
            return value.charAt(0).toUpperCase() + value.slice(1)
        }
    }
})
</script>

<style scoped>
.action-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
}
</style>
