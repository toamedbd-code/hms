<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { useDark, useToggle } from '@vueuse/core';
import eventBus from '@/eventBus.js';

const isDark = useDark();
const toggleDark = useToggle(isDark);
const sideBarFlag = ref(false);
const isDropdownVisible = ref(false);
const isBedPanelOpen = ref(false);
const isExpiryPanelOpen = ref(false);
const isActivityPanelOpen = ref(false);
const showOnlyFailedActivities = ref(false);
const bedStatuses = ref([]);
const page = usePage();
const allowedMenuRoutes = computed(() => {
    const routes = new Set();
    const menus = page.props?.auth?.sideMenus ?? [];

    menus.forEach((menu) => {
        if (menu?.route) {
            routes.add(String(menu.route).trim());
        }

        (menu?.childrens ?? []).forEach((child) => {
            if (child?.route) {
                routes.add(String(child.route).trim());
            }
        });
    });

    return routes;
});

const canAccessMenuRoute = (name) => allowedMenuRoutes.value.has(String(name ?? '').trim());

const hasRoute = (name) => {
    if (!name) return false;
    try {
        const router = route();
        if (typeof router?.has === 'function') {
            return router.has(name);
        }
        route(name);
        return true;
    } catch (error) {
        return false;
    }
};

const userPermissions = computed(() => {
    const raw = page.props?.auth?.permissions ?? [];
    return Array.isArray(raw) ? raw : [];
});
const hasPermission = (permissionName) => userPermissions.value.includes(permissionName);
const canManageAllWebSettings = computed(() => hasPermission('websetting-add'));
const canManageCmsSettings = computed(() => canManageAllWebSettings.value || hasPermission('cms-setting'));
const canManageGeneralSettings = computed(() => canManageAllWebSettings.value || hasPermission('general-setting-add'));
const canOpenWebSetting = computed(() => (
    (canManageCmsSettings.value || canManageGeneralSettings.value)
    && canAccessMenuRoute('backend.websetting.create')
    && hasRoute('backend.websetting.create')
));
const canOpenDashboardSetting = computed(() => (
    hasPermission('dashboard-setting')
    && canAccessMenuRoute('backend.dashboard-setting.edit')
    && hasRoute('backend.dashboard-setting.edit')
));
const canOpenReportSetting = computed(() => (
    hasPermission('report-settings')
    && canAccessMenuRoute('backend.report-setting.edit')
    && hasRoute('backend.report-setting.edit')
));
const canOpenActivityLogs = computed(() => (
    activityLogAlert.value?.can_view
    && canAccessMenuRoute('backend.activity-logs.index')
    && hasRoute('backend.activity-logs.index')
));
const canOpenActivityLogsPrint = computed(() => (
    activityLogAlert.value?.can_view
    && hasPermission('activity-logs.print')
    && hasRoute('backend.activity-logs.print')
));
const adminUser = computed(() => page.props?.auth?.admin || null);
const adminDisplayName = computed(() => {
    const admin = adminUser.value;
    if (!admin) return 'Admin';

    const fullName = `${admin.first_name || ''} ${admin.last_name || ''}`.trim();
    if (fullName) return fullName;
    if (admin.name) return String(admin.name).trim();
    if (admin.email) return String(admin.email).trim();

    return 'Admin';
});
const adminInitials = computed(() => {
    const name = adminDisplayName.value;
    const tokens = name.split(/\s+/).filter(Boolean);
    if (!tokens.length) return 'A';

    const first = tokens[0].charAt(0).toUpperCase();
    const second = tokens.length > 1 ? tokens[1].charAt(0).toUpperCase() : '';
    return `${first}${second}`;
});
const adminPhoto = computed(() => {
    const photo = adminUser.value?.photo;
    if (!(typeof photo === 'string' && photo.trim())) {
        return null;
    }

    const updatedAt = adminUser.value?.updated_at;
    const version = updatedAt ? encodeURIComponent(String(updatedAt)) : Date.now();
    return `${route('backend.profile.photo')}?v=${version}`;
});
const medicineExpiryAlert = computed(() => page.props?.pharmacyAlerts?.medicineExpiry || {
    expired_count: 0,
    expiring_soon_count: 0,
    days_window: 30,
});
const totalMedicineAlerts = computed(() => {
    const expired = Number(medicineExpiryAlert.value.expired_count || 0);
    const expiringSoon = Number(medicineExpiryAlert.value.expiring_soon_count || 0);
    return expired + expiringSoon;
});
const activityLogAlertsState = ref(page.props?.activityLogAlerts || {
    can_view: false,
    today_count: 0,
    recent: [],
});
const activityLogAlert = computed(() => activityLogAlertsState.value);
const failedRecentCount = computed(() => {
    return (activityLogAlert.value?.recent || []).filter((log) => log?.status === 'failed').length;
});
const activityBadgeClass = computed(() => {
    return failedRecentCount.value > 0
        ? 'bg-rose-600 text-white animate-pulse'
        : 'bg-blue-600 text-white';
});
const activityBadgeCount = computed(() => {
    const failedCount = Number(failedRecentCount.value || 0);
    if (failedCount > 0) {
        return failedCount;
    }
    return Number(activityLogAlert.value?.today_count || 0);
});
const filteredActivityLogs = computed(() => {
    const logs = activityLogAlert.value?.recent || [];
    if (!showOnlyFailedActivities.value) {
        return logs;
    }
    return logs.filter((log) => log?.status === 'failed');
});
const groupedBeds = computed(() => {
    const groups = {};

    bedStatuses.value.forEach((bed) => {
        const groupName = String(bed?.bed_group_name ?? '').trim() || 'Unassigned';
        if (!groups[groupName]) {
            groups[groupName] = [];
        }
        groups[groupName].push(bed);
    });

    return groups;
});
const bedLoading = ref(false);
const bedError = ref('');
let activityAlertsTimer = null;

