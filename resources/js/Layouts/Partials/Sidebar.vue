<script setup>
import { reactive, ref, onMounted, onBeforeUnmount, computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import SideBarSubMenu from "@/Components/SideBarSubMenu.vue";
import eventBus from "@/eventBus.js";

const page = usePage();
const screenWidth = ref(window.innerWidth);
const sideBar = ref(false);
const webSetting = computed(() => page.props.webSetting);

const handleResize = () => {
  screenWidth.value = window.innerWidth;
};

onMounted(() => {
  window.addEventListener("resize", handleResize);
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", handleResize);
});

eventBus.on("sidebarToggled", (flag) => {
  sideBar.value = flag;
});

const navSidebar = reactive([
  "flex items-center p-3 space-x-3 rounded-md cursor-pointer hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group",
]);

const hasRolePermission = (permissionName) => {
  const admin = page.props.auth?.admin;
  if (!admin || !admin.roles) return false;
  return admin.roles.some(role =>
    role.permissions?.some(permission =>
      permission.name === permissionName
    )
  );
};

const filteredMenus = computed(() => {
  return (page.props.auth?.sideMenus ?? []).map(menu => {
    const hasMainPermission = hasRolePermission(menu.permission_name);
    if (!hasMainPermission) return null;

    const filteredChildren = (menu.childrens ?? []).filter(child =>
      hasRolePermission(child.permission_name) && child.route
    );

    if (menu.route || filteredChildren.length > 0) {
      return {
        ...menu,
        childrens: filteredChildren,
      };
    }
    return null;
  }).filter(Boolean);
});

const getActiveRoute = (mainMenu) => {
  if (!mainMenu.childrens) return null;
  for (const childMenu of mainMenu.childrens) {
    if (route().current(childMenu.route)) {
      return childMenu.route;
    }
  }
  return null;
};

const sidebarClasses = computed(() => {
  const baseClasses = "bg-white text-gray-700 md:block relative border-r border-gray-200 shadow-sm";
  if (sideBar.value) {
    return `hidden w-[70px] ${baseClasses}`;
  } else {
    return `block w-[240px] ${baseClasses}`;
  }
});
</script>

<template>
  <div :class="sidebarClasses">
    <!-- Header -->
    <div class="w-full flex items-center h-[50px] border-b border-gray-200 bg-gray-100 px-4">
      <Link :href="route('backend.dashboard')"
        class="text-xl font-bold text-gray-800 hover:text-blue-600 transition-colors duration-200">
      {{ sideBar ? webSetting?.company_short_name : webSetting?.company_name || 'Company Name' }}
      <span v-if="!sideBar" class="block text-xs font-normal text-gray-500 mt-0.5"></span>
      </Link>
    </div>

    <!-- Navigation Menu -->
    <div style="width: inherit" class="h-[calc(100vh-60px)] overflow-y-auto bg-gray-100">
      <ul class="w-full px-3 py-4 space-y-1">
        <template v-for="(mainMenu, Index) in filteredMenus" :key="Index">
          <!-- Menu with Submenu -->
          <li v-if="mainMenu.childrens?.length > 0" :class="{ 'flex justify-center': sideBar }" class="relative">
            <SideBarSubMenu align="left" :activeRoute="getActiveRoute(mainMenu)">
              <template #trigger>
                <div :class="[
                  navSidebar,
                  getActiveRoute(mainMenu) ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500' : ''
                ]">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="mainMenu.icon" size="18" :class="[
                      'transition-colors duration-200',
                      getActiveRoute(mainMenu) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                    ]" />
                  </div>
                  <span v-if="!sideBar" class="truncate font-medium text-sm">{{ mainMenu.name }}</span>
                </div>
              </template>

              <template #content>
                <ul class="submenu bg-gray-100 border border-gray-200 rounded-md py-1">
                  <li v-for="(submenu, subIndex) in mainMenu.childrens" :key="subIndex">
                    <template v-if="submenu.route">
                      <Link :href="route(submenu.route)" :class="[
                        route().current(submenu.route)
                          ? 'bg-blue-50 text-blue-600 font-medium'
                          : 'text-gray-700 hover:bg-gray-50',
                        'flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-sm mx-1',
                        sideBar ? '' : 'ml-3',
                      ]">
                      <FeatherIcon :name="submenu.icon" size="16" class="text-gray-500" />
                      <span v-if="!sideBar" class="truncate text-sm">{{ submenu.name }}</span>
                      </Link>
                    </template>
                  </li>
                </ul>
              </template>
            </SideBarSubMenu>
          </li>

          <!-- Single Menu Item -->
          <li v-else :class="{ 'flex justify-center': sideBar }">
            <template v-if="mainMenu.route">
              <Link :href="route(mainMenu.route)" :class="[
                route().current(mainMenu.route)
                  ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500'
                  : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600',
                navSidebar,
              ]" class="w-full">
              <div class="flex items-center justify-center w-5 h-5">
                <FeatherIcon :name="mainMenu.icon" size="18" :class="[
                  'transition-colors duration-200',
                  route().current(mainMenu.route) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                ]" />
              </div>
              <span v-if="!sideBar" class="truncate text-sm">{{ mainMenu.name }}</span>
              </Link>
            </template>
          </li>
        </template>
      </ul>
    </div>
  </div>
</template>

<style scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #ecebeb;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: #989b9e;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #7e7e7f;
}

/* Submenu animations */
.submenu {
  animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Active state border */
.border-l-3 {
  border-left-width: 3px;
}
</style>