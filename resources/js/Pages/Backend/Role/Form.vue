<script setup>
import { ref, computed, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import eventBus from '@/eventBus.js';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps([
    'role',
    'permissions',
    'id',
    'canManageAllPermissions',
    'isDeveloper',
    'dashboardCategoryPermissionNames',
]);
const page = usePage();

const form = useForm({
    name: props.role?.name ?? '',
    guard_name: props.role?.guard_name ?? 'admin',
    permission_ids: props.role?.permission_ids ?? [],
    _method: props.role?.id ? 'put' : 'post',
});


const submit = async () => {
    const routeName = props.id ? route('backend.role.update', props.id) : route('backend.role.store');
    form.transform((data) => {
        const requestedPermissionIds = Array.isArray(data.permission_ids)
            ? data.permission_ids.map((id) => String(id)).filter(Boolean)
            : [];

        const visibleSelectedIds = requestedPermissionIds.filter((id) => visiblePermissionIds.value.has(String(id)));
        const preservedHiddenIds = requestedPermissionIds.filter((id) => !visiblePermissionIds.value.has(String(id)));

        return {
            ...data,
            remember: '',
            isDirty: false,
            permission_ids: Array.from(new Set([...visibleSelectedIds, ...preservedHiddenIds])),
        };
    }).post(routeName, {

        onSuccess: async (response) => {
            if (!props.id)
                form.reset();
            displayResponse(response);

            // Proactively fetch side-menus and emit to Sidebar so the UI
            // updates immediately without requiring any Inertia reload.
            // Emit both the normal `sidebar.remoteUpdated` (for compatibility)
            // and `sidebar.remoteUpdatedForce` which the sidebar will apply
            // immediately regardless of timestamp ordering.
            try {
                const applySnapshot = (data) => {
                    try { eventBus.emit('sidebar.remoteUpdated', data); } catch (e) { /* ignore */ }
                    try { eventBus.emit('sidebar.remoteUpdatedForce', data); } catch (e) { /* ignore */ }
                };

                const fetchAndApply = (cb) => {
                    if (typeof window !== 'undefined' && window.axios && typeof window.axios.get === 'function') {
                        window.axios.get('/admin/side-menus')
                            .then((resp) => {
                                if (resp && resp.data) {
                                    applySnapshot(resp.data);
                                    if (typeof cb === 'function') cb(resp.data);
                                }
                            }).catch(() => { if (typeof cb === 'function') cb(null); });
                    } else if (typeof fetch === 'function') {
                        fetch('/admin/side-menus', { credentials: 'include' })
                            .then((r) => r.json())
                            .then((data) => {
                                if (data) {
                                    applySnapshot(data);
                                    if (typeof cb === 'function') cb(data);
                                } else if (typeof cb === 'function') cb(null);
                            })
                            .catch(() => { if (typeof cb === 'function') cb(null); });
                    } else if (typeof cb === 'function') {
                        cb(null);
                    }
                };

                // Fetch and apply immediately
                fetchAndApply((snapshot) => {
                    // Also re-apply after any Inertia navigation finishes so that
                    // the forced snapshot survives the Inertia visit that follows
                    // the form POST (redirects update shared props and remount
                    // the layout). Listen once and then remove the listener.
                    try {
                        const onInertiaFinish = () => {
                            try {
                                if (snapshot) applySnapshot(snapshot);
                                else fetchAndApply();
                            } catch (e) { /* ignore */ }
                            try { window.removeEventListener('inertia:finish', onInertiaFinish); } catch (e) { /* ignore */ }
                        };

                        try { window.addEventListener('inertia:finish', onInertiaFinish, { passive: true }); } catch (e) { /* ignore */ }
                        // Fallback: also re-apply after a short delay in case the
                        // Inertia event isn't fired in some environments.
                        setTimeout(() => {
                            try { if (snapshot) applySnapshot(snapshot); else fetchAndApply(); } catch (e) { /* ignore */ }
                        }, 500);
                    } catch (e) {
                        // ignore
                    }

                    // Final safety fallback: if the above snapshot apply did not
                    // update the sidebar (some environments may not fire the
                    // client-side events reliably), force Inertia to reload the
                    // shared `auth.sideMenus` prop so the sidebar updates.
                    try {
                        setTimeout(() => {
                            try {
                                router.reload({ only: ['auth'] });
                            } catch (err) {
                                try { router.reload(); } catch (err2) { /* ignore */ }
                            }
                        }, 900);
                    } catch (e) {
                        // ignore
                    }
                });
            } catch (e) {
                // ignore
            }
        },
        onError: (errorObject) => {

            displayWarning(errorObject);
        },
    });
};

const checkedPermissions = computed({
    get: () => form.permission_ids,
    set: (newValue) => form.permission_ids = newValue,
});

const permissionSearch = ref('');

function permissionMatches(permission, query) {
    const name = String(permission?.name ?? '').toLowerCase();
    const display = permissionDisplayLabel(permission).toLowerCase();
    return name.includes(query) || display.includes(query);
}

function filterPermissionTree(list, query) {
    if (!query) return list;

    return (list || [])
        .map((permission) => {
            const children = filterPermissionTree(permission?.child || [], query);
            if (permissionMatches(permission, query) || children.length) {
                return {
                    ...permission,
                    child: children,
                };
            }
            return null;
        })
        .filter(Boolean);
}

const filteredPermissions = computed(() => {
    const query = permissionSearch.value.trim().toLowerCase();
    return [...filterPermissionTree(props.permissions || [], query)]
        .sort((a, b) => {
            if (a.name === 'dashboard' && b.name !== 'dashboard') return -1;
            if (b.name === 'dashboard' && a.name !== 'dashboard') return 1;
            return 0;
        });
});

const visiblePermissionIds = computed(() => {
    const ids = new Set();
    const walk = (nodes) => {
        (nodes || []).forEach((permission) => {
            permissionIdsForNode(permission).forEach((id) => ids.add(String(id)));
            if (permission?.child && Array.isArray(permission.child)) {
                walk(permission.child);
            }
        });
    };
    walk(filteredPermissions.value);
    return ids;
});

const menuPermissionLabelMap = computed(() => {
    const labels = new Map();
    const sideMenus = page.props?.auth?.sideMenus ?? [];

    const pushLabel = (permissionName, menuName) => {
        const normalized = String(permissionName ?? '').trim().toLowerCase();
        const label = String(menuName ?? '').trim();
        if (!normalized || !label || labels.has(normalized)) {
            return;
        }
        labels.set(normalized, label);
    };

    sideMenus.forEach((menu) => {
        pushLabel(menu?.permission_name, menu?.name);
        (menu?.childrens ?? []).forEach((child) => {
            pushLabel(child?.permission_name, child?.name);
        });
    });

    return labels;
});

function permissionDisplayLabel(permission) {
    const name = String(permission?.name ?? '').trim();
    const normalized = name.toLowerCase();
    if (normalized === 'billing') {
        return 'Module Billing';
    }
    if (normalized === 'prescription-management') {
        return 'Prescription Management';
    }

    const billingModuleLabels = {
        'billing-module-billing': 'Billing',
        'billing-module-appointment': 'Appointment',
        'billing-module-opd': 'OPD',
        'billing-module-ipd': 'IPD',
        'billing-module-prescription': 'Prescription',
        'billing-module-ambulance': 'Ambulance',
        'billing-module-billing-report': 'Billing Report',
        'billing-module-bed-management': 'Bed Management',
        'billing-module-pathology': 'Pathology',
        'billing-module-radiology': 'Radiology',
        'billing-module-blood-issue': 'Blood Issue',
        'billing-module-pharmacy': 'Pharmacy',
    };
    if (billingModuleLabels[normalized]) {
        return billingModuleLabels[normalized];
    }

    const dashboardCardLabels = {
        'dashboard-card-opd-income': 'OPD Income',
        'dashboard-card-doctor-visit-appointment': 'Doctor-wise Visit Appointment',
        'dashboard-card-ipd-income': 'IPD Income',
        'dashboard-card-pharmacy-income': 'Pharmacy Income',
        'dashboard-card-pathology-income': 'Pathology Income',
        'dashboard-card-radiology-income': 'Radiology Income',
        'dashboard-card-disposable-income': 'Disposable Income',
        'dashboard-card-blood-bank-income': 'Blood Bank Income',
        'dashboard-card-expenses': 'Expenses',
        'dashboard-card-pending-income': 'Pending Income',
        'dashboard-card-referral-commission': 'Referral Commission',
        'dashboard-card-net-income': 'Net Income',
        'dashboard-card-total-income': 'Total Income',
        'dashboard-card-refunds': 'Refunds',
        'dashboard-card-total-discount': 'Total Discount',
        'dashboard-card-expired-medicines': 'Expired Medicines',
        'dashboard-card-expiring-medicines': 'Expiring Medicines',
        'dashboard-card-product-returns': 'Product Returns',
        'dashboard-chart-income-by-department': 'Income by Department (Bar Chart)',
        'dashboard-chart-income-distribution': 'Income Distribution (Pie Chart)',
    };
    if (dashboardCardLabels[normalized]) {
        return dashboardCardLabels[normalized];
    }

    const prescriptionPermissionLabels = {
        'prescription-list': 'Prescription List',
        'prescription-add': 'Prescription Add',
        'prescription-edit': 'Prescription Edit',
        'prescription-delete': 'Prescription Delete',
        'prescription-print': 'Prescription Print',
        'prescription-list-create': 'Prescription Add (Legacy)',
        'prescription-list-edit': 'Prescription Edit (Legacy)',
        'prescription-list-delete': 'Prescription Delete (Legacy)',
        'prescription-settings': 'Prescription Settings',
    };
    if (prescriptionPermissionLabels[normalized]) {
        return prescriptionPermissionLabels[normalized];
    }

    if (normalized.startsWith('dashboard-card-') && normalized.endsWith('-income')) {
        const category = name.slice('dashboard-card-'.length, -'-income'.length);
        return `${formatLabel(category)} Income`;
    }

    if (normalized.startsWith('dashboard-main-category-') && normalized.endsWith('-income')) {
        const category = name.slice('dashboard-main-category-'.length, -'-income'.length);
        return `Main Category - ${formatLabel(category)} Income`;
    }

    const menuLabel = menuPermissionLabelMap.value.get(normalized);
    return menuLabel || formatLabel(name);
}

const sortedPermissionGroups = computed(() => {
    return filteredPermissions.value ?? [];
});

const activePermissionGroupId = ref(null);

const activePermissionGroup = computed(() => {
    const groups = sortedPermissionGroups.value ?? [];
    if (!groups.length) {
        return null;
    }

    const matched = groups.find((item) => Number(item.id) === Number(activePermissionGroupId.value));
    return matched ?? groups[0];
});

const dashboardCategoryPermissions = computed(() => {
    const dashboard = (props.permissions || []).find((permission) => permission?.name === 'dashboard');
    if (!dashboard) return [];

    const categoryPermissionNames = new Set(props.dashboardCategoryPermissionNames || []);
    return (dashboard.child || []).filter((permission) => {
        const name = String(permission?.name ?? '').toLowerCase();
        return categoryPermissionNames.has(name);
    });
});

const dashboardMainCategoryPermissions = computed(() => {
    const dashboard = (props.permissions || []).find((permission) => permission?.name === 'dashboard');
    return (dashboard?.child || []).filter((permission) =>
        /^dashboard-main-category-.+-income$/i.test(String(permission?.name ?? ''))
    );
});

const defaultDashboardPermissions = computed(() => {
    if (activePermissionGroup.value?.name !== 'dashboard') {
        return [];
    }

    const fixedIncomePermissions = new Set([
        'dashboard-card-opd-income',
        'dashboard-card-doctor-visit-appointment',
        'dashboard-card-ipd-income',
        'dashboard-card-pharmacy-income',
        'dashboard-card-pathology-income',
        'dashboard-card-radiology-income',
        'dashboard-card-disposable-income',
        'dashboard-card-blood-bank-income',
        'dashboard-card-expenses',
        'dashboard-card-pending-income',
        'dashboard-card-referral-commission',
        'dashboard-card-net-income',
        'dashboard-card-total-income',
        'dashboard-card-refunds',
        'dashboard-card-total-discount',
        'dashboard-card-expired-medicines',
        'dashboard-card-expiring-medicines',
        'dashboard-card-product-returns',
    ]);

    return (activePermissionGroup.value.child || []).filter((permission) => {
        const name = String(permission?.name ?? '').toLowerCase();
        const isCategoryPermission = /^dashboard-card-.+-income$/.test(name)
            && !fixedIncomePermissions.has(name);
        const isMainCategoryPermission = /^dashboard-main-category-.+-income$/.test(name);
        return !isCategoryPermission && !isMainCategoryPermission;
    });
});

const setActivePermissionGroup = (permissionId) => {
    activePermissionGroupId.value = permissionId;
    try {
        const key = `role_active_permission_group_${props.id ?? 'new'}`;
        localStorage.setItem(key, String(permissionId));
    } catch (e) {
        // ignore localStorage errors
    }
};

onMounted(() => {
    try {
        const key = `role_active_permission_group_${props.id ?? 'new'}`;
        const v = localStorage.getItem(key);
        if (v) {
            activePermissionGroupId.value = Number(v);
        }
    } catch (e) {
        // ignore
    }
});

function collectPermissionIds(permission) {
    const ids = [];
    const walk = (node) => {
        if (!node) return;
        ids.push(...permissionIdsForNode(node));
        (node.child || []).forEach((child) => walk(child));
    };
    walk(permission);
    return Array.from(new Set(ids));
}

function permissionIdsForNode(permission) {
    const ids = Array.isArray(permission?.assignable_permission_ids)
        ? permission.assignable_permission_ids
        : [permission?.id];
    return ids
        .filter((id) => id !== undefined && id !== null && id !== '')
        .map((id) => Number(id))
        .filter(Number.isInteger);
}

function isPermissionChecked(permission) {
    const ids = permissionIdsForNode(permission);
    return ids.length > 0 && ids.every((id) => checkedPermissions.value.some((selected) => Number(selected) === id));
}

function isPermissionPartiallyChecked(permission) {
    const ids = permissionIdsForNode(permission);
    const selectedCount = ids.filter((id) => checkedPermissions.value.some((selected) => Number(selected) === id)).length;
    return selectedCount > 0 && selectedCount < ids.length;
}

function setPermissionChecked(permission, isChecked) {
    const current = new Set(checkedPermissions.value.map((id) => Number(id)));
    permissionIdsForNode(permission).forEach((id) => {
        if (isChecked) {
            current.add(id);
        } else {
            current.delete(id);
        }
    });
    checkedPermissions.value = Array.from(current);
}

function collectPermissionNodes(permission) {
    const nodes = [];
    const walk = (node) => {
        if (!node) return;
        nodes.push(node);
        (node.child || []).forEach((child) => walk(child));
    };
    walk(permission);
    return nodes;
}

function isAllInModuleChecked(permission) {
    const nodes = collectPermissionNodes(permission);
    return nodes.length > 0 && nodes.every(isPermissionChecked);
}

function toggleAllInModule(permission) {
    const ids = collectPermissionIds(permission);
    if (!ids.length) return;

    const current = new Set(checkedPermissions.value);
    const shouldSelect = !isAllInModuleChecked(permission);

    ids.forEach((id) => {
        if (shouldSelect) {
            current.add(id);
        } else {
            current.delete(id);
        }
    });

    checkedPermissions.value = Array.from(current);
}

function isAllPermissionsChecked(permissions) {
    return permissions.length > 0 && permissions.every(isPermissionChecked);
}

function togglePermissions(permissions) {
    if (!permissions.length) return;

    const shouldSelect = !isAllPermissionsChecked(permissions);
    permissions.forEach((permission) => setPermissionChecked(permission, shouldSelect));
}

function selectDefaultDashboardPermissions() {
    if (activePermissionGroup.value?.name !== 'dashboard') return;

    const dashboardGroup = (props.permissions || []).find((permission) => permission?.name === 'dashboard');
    const dashboardPermissionIds = new Set(collectPermissionIds(dashboardGroup || activePermissionGroup.value));
    const selectedIds = new Set(
        checkedPermissions.value
            .map((id) => Number(id))
            .filter((id) => !dashboardPermissionIds.has(id))
    );

    permissionIdsForNode(activePermissionGroup.value).forEach((id) => selectedIds.add(id));
    defaultDashboardPermissions.value.forEach((permission) => {
        permissionIdsForNode(permission).forEach((id) => selectedIds.add(id));
    });

    checkedPermissions.value = Array.from(selectedIds);
}

function selectedCountInModule(permission) {
    return collectPermissionNodes(permission).filter(isPermissionChecked).length;
}

function totalCountInModule(permission) {
    return collectPermissionNodes(permission).length;
}

function formatLabel(label) {
    if (!label) return '';
    return label
        .replace(/[-_ ]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

const goToRoleList = () => {
    router.visit(route('backend.role.index'));
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    router.visit(route('backend.role.index'));
};

</script>

<template>
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md ">

            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goBack"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gray-500 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gray-600">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back
                        </button>

                        <button @click="goToRoleList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Role List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <!-- <AlertMessage /> -->
                <!-- <pre>{{ form }}</pre> -->
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <InputLabel for="name" value="Role Edit | Role Name | Permissions" />
                        <input id="name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.name" type="text" placeholder="Role Name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div class="w-full lg:max-w-md">
                        <InputLabel for="permission_search" value="Permission Search" class="text-black" />
                        <input
                            id="permission_search"
                            v-model="permissionSearch"
                            type="text"
                            placeholder="Search permission (e.g. doctor portal, website inbox)"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                        />
                    </div>
                </div>

                <div class="w-full mt-4">
                    <div class="text-xs text-slate-500 mb-3">
                        Module-wise permission list: module এ ক্লিক করলে permission গুলো open হবে।
                    </div>
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-2 max-h-[540px] overflow-y-auto">
                            <template v-for="permissionInfo in sortedPermissionGroups" :key="permissionInfo.id">
                                <button
                                    type="button"
                                    class="w-full text-left rounded-md border px-3 py-2 mb-2 transition"
                                    :class="Number(activePermissionGroup?.id) === Number(permissionInfo.id)
                                        ? 'border-blue-300 bg-blue-50 text-blue-800'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100'"
                                    @click="setActivePermissionGroup(permissionInfo.id)"
                                >
                                    <p class="font-semibold text-sm">{{ permissionDisplayLabel(permissionInfo) }}</p>
                                    <p :class="Number(activePermissionGroup?.id) === Number(permissionInfo.id)
                                        ? 'text-white text-xs mt-1'
                                        : 'text-slate-500 text-xs mt-1'">
                                        {{ selectedCountInModule(permissionInfo) }}/{{ totalCountInModule(permissionInfo) }} selected
                                    </p>
                                </button>
                            </template>

                            <div v-if="!sortedPermissionGroups.length" class="text-xs text-slate-500 p-2">
                                No permission group found.
                            </div>
                        </div>

                        <div v-if="activePermissionGroup" class="rounded-md border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between gap-2 p-3 border-b border-slate-100">
                                <div>
                                    <p class="font-semibold text-sm text-slate-800">{{ permissionDisplayLabel(activePermissionGroup) }}</p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ selectedCountInModule(activePermissionGroup) }}/{{ totalCountInModule(activePermissionGroup) }} selected
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1">
                                    <template v-if="activePermissionGroup.name === 'dashboard'">
                                        <template v-if="props.isDeveloper">
                                            <button
                                                v-if="dashboardCategoryPermissions.length"
                                                type="button"
                                                class="whitespace-nowrap text-left text-xs font-semibold text-slate-700 hover:text-blue-700"
                                                @click="togglePermissions(dashboardCategoryPermissions)"
                                            >
                                                {{ isAllPermissionsChecked(dashboardCategoryPermissions) ? 'Unselect All Categories' : 'Select All Categories' }}
                                            </button>
                                            <button
                                                v-if="dashboardMainCategoryPermissions.length"
                                                type="button"
                                                class="whitespace-nowrap text-left text-xs font-semibold text-slate-700 hover:text-blue-700"
                                                @click="togglePermissions(dashboardMainCategoryPermissions)"
                                            >
                                                {{ isAllPermissionsChecked(dashboardMainCategoryPermissions) ? 'Unselect All Main Categories' : 'Select All Main Categories' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="whitespace-nowrap text-left text-xs font-semibold text-slate-700 hover:text-blue-700"
                                                @click="selectDefaultDashboardPermissions"
                                            >
                                                Select Default Cards
                                            </button>
                                        </template>
                                    </template>
                                    <button
                                        type="button"
                                        class="text-left font-semibold text-sm text-slate-800 hover:text-blue-700"
                                        @click="toggleAllInModule(activePermissionGroup)"
                                    >
                                        {{ isAllInModuleChecked(activePermissionGroup) ? 'Unselect All' : 'Select All' }}
                                    </button>
                                </div>
                            </div>
                            <div class="p-3">
                                <ul class="space-y-2">
                                    <li>
                                        <div class="flex items-center">
                                            <input v-model="checkedPermissions" :value="activePermissionGroup.id" type="checkbox"
                                                class="cursor-pointer" :id="'permission_' + activePermissionGroup.id" />
                                            <label :for="'permission_' + activePermissionGroup.id"
                                                class="ml-2 cursor-pointer font-bold"
                                                :class="checkedPermissions.includes(activePermissionGroup.id) ? 'text-green-600' : 'text-gray-700'">
                                                {{ permissionDisplayLabel(activePermissionGroup) }}
                                            </label>
                                        </div>

                                        <ul v-if="activePermissionGroup.child" class="ml-1 mt-2 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                                            <template v-for="childInfo in activePermissionGroup.child" :key="childInfo.id">
                                                <li class="rounded border border-slate-200 p-2 bg-slate-50">
                                                    <div class="flex items-center">
                                                        <input
                                                            :checked="isPermissionChecked(childInfo)"
                                                            :indeterminate="isPermissionPartiallyChecked(childInfo)"
                                                            @change="setPermissionChecked(childInfo, $event.target.checked)"
                                                            type="checkbox" class="cursor-pointer"
                                                            :id="'permission_' + childInfo.id" />
                                                        <label :for="'permission_' + childInfo.id"
                                                            class="ml-2 cursor-pointer"
                                                            :class="isPermissionChecked(childInfo) ? 'text-green-600' : 'text-gray-700'">
                                                            {{ permissionDisplayLabel(childInfo) }}
                                                        </label>
                                                    </div>

                                                    <ul v-if="childInfo.child" class="ml-4 mt-1 grid grid-cols-1 gap-1">
                                                        <template v-for="childChildInfo in childInfo.child"
                                                            :key="childChildInfo.id">
                                                            <li class="flex items-center">
                                                                <input v-model="checkedPermissions"
                                                                    :value="childChildInfo.id" type="checkbox"
                                                                    class="cursor-pointer"
                                                                    :id="'permission_' + childChildInfo.id" />
                                                                <label :for="'permission_' + childChildInfo.id"
                                                                    class="ml-2 cursor-pointer"
                                                                    :class="checkedPermissions.includes(childChildInfo.id) ? 'text-green-600' : 'text-gray-700'">
                                                                    {{ permissionDisplayLabel(childChildInfo) }}
                                                                </label>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                </li>
                                            </template>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div v-else class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
                            Permission group select করুন।
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Create') }}
                    </PrimaryButton>
                </div>
            </form>

        </div>
    </BackendLayout>
</template>