const toggleSidebar = () => {
    sideBarFlag.value = !sideBarFlag.value;
    eventBus.emit('sidebarToggled', sideBarFlag.value);
    try {
        window.localStorage.setItem('backend.sidebar.collapsed', String(sideBarFlag.value));
    } catch (error) {
        // ignore
    }
};

const restoreSidebarState = () => {
    try {
        const saved = window.localStorage.getItem('backend.sidebar.collapsed');
        if (saved === null) return;
        sideBarFlag.value = saved === 'true';
        eventBus.emit('sidebarToggled', sideBarFlag.value);
    } catch (error) {
        // ignore
    }
};

const logout = () => {
    window.open(route("backend.auth.logout"), "_self");
};

const toggleDropdown = () => {
    isDropdownVisible.value = !isDropdownVisible.value;
};

const loadBedStatuses = async () => {
    bedLoading.value = true;
    bedError.value = '';

    try {
        const response = await fetch(route('backend.bed.status.snapshot'));
        if (!response.ok) {
            throw new Error('Failed to load bed status');
        }
        bedStatuses.value = await response.json();
        bedError.value = '';
    } catch (error) {
        bedError.value = 'Unable to load bed status.';
        bedStatuses.value = [];
    } finally {
        bedLoading.value = false;
    }
};

const toggleBedPanel = async () => {
    isBedPanelOpen.value = !isBedPanelOpen.value;
    if (isBedPanelOpen.value) {
        isExpiryPanelOpen.value = false;
        isActivityPanelOpen.value = false;
    }
    if (isBedPanelOpen.value) {
        await loadBedStatuses();
    }
};

const toggleExpiryPanel = () => {
    isExpiryPanelOpen.value = !isExpiryPanelOpen.value;
    if (isExpiryPanelOpen.value) {
        isBedPanelOpen.value = false;
        isActivityPanelOpen.value = false;
    }
};

const toggleActivityPanel = () => {
    isActivityPanelOpen.value = !isActivityPanelOpen.value;
    if (isActivityPanelOpen.value) {
        isBedPanelOpen.value = false;
        isExpiryPanelOpen.value = false;
    } else {
        showOnlyFailedActivities.value = false;
    }
};

