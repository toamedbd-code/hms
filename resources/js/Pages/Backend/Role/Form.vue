<script setup>
import { ref, computed, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['role', 'permissions', 'id']);
const page = usePage();

const form = useForm({
    name: props.role?.name ?? '',
    guard_name: props.role?.guard_name ?? 'admin',
    permission_ids: props.role?.permission_ids ?? [],
    _method: props.role?.id ? 'put' : 'post',
});


const submit = () => {
    const routeName = props.id ? route('backend.role.update', props.id) : route('backend.role.store');
    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {

        onSuccess: (response) => {
            if (!props.id)
                form.reset();
            displayResponse(response)
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
    const formatted = formatLabel(permission?.name ?? '').toLowerCase();
    return name.includes(query) || formatted.includes(query);
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
    return filterPermissionTree(props.permissions || [], query);
});

const menuPermissionOrder = computed(() => {
    const order = new Map();
    const sideMenus = page.props?.auth?.sideMenus ?? [];
    let index = 0;

    const pushPermission = (permissionName) => {
        const normalized = String(permissionName ?? '').trim().toLowerCase();
        if (!normalized || order.has(normalized)) {
            return;
        }
        order.set(normalized, index++);
    };

    sideMenus.forEach((menu) => {
        pushPermission(menu?.permission_name);
        (menu?.childrens ?? []).forEach((child) => {
            pushPermission(child?.permission_name);
        });
    });

    return order;
});

const initialGroupOrder = ref(new Map());

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

    // capture initial order of permission groups to keep UI stable
    try {
        const list = props.permissions || [];
        let idx = 0;
        list.forEach((p) => {
            const n = String(p?.name ?? '').trim().toLowerCase();
            if (n && !initialGroupOrder.value.has(n)) {
                initialGroupOrder.value.set(n, idx++);
            }
        });
    } catch (e) {
        // ignore
    }
});

const sortedPermissionGroups = computed(() => {
    const list = [...(filteredPermissions.value ?? [])];
    const order = menuPermissionOrder.value;

    return list.sort((a, b) => {
        const aName = String(a?.name ?? '').toLowerCase();
        const bName = String(b?.name ?? '').toLowerCase();

        const aInit = initialGroupOrder.value.has(aName) ? initialGroupOrder.value.get(aName) : null;
        const bInit = initialGroupOrder.value.has(bName) ? initialGroupOrder.value.get(bName) : null;

        // Prefer initial captured order if available (freeze)
        if (aInit !== null || bInit !== null) {
            if (aInit === null) return 1;
            if (bInit === null) return -1;
            if (aInit !== bInit) return aInit - bInit;
        }

        // Fallback to menu order
        const aMenu = order.has(aName) ? order.get(aName) : Number.MAX_SAFE_INTEGER;
        const bMenu = order.has(bName) ? order.get(bName) : Number.MAX_SAFE_INTEGER;
        if (aMenu !== bMenu) return aMenu - bMenu;

        return formatLabel(a?.name).localeCompare(formatLabel(b?.name));
    });
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
        if (node.id) ids.push(node.id);
        (node.child || []).forEach((child) => walk(child));
    };
    walk(permission);
    return ids;
}

function isAllInModuleChecked(permission) {
    const ids = collectPermissionIds(permission);
    if (!ids.length) return false;
    return ids.every((id) => checkedPermissions.value.includes(id));
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

function selectedCountInModule(permission) {
    const ids = collectPermissionIds(permission);
    if (!ids.length) return 0;
    return ids.filter((id) => checkedPermissions.value.includes(id)).length;
}

function totalCountInModule(permission) {
    return collectPermissionIds(permission).length;
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
                                    <p class="font-semibold text-sm">{{ formatLabel(permissionInfo.name) }}</p>
                                    <p class="text-xs mt-1 text-slate-500">{{ selectedCountInModule(permissionInfo) }}/{{ totalCountInModule(permissionInfo) }} selected</p>
                                </button>
                            </template>

                            <div v-if="!sortedPermissionGroups.length" class="text-xs text-slate-500 p-2">
                                No permission group found.
                            </div>
                        </div>

                        <div v-if="activePermissionGroup" class="rounded-md border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between gap-2 p-3 border-b border-slate-100">
                                <div>
                                    <p class="font-semibold text-sm text-slate-800">{{ formatLabel(activePermissionGroup.name) }}</p>
                                    <p class="text-xs text-slate-500 mt-1">{{ selectedCountInModule(activePermissionGroup) }}/{{ totalCountInModule(activePermissionGroup) }} selected</p>
                                </div>
                                <div class="flex items-center gap-2">
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
                                                {{ formatLabel(activePermissionGroup.name) }}
                                            </label>
                                        </div>

                                        <ul v-if="activePermissionGroup.child" class="ml-1 mt-2 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                                            <template v-for="childInfo in activePermissionGroup.child" :key="childInfo.id">
                                                <li class="rounded border border-slate-200 p-2 bg-slate-50">
                                                    <div class="flex items-center">
                                                        <input v-model="checkedPermissions" :value="childInfo.id"
                                                            type="checkbox" class="cursor-pointer"
                                                            :id="'permission_' + childInfo.id" />
                                                        <label :for="'permission_' + childInfo.id"
                                                            class="ml-2 cursor-pointer"
                                                            :class="checkedPermissions.includes(childInfo.id) ? 'text-green-600' : 'text-gray-700'">
                                                            {{ formatLabel(childInfo.name) }}
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
                                                                    {{ formatLabel(childChildInfo.name) }}
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
