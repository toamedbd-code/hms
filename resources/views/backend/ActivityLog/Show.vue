<template>
    <div class="p-6">
        <div class="mb-6">
            <a href="/backend/activity-logs" class="text-blue-500 hover:text-blue-700">← Back to Logs</a>
            <h1 class="text-3xl font-bold text-gray-800 mt-2">Activity Log Details</h1>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Main Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Activity Information</h2>

                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-600">Date & Time</label>
                        <p class="text-lg font-medium text-gray-900">
                            {{ formatDate(activityLog.created_at) }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm text-gray-600">User</label>
                        <p class="text-lg font-medium text-gray-900">
                            {{ activityLog.user_name }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm text-gray-600">Module</label>
                        <p class="text-lg font-medium">
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded">
                                {{ activityLog.module }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <label class="text-sm text-gray-600">Action</label>
                        <p class="text-lg font-medium">
                            <span :class="getActionClass(activityLog.action)" class="px-3 py-1 rounded">
                                {{ getActionLabel(activityLog.action) }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <label class="text-sm text-gray-600">Status</label>
                        <p class="text-lg font-medium">
                            <span :class="getStatusClass(activityLog.status)" class="px-3 py-1 rounded">
                                {{ activityLog.status | capitalize }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Network Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold mb-4">Network Information</h2>

                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-600">IP Address</label>
                        <p class="text-lg font-mono font-medium text-gray-900">
                            {{ activityLog.ip_address || 'N/A' }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm text-gray-600">User Agent</label>
                        <p class="text-sm font-mono text-gray-600 break-words">
                            {{ activityLog.user_agent || 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-lg font-bold mb-4">Description</h2>
            <p class="text-gray-700 leading-relaxed">
                {{ activityLog.description || 'No description provided' }}
            </p>
        </div>

        <!-- Metadata -->
        <div v-if="activityLog.meta" class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-lg font-bold mb-4">Additional Data</h2>
            <div class="bg-gray-50 rounded p-4 overflow-auto">
                <pre class="text-sm text-gray-700">{{ JSON.stringify(activityLog.meta, null, 2) }}</pre>
            </div>
        </div>

        <!-- Related Information -->
        <div v-if="activityLog.meta && activityLog.meta.record_id" class="bg-blue-50 border-l-4 border-blue-500 p-6 mt-6 rounded">
            <h2 class="text-lg font-bold mb-2 text-blue-900">Related Record</h2>
            <div class="space-y-2 text-blue-800">
                <p><strong>Record ID:</strong> {{ activityLog.meta.record_id }}</p>
                <p v-if="activityLog.meta.record_name"><strong>Record Name:</strong> {{ activityLog.meta.record_name }}</p>
            </div>
        </div>
    </div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
    props: {
        activityLog: Object
    },
    methods: {
        formatDate(date) {
            return new Date(date).toLocaleString('en-UK', {
                year: 'numeric',
                month: 'long',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                weekday: 'long'
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