const refreshActivityAlerts = async () => {
    if (!activityLogAlert.value?.can_view) {
        return;
    }

    try {
        const resolveAlertsUrl = () => {
            if (typeof route === 'function') {
                try {
                    return route('backend.activity-logs.alerts');
                } catch (error) {
                    // continue to fallback
                }

                try {
                    return route('activity-logs.alerts');
                } catch (error) {
                    // continue to fallback
                }
            }

            return `${window.location.origin}/activity-logs/alerts`;
        };

        const alertsUrl = resolveAlertsUrl();

        const response = await fetch(alertsUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        activityLogAlertsState.value = {
            can_view: activityLogAlert.value?.can_view ?? false,
            today_count: Number(data.today_count || 0),
            recent: Array.isArray(data.recent) ? data.recent : [],
        };
    } catch (error) {
        // ignore refresh failures
    }
};

const closeNotificationPanels = () => {
    isBedPanelOpen.value = false;
    isExpiryPanelOpen.value = false;
    isActivityPanelOpen.value = false;
    showOnlyFailedActivities.value = false;
};

const handleOutsideClick = (event) => {
    const target = event.target;
    if (target instanceof Element && target.closest('[data-navbar-notification]')) {
        return;
    }

    closeNotificationPanels();
};

const selectBed = (bed) => {
    if (!bed) return;

    if (!bed?.is_available) {
        if (bed.occupied_by?.ipd_id) {
            router.visit(route('backend.ipdpatient.show', bed.occupied_by.ipd_id));
            return;
        }
        bedError.value = bedTooltip(bed) || 'Bed is occupied.';
        return;
    }

    if (!window.__ipdBedSelectReady) {
        router.visit(route('backend.ipdpatient.create'), {
            data: {
                bed_group_id: bed.bed_group_id,
                bed_id: bed.id,
            },
        });
        return;
    }

    bedError.value = '';
    eventBus.emit('bedSelected', bed);
    window.dispatchEvent(new CustomEvent('ipd-bed-selected', { detail: bed }));
    isBedPanelOpen.value = false;
};

const bedTooltip = (bed) => {
    if (!bed?.occupied_by) return '';
    return `Bed ${bed.name} | Patient: ${bed.occupied_by.patient_name} | IPD: ${bed.occupied_by.ipd_id}`;
};

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);
    restoreSidebarState();

    activityAlertsTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            refreshActivityAlerts();
        }
    }, 20000);

    refreshActivityAlerts();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick);
    if (activityAlertsTimer) {
        window.clearInterval(activityAlertsTimer);
    }
});
</script>

<template>
    <div class="relative w-full">
        <div :class="['absolute', 'w-full', { 'md:pl-[70px]': sideBarFlag, 'pl-[240px]': !sideBarFlag }]">
            <div :class="['flex px-4 items-center justify-between w-full py-3 h-[50px]', isDark ? 'border-b border-gray-700 bg-slate-900' : 'border-b border-gray-200 bg-gray-100']">
                    <div>
                    <button type="button" @click="toggleSidebar"
                        :class="['p-2 rounded transition-colors duration-200', isDark ? 'text-white hover:text-white/90 hover:bg-slate-800' : 'text-gray-500 hover:text-blue-600 hover:bg-gray-100']">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                        </svg>
                    </button>
                </div>
                <div>
                    <ul class="flex items-center space-x-2">
                        <li>
                            <button type="button" @click="toggleDark()"
                                :class="['p-2 rounded transition-colors duration-200', isDark ? 'text-gray-300 hover:text-blue-400 hover:bg-slate-800' : 'text-gray-500 hover:text-blue-600 hover:bg-gray-100']"
                                :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
                                <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                                </svg>
                                <!-- Show sun icon when in light mode -->
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25M12 18.75V21M4.5 12H6.75M17.25 12H19.5M6.343 6.343l1.591 1.591M16.066 16.066l1.591 1.591M6.343 17.657l1.591-1.591M16.066 7.934l1.591-1.591M12 8.25a3.75 3.75 0 100 7.5 3.75 3.75 0 000-7.5z" />
                                </svg>
                            </button>
                        </li>

                        <li class="relative" data-navbar-notification>
                            <button type="button" @click="toggleBedPanel"
                                :class="['p-2 rounded transition-colors duration-200', isDark ? 'text-gray-300 hover:text-blue-400 hover:bg-slate-800' : 'text-gray-500 hover:text-blue-600 hover:bg-gray-100']"
                                title="Bed Status">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 7.5A2.25 2.25 0 0 1 5.25 5.25h13.5A2.25 2.25 0 0 1 21 7.5v6.75M3 14.25v-6.75M3 14.25v2.25M21 14.25v2.25M3 14.25h18M7.5 17.25v1.5m9-1.5v1.5" />
                                </svg>
                            </button>

                            <div v-if="isBedPanelOpen"
                                :class="['absolute right-0 mt-2 w-[720px] rounded shadow-lg z-50', isDark ? 'bg-slate-900 border-gray-700' : 'bg-white border border-gray-200']">
                                <div :class="['px-3 py-2 flex items-center justify-between text-xs font-semibold', isDark ? 'border-b border-gray-700 text-white' : 'border-b border-gray-200 text-gray-700']">
                                    <span>Bed Status</span>
                                    <button type="button" :class="['text-[11px]', isDark ? 'text-gray-300 hover:text-gray-100' : 'text-gray-500 hover:text-gray-700']"
                                        @click="loadBedStatuses(); isBedPanelOpen = false">
                                        Back
                                    </button>
                                </div>

                                <div class="p-3">
                                    <div v-if="bedLoading" :class="['text-xs', isDark ? 'text-gray-300' : 'text-gray-500']">Loading...</div>
                                    <div v-else-if="bedError" :class="['text-xs', isDark ? 'text-rose-300' : 'text-red-600']">{{ bedError }}</div>

                                    <div v-else class="space-y-4 max-h-[520px] overflow-y-auto">
                                        <div v-for="(beds, group) in groupedBeds" :key="group">
                                            <div v-if="beds.length" :class="['text-xs font-semibold mb-2', isDark ? 'text-white' : 'text-gray-700']">
                                                {{ group }}
                                            </div>
                                            <div v-if="beds.length" class="grid grid-cols-4 gap-2">
                                                <button v-for="bed in beds" :key="bed.id" type="button"
                                                    @click="selectBed(bed)"
                                                    :title="bedTooltip(bed)"
                                                    class="flex items-center justify-between rounded border px-2 py-1 text-xs"
                                                    :class="bed.is_available
                                                        ? (isDark ? 'border-emerald-700 bg-emerald-800 text-white hover:bg-emerald-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100')
                                                        : (isDark ? 'border-rose-700 bg-rose-800 text-white cursor-not-allowed' : 'border-rose-200 bg-rose-50 text-rose-700 cursor-not-allowed')">
                                                    <span class="truncate flex items-center gap-1">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                            stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M3 7.5A2.25 2.25 0 0 1 5.25 5.25h13.5A2.25 2.25 0 0 1 21 7.5v6.75M3 14.25v-6.75M3 14.25v2.25M21 14.25v2.25M3 14.25h18M7.5 17.25v1.5m9-1.5v1.5" />
                                                        </svg>
                                                        <span :class="isDark ? 'text-white' : ''">{{ bed.name }}</span>
                                                    </span>
                                                    <span
                                                        class="ml-2 inline-flex h-2 w-2 rounded-full"
                                                        :class="bed.is_available ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        
                        <li v-if="activityLogAlert.can_view" class="relative" data-navbar-notification>
                            <button type="button" @click="toggleActivityPanel"
                                :class="['p-2 rounded transition-colors duration-200 block relative', isDark ? 'text-gray-300 hover:text-blue-400 hover:bg-slate-800' : 'text-gray-500 hover:text-blue-600 hover:bg-gray-100']"
                                title="User Activity Logs">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 6.75a6 6 0 0 0-6 6v3.75l-1.5 1.5h15l-1.5-1.5v-3.75a6 6 0 0 0-6-6Zm0 0V4.5m0 2.25a2.25 2.25 0 0 1 2.25 2.25" />
                                </svg>
                                <span v-if="activityBadgeCount > 0"
                                    class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full text-[10px] leading-[18px] text-center font-semibold"
                                    :class="activityBadgeClass">
                                    {{ activityBadgeCount }}
                                </span>
                            </button>

                            <div v-if="isActivityPanelOpen"
                                :class="['absolute right-0 mt-2 w-[380px] rounded shadow-lg z-50', isDark ? 'bg-slate-900 border-gray-700' : 'bg-white border border-gray-200']">
                                <div :class="['px-3 py-2 text-xs font-semibold flex items-center justify-between', isDark ? 'border-b border-gray-700 text-white' : 'border-b border-gray-200 text-white']">
                                    <span>User Activity Logs</span>
                                    <span :class="['text-[11px]', isDark ? 'text-blue-700' : 'text-white']">Today: {{ activityLogAlert.today_count || 0 }}</span>
                                </div>
                                <div :class="['px-2 pt-2 flex items-center gap-2 pb-2', isDark ? 'border-b border-gray-700' : 'border-b border-gray-100']">
                                    <button type="button"
                                        @click="showOnlyFailedActivities = false"
                                        class="px-2 py-1 text-[11px] rounded border"
                                        :class="!showOnlyFailedActivities
                                            ? 'bg-blue-600 text-white border-blue-600'
                                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                                        All ({{ activityLogAlert.recent?.length || 0 }})
                                    </button>
                                    <button type="button"
                                        @click="showOnlyFailedActivities = true"
                                        class="px-2 py-1 text-[11px] rounded border"
                                        :class="showOnlyFailedActivities
                                            ? 'bg-rose-600 text-white border-rose-600'
                                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                                        Failed ({{ failedRecentCount }})
                                    </button>
                                </div>
                                <div class="max-h-[360px] overflow-y-auto p-2 space-y-2">
                                    <a v-for="log in filteredActivityLogs" :key="log.id"
                                        :href="route('backend.activity-logs.show', log.id)"
                                        :class="[
                                            'block rounded border px-2 py-2 transition',
                                            isDark ? 'border-gray-700 hover:border-blue-600/20 hover:bg-slate-800' : 'border-gray-200 hover:border-blue-200 hover:bg-blue-50/40'
                                        ]">
                                        <div class="flex items-center justify-between gap-2">
                                            <span :class="['text-xs font-semibold truncate', isDark ? 'text-gray-100' : 'text-white']">{{ log.module }} - {{ log.action }}</span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded"
                                                :class="log.status === 'success'
                                                    ? (isDark ? 'bg-emerald-100 text-emerald-700' : 'bg-emerald-700 text-white')
                                                    : (isDark ? 'bg-rose-100 text-rose-700' : 'bg-rose-700 text-white')">
                                                {{ log.status }}
                                            </span>
                                        </div>
                                        <p :class="['text-[11px] mt-1 truncate', isDark ? 'text-gray-300' : 'text-white']">{{ log.description || 'No description' }}</p>
                                        <div :class="['text-[10px] mt-1', isDark ? 'text-gray-400' : 'text-white']">{{ log.user_name || 'System' }} | {{ log.created_at }}</div>
                                    </a>
                                    <div v-if="!filteredActivityLogs.length" class="text-xs text-gray-500 text-center py-4">
                                        {{ showOnlyFailedActivities ? 'No failed logs in recent activities.' : 'No activity logs available.' }}
                                    </div>
                                </div>
                                <div class="border-t border-gray-200 p-2">
                                    <a :href="route('backend.activity-logs.index')" class="block text-center text-xs font-semibold text-blue-600 hover:text-blue-700">
                                        Open Activity Logs
                                    </a>
                                </div>
                            </div>
                        </li>

                        <li class="relative" data-navbar-notification>
                            <button type="button" @click="toggleExpiryPanel"
                                :class="['cursor-pointer p-2 rounded transition-colors duration-200 relative', isDark ? 'text-gray-300 hover:text-blue-400 hover:bg-slate-800' : 'text-gray-500 hover:text-blue-600 hover:bg-gray-100']"
                                title="Medicine Expiry Alert">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                </svg>
                                <span v-if="totalMedicineAlerts > 0"
                                    class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] leading-[18px] text-center font-semibold">
                                    {{ totalMedicineAlerts }}
                                </span>
                            </button>

                            <div v-if="isExpiryPanelOpen"
                                class="absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded shadow-lg z-50">
                                <div class="px-3 py-2 border-b border-gray-200 text-xs font-semibold text-gray-700">
                                    Medicine Expiry Alert
                                </div>
                                <div class="p-3 space-y-2">
                                    <a :href="route('backend.medicineinventory.index', { expiry_filter: 'expired' })"
                                        class="flex items-center justify-between rounded bg-rose-50 border border-rose-200 px-2 py-1.5 hover:bg-rose-100 transition">
                                        <span class="text-xs text-rose-700">Expired</span>
                                        <span class="text-xs font-semibold text-rose-900">{{ medicineExpiryAlert.expired_count || 0 }}</span>
                                    </a>
                                    <a :href="route('backend.medicineinventory.index', { expiry_filter: 'expiring_soon' })"
                                        class="flex items-center justify-between rounded bg-amber-50 border border-amber-200 px-2 py-1.5 hover:bg-amber-100 transition">
                                        <span class="text-xs text-amber-700">Expiring in {{ medicineExpiryAlert.days_window || 30 }} days</span>
                                        <span class="text-xs font-semibold text-amber-900">{{ medicineExpiryAlert.expiring_soon_count || 0 }}</span>
                                    </a>
                                    <a :href="route('backend.medicineinventory.index', { expiry_filter: 'alerts' })"
                                        class="block text-center text-xs font-semibold text-violet-600 hover:text-violet-700 pt-1">
                                        Open All Alerts
                                    </a>
                                    <a :href="route('backend.medicineinventory.index')"
                                        class="block text-center text-xs font-semibold text-blue-600 hover:text-blue-700 pt-1">
                                        Open Medicine Inventory
                                    </a>
                                </div>
                            </div>
                        </li>

                        <li>
                            <div class="relative">
                                <div class="flex items-center text-gray-500 hover:text-blue-600 transition-colors duration-200 p-2 rounded cursor-pointer">
                                    <Dropdown align="right">
                                    <template #trigger>
                                        <div class="flex items-center gap-2">
                                            <div :class="['h-7 w-7 rounded-full overflow-hidden flex items-center justify-center', isDark ? 'border-gray-600 bg-slate-800' : 'border border-gray-300 bg-gray-100']">
                                                <img
                                                    v-if="adminPhoto"
                                                    :src="adminPhoto"
                                                    alt="Profile"
                                                    class="h-full w-full object-cover"
                                                >
                                                <span v-else :class="['text-[10px] font-semibold', isDark ? 'text-gray-200' : 'text-gray-600']">
                                                    {{ adminInitials }}
                                                </span>
                                            </div>
                                            <span :class="['hidden md:block max-w-[130px] truncate text-xs font-semibold', isDark ? 'text-white' : 'text-gray-700']">
                                                {{ adminDisplayName }}
                                            </span>
                                        </div>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('backend.profile.edit')">
                                            Profile
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <div class="px-3 py-2 text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                            Settings
                                        </div>

                                        <DropdownLink v-if="canOpenDashboardSetting" :href="route('backend.dashboard-setting.edit')">
                                            Dashboard Settings
                                        </DropdownLink>

                                        <DropdownLink v-if="canOpenReportSetting" :href="route('backend.report-setting.edit')">
                                            Report Settings
                                        </DropdownLink>

                                        <DropdownLink v-if="canOpenWebSetting" :href="route('backend.websetting.create')">
                                            CMS Setting
                                        </DropdownLink>

                                        <DropdownLink v-if="canOpenActivityLogs" :href="route('backend.activity-logs.index')">
                                            Activity Logs
                                        </DropdownLink>

                                        <DropdownLink v-if="canOpenActivityLogsPrint" :href="route('backend.activity-logs.print')" as="a" target="_blank" rel="noopener noreferrer">
                                            Activity Logs Print
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                                </div>
                            </div>
                        </li>

                        
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-blue-50 {
    background-color: var(--app-theme-soft) !important;
}

.bg-gray-50,
.bg-gray-100,
.bg-white {
    background-color: color-mix(in srgb, var(--app-theme-surface) 88%, #ffffff) !important;
}

.border-gray-200,
.border-gray-300 {
    border-color: color-mix(in srgb, var(--app-theme-primary) 20%, #cbd5e1) !important;
}

.text-gray-500,
.text-gray-600,
.text-gray-700,
.text-gray-800 {
    color: color-mix(in srgb, var(--app-theme-contrast) 70%, #334155) !important;
}

.text-blue-600,
.text-blue-700,
.text-violet-600,
.text-violet-700 {
    color: var(--app-theme-primary) !important;
}

.bg-blue-600 {
    background-color: var(--app-theme-primary) !important;
}

.border-blue-600,
.border-blue-200 {
    border-color: var(--app-theme-primary) !important;
}

.hover\:bg-blue-50\/40:hover {
    background-color: color-mix(in srgb, var(--app-theme-soft) 60%, white) !important;
}

.hover\:bg-gray-50:hover,
.hover\:bg-gray-100:hover {
    background-color: color-mix(in srgb, var(--app-theme-soft) 58%, white) !important;
}

.hover\:text-blue-600:hover,
.hover\:text-blue-700:hover,
.hover\:text-violet-700:hover {
    color: var(--app-theme-primary) !important;
}

.shadow-lg,
.shadow-md,
.shadow-sm {
    box-shadow:
        0 1px 2px rgb(var(--app-theme-primary-rgb) / 0.08),
        0 8px 22px rgb(var(--app-theme-primary-rgb) / 0.09) !important;
}
</style>