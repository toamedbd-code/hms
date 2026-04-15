<script setup>
import { reactive, ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import SideBarSubMenu from "@/Components/SideBarSubMenu.vue";
import eventBus from "@/eventBus.js";

const page = usePage();
const screenWidth = ref(window.innerWidth);
const sideBar = ref(false);
const webSetting = computed(() => page.props.webSetting);
const sidebarScrollContainer = ref(null);
const lastClickedRoute = ref(null);

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

const integrationModules = computed(() => {
  const defaults = {
    fingerprint: true,
    face_attendance: true,
    leave: true,
    duty_roster: true,
    salary_sheet: true,
  };

  const rawOptions = webSetting.value?.attendance_device_options;
  if (!rawOptions) {
    return defaults;
  }

  try {
    const parsed = typeof rawOptions === 'string' ? JSON.parse(rawOptions) : rawOptions;
    return {
      ...defaults,
      ...(parsed?.modules ?? {}),
    };
  } catch (error) {
    return defaults;
  }
});

const routeAliasMap = {
  'backend.pharmacy.supplier.payment': 'backend.supplierpayment.index',
  'backend.pharmacy.return.index': 'backend.productreturn.index',
  'activity-logs.index': 'backend.activity-logs.index',
  'activity-logs.print': 'backend.activity-logs.print',
  'pathology-machine-logs.index': 'backend.pathology-machine-logs.index',
  'admin.attendance.devices': 'backend.attendance.devices',
};

const menuLabelOverrides = {
  'backend.productreturn.index': 'Supplier Product Return',
};

const fullReloadRoutes = [
  'backend.attendance.face',
  'backend.attendance.face.register',
  // Force full page reload for bKash admin settings to avoid SPA/modal behavior
  'backend.settings.payment.bkash',
];

const isFullReloadRoute = (name) => fullReloadRoutes.includes(name);

const normalizeRouteName = (name) => routeAliasMap[name] ?? name;

const getMenuDisplayName = (menuItem) => {
  const normalizedRoute = parseRouteDescriptor(menuItem?.route ?? '').name;
  return menuLabelOverrides[normalizedRoute] ?? menuItem?.name;
};

const parseRouteDescriptor = (routeValue) => {
  const rawRoute = String(routeValue ?? '').trim();
  if (!rawRoute) {
    return { name: '', params: {}, section: '', module: '' };
  }

  const [rawName, rawQuery = ''] = rawRoute.split('?');
  const name = normalizeRouteName(rawName);
  const params = {};

  if (rawQuery) {
    const searchParams = new URLSearchParams(rawQuery);
    searchParams.forEach((value, key) => {
      if (key) {
        params[key] = value;
      }
    });
  }

  return {
    name,
    params,
    section: String(params.section ?? '').trim().toLowerCase(),
    module: String(params.module ?? '').trim().toLowerCase(),
  };
};

const currentSection = computed(() => {
  try {
    const currentUrl = String(page.url ?? '');
    const queryString = currentUrl.includes('?') ? currentUrl.split('?')[1] : '';
    const searchParams = new URLSearchParams(queryString);
    return String(searchParams.get('section') ?? '').trim().toLowerCase();
  } catch (error) {
    return '';
  }
});

const currentModule = computed(() => {
  try {
    const currentUrl = String(page.url ?? '');
    const queryString = currentUrl.includes('?') ? currentUrl.split('?')[1] : '';
    const searchParams = new URLSearchParams(queryString);
    return String(searchParams.get('module') ?? '').trim().toLowerCase();
  } catch (error) {
    return '';
  }
});

const hasRoute = (name) => {
  const descriptor = parseRouteDescriptor(name);
  if (!descriptor.name) return false;
  try {
    const router = route();
    if (typeof router?.has === 'function') {
      return router.has(descriptor.name);
    }

    // Fallback for Ziggy versions without route().has
    route(descriptor.name);
    return true;
  } catch (error) {
    return false;
  }
};

const getMenuHref = (name) => {
  const descriptor = parseRouteDescriptor(name);
  if (!hasRoute(descriptor.name)) return null;
  try {
    return route(descriptor.name, descriptor.params);
  } catch (error) {
    return null;
  }
};

const isRouteActive = (name) => {
  const descriptor = parseRouteDescriptor(name);
  if (!hasRoute(descriptor.name)) return false;
  try {
    const currentMatched = route().current(descriptor.name);
    if (!currentMatched) {
      return false;
    }

    if (descriptor.section && currentSection.value !== descriptor.section) {
      return false;
    }

    if (descriptor.module) {
      return currentModule.value === descriptor.module;
    }

    return true;
  } catch (error) {
    return false;
  }
};

const allowedMenuRoutes = computed(() => {
  const routes = new Set();
  const menus = page.props.auth?.sideMenus ?? [];

  menus.forEach((menu) => {
    if (menu?.route) {
      routes.add(parseRouteDescriptor(menu.route).name);
    }

    (menu?.childrens ?? []).forEach((child) => {
      if (child?.route) {
        routes.add(parseRouteDescriptor(child.route).name);
      }
    });
  });

  return routes;
});

const canAccessMenuRoute = (name) => {
  const descriptor = parseRouteDescriptor(name);
  return allowedMenuRoutes.value.has(descriptor.name);
};

const canAccessAnyMenuRoute = (routeNames = []) => {
  return routeNames.some((routeName) => canAccessMenuRoute(routeName));
};

const userPermissions = computed(() => {
  const raw = page.props.auth?.permissions ?? [];
  return Array.isArray(raw) ? raw : [];
});

const hasPermission = (permissionName) => userPermissions.value.includes(permissionName);
const canManageAllWebSettings = computed(() => hasPermission('websetting-add'));
const canManageCmsSettings = computed(() => canManageAllWebSettings.value || hasPermission('cms-setting'));
const canManageGeneralSettings = computed(() => canManageAllWebSettings.value || hasPermission('general-setting-add'));
const canShowWebSettingModuleSubmenus = computed(() => (
  canManageAllWebSettings.value
  && hasRoute('backend.websetting.section.module')
  && (canAccessMenuRoute('backend.websetting.section.module') || canAccessMenuRoute('backend.websetting.create'))
));

const webSettingModuleSubmenus = [
  {
    name: 'Attendance Module Setting',
    icon: 'check-square',
    route: 'backend.websetting.section.module?section=module&module=attendance',
    requiredPermission: 'attendance-settings',
  },
  {
    name: 'Machine Integration Setting',
    icon: 'activity',
    route: 'backend.websetting.section.module?section=module&module=pathology',
    requiredPermission: 'machine-integration-setting',
  },
  {
    name: 'Payroll Module Setting',
    icon: 'dollar-sign',
    route: 'backend.websetting.section.module?section=module&module=payroll',
    requiredPermission: 'payroll-management',
  },
  {
    name: 'Reporting Module Setting',
    icon: 'bar-chart-2',
    route: 'backend.websetting.section.module?section=module&module=reporting',
    requiredPermission: 'report-settings',
  },
];

const canOpenFaceAttendance = computed(() => (
  integrationModules.value.face_attendance
  && hasPermission('face-attendance')
  && canAccessMenuRoute('backend.attendance.face')
  && hasRoute('backend.attendance.face')
  && Boolean(getMenuHref('backend.attendance.face'))
));

const canOpenAttendanceSettings = computed(() => (
  (integrationModules.value.face_attendance || integrationModules.value.fingerprint)
  && hasPermission('attendance-settings')
  && canAccessMenuRoute('backend.attendance.devices')
  && hasRoute('backend.attendance.devices')
  && Boolean(getMenuHref('backend.attendance.devices'))
));

const canOpenLeaveRequests = computed(() => (
  integrationModules.value.leave
  && hasPermission('leave-type-list')
  && canAccessMenuRoute('backend.pending.request')
  && hasRoute('backend.pending.request')
  && Boolean(getMenuHref('backend.pending.request'))
));

const canOpenDutyRoster = computed(() => (
  integrationModules.value.duty_roster
  && hasPermission('dutyroaster-list')
  && canAccessMenuRoute('backend.staffattendance.duty-roster')
  && hasRoute('backend.staffattendance.duty-roster')
  && Boolean(getMenuHref('backend.staffattendance.duty-roster'))
));

const canOpenSalarySheet = computed(() => (
  integrationModules.value.salary_sheet
  && hasPermission('staff-attendance-list')
  && canAccessMenuRoute('backend.staffattendance.salary-sheet')
  && hasRoute('backend.staffattendance.salary-sheet')
  && Boolean(getMenuHref('backend.staffattendance.salary-sheet'))
));

const canOpenCmsEdit = computed(() => (
  (canManageCmsSettings.value || canManageGeneralSettings.value)
  && canAccessMenuRoute('backend.websetting.create')
  && hasRoute('backend.websetting.create')
  && Boolean(getMenuHref('backend.websetting.create'))
));

const webSettingQuickLinkLabel = computed(() => {
  return 'CMS Setting';
});

const canOpenWebsiteInbox = computed(() => (
  hasPermission('website-inbox')
  && canAccessMenuRoute('backend.appoinment.website-inbox')
  && hasRoute('backend.appoinment.website-inbox')
  && Boolean(getMenuHref('backend.appoinment.website-inbox'))
));

const canOpenDoctorPortal = computed(() => (
  hasPermission('doctor-portal')
  && canAccessMenuRoute('backend.doctor.portal.opd')
  &&
  hasRoute('backend.doctor.portal.opd')
  && Boolean(getMenuHref('backend.doctor.portal.opd'))
));

const quickLinks = computed(() => {
  const links = [];

  if (canOpenFaceAttendance.value) {
    links.push({
      route: 'backend.attendance.face',
      icon: 'camera',
      label: 'Face Attendance',
    });
  }

  if (canOpenAttendanceSettings.value) {
    links.push({
      route: 'backend.attendance.devices',
      icon: 'settings',
      label: 'Attendance Settings',
    });
  }

  if (canOpenLeaveRequests.value) {
    links.push({
      route: 'backend.pending.request',
      icon: 'briefcase',
      label: 'Leave Requests',
    });
  }

  if (canOpenDutyRoster.value) {
    links.push({
      route: 'backend.staffattendance.duty-roster',
      icon: 'calendar',
      label: 'Duty Roster',
    });
  }

  if (canOpenSalarySheet.value) {
    links.push({
      route: 'backend.staffattendance.salary-sheet',
      icon: 'dollar-sign',
      label: 'Salary Sheet',
    });
  }

  if (canOpenCmsEdit.value) {
    links.push({
      route: 'backend.websetting.create',
      icon: 'edit-3',
      label: webSettingQuickLinkLabel.value,
    });
  }

  if (canOpenWebsiteInbox.value) {
    links.push({
      route: 'backend.appoinment.website-inbox',
      icon: 'inbox',
      label: 'Website Inbox',
    });
  }

  if (canOpenDoctorPortal.value) {
    links.push({
      route: 'backend.doctor.portal.opd',
      icon: 'briefcase',
      label: 'Doctor Portal',
    });
  }

  return links;
});

const showHrHub = computed(() => quickLinks.value.length > 0);

const quickAccessRoutes = new Set([
  'backend.attendance.face',
  'backend.attendance.devices',
  'backend.pending.request',
  'backend.staffattendance.duty-roster',
  'backend.staffattendance.salary-sheet',
  'backend.appoinment.website-inbox',
  'backend.doctor.portal.opd',
]);

const blockedMenuNames = new Set([
  'পার্সেস প্রোডাক্ট',
  'পারছেস প্রোডাক্ট',
  'পারচেস প্রোডাক্ট',
  'প্রোডাক্ট ডেলিভারি',
  'product delivery',
  'product add',
]);

const normalizeMenuName = (name) => String(name ?? '').trim().toLowerCase();

const isBlockedMenuName = (name) => {
  const normalized = normalizeMenuName(name);
  return blockedMenuNames.has(normalized) || blockedMenuNames.has(String(name ?? '').trim());
};

const childUniqueKey = (child) => {
  const normalizedName = normalizeMenuName(child?.name);
  if (normalizedName === 'supplier payment') {
    return 'name:supplier-payment';
  }

  const descriptor = parseRouteDescriptor(child?.route ?? '');
  if (descriptor.name) {
    const routeScope = [
      descriptor.section ? `section:${descriptor.section}` : '',
      descriptor.module ? `module:${descriptor.module}` : '',
    ].filter(Boolean).join('|');
    return routeScope ? `route:${descriptor.name}|${routeScope}` : `route:${descriptor.name}`;
  }

  return `name:${normalizedName}`;
};

const isWebSettingMenu = (menu, children = []) => {
  const menuRouteName = parseRouteDescriptor(menu?.route ?? '').name;
  if (menuRouteName.startsWith('backend.websetting.')) {
    return true;
  }

  return children.some((child) => parseRouteDescriptor(child?.route ?? '').name.startsWith('backend.websetting.'));
};

const scrollSelectedMenuIntoView = (event) => {
  const container = sidebarScrollContainer.value;
  const target = event?.currentTarget?.closest('li') ?? event?.target?.closest('li');

  if (!container || !target) return;

  const containerRect = container.getBoundingClientRect();
  const targetRect = target.getBoundingClientRect();
  const targetTop = targetRect.top - containerRect.top + container.scrollTop - 8;

  container.scrollTo({
    top: Math.max(targetTop, 0),
    behavior: 'smooth',
  });
};

const scrollRouteToTop = (routeName, behavior = 'smooth') => {
  const container = sidebarScrollContainer.value;
  if (!container || !routeName) return;

  const safeRouteName = String(routeName).replace(/"/g, '\\"');
  const target = container.querySelector(`[data-menu-route="${safeRouteName}"]`);
  if (!target) return;

  const li = target.closest('li') ?? target;
  const containerRect = container.getBoundingClientRect();
  const targetRect = li.getBoundingClientRect();
  const targetTop = targetRect.top - containerRect.top + container.scrollTop - 8;

  container.scrollTo({
    top: Math.max(targetTop, 0),
    behavior,
  });
};

const handleMenuClick = (event, routeName = null) => {
  if (routeName) {
    lastClickedRoute.value = parseRouteDescriptor(routeName).name;
  }
  scrollSelectedMenuIntoView(event);
};

const currentRouteName = computed(() => {
  try {
    const current = route().current();
    return current ? parseRouteDescriptor(current).name : null;
  } catch (error) {
    return null;
  }
});

watch(currentRouteName, async (newRoute) => {
  if (!newRoute) return;

  await nextTick();
  const targetRoute = lastClickedRoute.value ?? newRoute;
  scrollRouteToTop(targetRoute, 'smooth');
}, { immediate: true });

const filteredMenus = computed(() => {
  return (page.props.auth?.sideMenus ?? []).map(menu => {
    if (isBlockedMenuName(menu?.name)) {
      return null;
    }

    const filteredChildren = (menu.childrens ?? []).filter(child => {
      if (isBlockedMenuName(child?.name)) {
        return false;
      }

      if (!child?.route || !hasRoute(child.route)) {
        return false;
      }

      if (!canAccessMenuRoute(child.route)) {
        return false;
      }

      const normalizedChildRoute = parseRouteDescriptor(child.route).name;
      return !quickAccessRoutes.has(normalizedChildRoute);
    }).filter((child, index, list) => list.findIndex((item) => childUniqueKey(item) === childUniqueKey(child)) === index);

    const eligibleModuleSubmenus = webSettingModuleSubmenus.filter((submenu) => {
      return !submenu.requiredPermission || hasPermission(submenu.requiredPermission);
    });

    const enrichedChildren = isWebSettingMenu(menu, filteredChildren) && canShowWebSettingModuleSubmenus.value
      ? [...filteredChildren, ...eligibleModuleSubmenus]
      : filteredChildren;

    const uniqueChildren = enrichedChildren.filter((child, index, list) => (
      list.findIndex((item) => childUniqueKey(item) === childUniqueKey(child)) === index
    ));

    const menuRoute = menu?.route ? parseRouteDescriptor(menu.route).name : null;
    const shouldHideTopLevelQuickLink = menuRoute ? quickAccessRoutes.has(menuRoute) : false;

    const canShowTopLevelMenu = menu.route
      && hasRoute(menu.route)
      && canAccessMenuRoute(menu.route)
      && !shouldHideTopLevelQuickLink;

    if (canShowTopLevelMenu || uniqueChildren.length > 0) {
      return {
        ...menu,
        childrens: uniqueChildren,
      };
    }
    return null;
  }).filter(Boolean);
});

const payrollMenuIndex = computed(() => {
  return filteredMenus.value.findIndex((menu) => {
    const normalizedName = String(menu?.name ?? '').trim().toLowerCase();
    const normalizedPermission = String(menu?.permission_name ?? '').trim().toLowerCase();
    return normalizedName === 'payroll' || normalizedPermission === 'payroll-management';
  });
});

const getActiveRoute = (mainMenu) => {
  if (!mainMenu.childrens) return null;
  for (const childMenu of mainMenu.childrens) {
    if (isRouteActive(childMenu.route)) {
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
    <div ref="sidebarScrollContainer" style="width: inherit" class="h-[calc(100vh-60px)] overflow-y-auto bg-gray-100">
      <ul class="w-full px-3 py-4 space-y-1">
        <template v-if="showHrHub && payrollMenuIndex === -1">
          <li v-if="!sideBar" class="px-3 pt-1 pb-2">
            <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold">HR Attendance Hub</p>
          </li>

          <li v-for="quickLink in quickLinks" :key="`top-${quickLink.route}`" :class="{ 'flex justify-center': sideBar }">
            <template v-if="isFullReloadRoute(quickLink.route)">
              <a :href="getMenuHref(quickLink.route)" :data-menu-route="normalizeRouteName(quickLink.route)" @click="handleMenuClick($event, quickLink.route)" :class="[
                isRouteActive(quickLink.route)
                  ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500'
                  : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600',
                navSidebar,
              ]" class="w-full">
                <div class="flex items-center justify-center w-5 h-5">
                  <FeatherIcon :name="quickLink.icon" size="18" :class="[
                    'transition-colors duration-200',
                    isRouteActive(quickLink.route) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                  ]" />
                </div>
                <span v-if="!sideBar" class="truncate text-sm">{{ quickLink.label }}</span>
              </a>
            </template>

            <template v-else>
              <Link :href="getMenuHref(quickLink.route)" :data-menu-route="normalizeRouteName(quickLink.route)" @click="handleMenuClick($event, quickLink.route)" :class="[
                isRouteActive(quickLink.route)
                  ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500'
                  : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600',
                navSidebar,
              ]" class="w-full">
                <div class="flex items-center justify-center w-5 h-5">
                  <FeatherIcon :name="quickLink.icon" size="18" :class="[
                    'transition-colors duration-200',
                    isRouteActive(quickLink.route) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                  ]" />
                </div>
                <span v-if="!sideBar" class="truncate text-sm">{{ quickLink.label }}</span>
              </Link>
            </template>
          </li>
        </template>

        <template v-for="(mainMenu, Index) in filteredMenus" :key="Index">
          <!-- Menu with Submenu -->
          <li v-if="mainMenu.childrens?.length > 0" :class="{ 'flex justify-center': sideBar }" class="relative">
            <SideBarSubMenu align="left" :activeRoute="getActiveRoute(mainMenu)">
              <template #trigger>
                <div @click="handleMenuClick($event)" :class="[
                  navSidebar,
                  getActiveRoute(mainMenu) ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500' : ''
                ]">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="mainMenu.icon" size="18" :class="[
                      'transition-colors duration-200',
                      getActiveRoute(mainMenu) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                    ]" />
                  </div>
                  <span v-if="!sideBar" class="truncate font-medium text-sm">{{ getMenuDisplayName(mainMenu) }}</span>
                </div>
              </template>

              <template #content>
                <ul class="submenu bg-gray-100 border border-gray-200 rounded-md py-1">
                  <li v-for="(submenu, subIndex) in mainMenu.childrens" :key="subIndex">
                    <template v-if="getMenuHref(submenu.route)">
                      <template v-if="isFullReloadRoute(submenu.route)">
                        <a :href="getMenuHref(submenu.route)" :data-menu-route="normalizeRouteName(submenu.route)" @click="handleMenuClick($event, submenu.route)" :class="[
                          isRouteActive(submenu.route)
                            ? 'bg-blue-50 text-blue-600 font-medium'
                            : 'text-gray-700 hover:bg-gray-50',
                          'flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-sm mx-1',
                          sideBar ? '' : 'ml-3',
                        ]">
                          <FeatherIcon :name="submenu.icon" size="16" class="text-gray-500" />
                          <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(submenu) }}</span>
                        </a>
                      </template>
                      <template v-else>
                        <Link :href="getMenuHref(submenu.route)" :data-menu-route="normalizeRouteName(submenu.route)" @click="handleMenuClick($event, submenu.route)" :class="[
                          isRouteActive(submenu.route)
                            ? 'bg-blue-50 text-blue-600 font-medium'
                            : 'text-gray-700 hover:bg-gray-50',
                          'flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-sm mx-1',
                          sideBar ? '' : 'ml-3',
                        ]">
                          <FeatherIcon :name="submenu.icon" size="16" class="text-gray-500" />
                          <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(submenu) }}</span>
                        </Link>
                      </template>
                    </template>
                  </li>
                </ul>
              </template>
            </SideBarSubMenu>
          </li>

          <!-- Single Menu Item -->
          <li v-else :class="{ 'flex justify-center': sideBar }">
            <template v-if="getMenuHref(mainMenu.route)">
              <template v-if="isFullReloadRoute(mainMenu.route)">
                <a :href="getMenuHref(mainMenu.route)" :data-menu-route="normalizeRouteName(mainMenu.route)" @click="handleMenuClick($event, mainMenu.route)" :class="[
                  isRouteActive(mainMenu.route)
                    ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500'
                    : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600',
                  navSidebar,
                ]" class="w-full">
                <div class="flex items-center justify-center w-5 h-5">
                  <FeatherIcon :name="mainMenu.icon" size="18" :class="[
                    'transition-colors duration-200',
                    isRouteActive(mainMenu.route) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                  ]" />
                </div>
                <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(mainMenu) }}</span>
                </a>
              </template>
              <template v-else>
                <Link :href="getMenuHref(mainMenu.route)" :data-menu-route="normalizeRouteName(mainMenu.route)" @click="handleMenuClick($event, mainMenu.route)" :class="[
                  isRouteActive(mainMenu.route)
                    ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500'
                    : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600',
                  navSidebar,
                ]" class="w-full">
                <div class="flex items-center justify-center w-5 h-5">
                  <FeatherIcon :name="mainMenu.icon" size="18" :class="[
                    'transition-colors duration-200',
                    isRouteActive(mainMenu.route) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                  ]" />
                </div>
                <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(mainMenu) }}</span>
                </Link>
              </template>
            </template>
          </li>

          <template v-if="showHrHub && Index === payrollMenuIndex">
            <li v-if="!sideBar" class="px-3 pt-2 pb-2">
              <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold">HR Attendance Hub</p>
            </li>

            <li v-for="quickLink in quickLinks" :key="`payroll-${quickLink.route}`" :class="{ 'flex justify-center': sideBar }">
              <template v-if="isFullReloadRoute(quickLink.route)">
                <a :href="getMenuHref(quickLink.route)" :data-menu-route="normalizeRouteName(quickLink.route)" @click="handleMenuClick($event, quickLink.route)" :class="[
                  isRouteActive(quickLink.route)
                    ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500'
                    : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600',
                  navSidebar,
                ]" class="w-full">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="quickLink.icon" size="18" :class="[
                      'transition-colors duration-200',
                      isRouteActive(quickLink.route) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                    ]" />
                  </div>
                  <span v-if="!sideBar" class="truncate text-sm">{{ quickLink.label }}</span>
                </a>
              </template>

              <template v-else>
                <Link :href="getMenuHref(quickLink.route)" :data-menu-route="normalizeRouteName(quickLink.route)" @click="handleMenuClick($event, quickLink.route)" :class="[
                  isRouteActive(quickLink.route)
                    ? 'bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500'
                    : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600',
                  navSidebar,
                ]" class="w-full">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="quickLink.icon" size="18" :class="[
                      'transition-colors duration-200',
                      isRouteActive(quickLink.route) ? 'text-blue-600' : 'text-gray-500 group-hover:text-blue-600'
                    ]" />
                  </div>
                  <span v-if="!sideBar" class="truncate text-sm">{{ quickLink.label }}</span>
                </Link>
              </template>
            </li>
          </template>
        </template>

      </ul>
    </div>
  </div>
</template>

<style scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}

.bg-blue-50 {
  background-color: var(--app-theme-soft) !important;
}

.bg-gray-100,
.bg-gray-50 {
  background-color: color-mix(in srgb, var(--app-theme-soft) 36%, #f8fafc) !important;
}

.border-gray-200 {
  border-color: color-mix(in srgb, var(--app-theme-primary) 20%, #cbd5e1) !important;
}

.text-gray-500,
.text-gray-700,
.text-gray-800 {
  color: color-mix(in srgb, var(--app-theme-contrast) 70%, #334155) !important;
}

.text-blue-600,
.text-blue-700 {
  color: var(--app-theme-primary) !important;
}

.border-blue-500 {
  border-color: var(--app-theme-primary) !important;
}

.hover\:bg-gray-50:hover {
  background-color: color-mix(in srgb, var(--app-theme-soft) 62%, white) !important;
}

.hover\:bg-blue-50:hover {
  background-color: var(--app-theme-soft) !important;
}

.hover\:text-blue-600:hover,
.hover\:text-blue-700:hover {
  color: var(--app-theme-primary) !important;
}

.group:hover .group-hover\:text-blue-600 {
  color: var(--app-theme-primary) !important;
}

::-webkit-scrollbar-track {
  background: color-mix(in srgb, var(--app-theme-soft) 25%, #e2e8f0);
  border-radius: 8px;
}

::-webkit-scrollbar-thumb {
  background: color-mix(in srgb, var(--app-theme-primary) 36%, #94a3b8);
  border-radius: 8px;
}

::-webkit-scrollbar-thumb:hover {
  background: color-mix(in srgb, var(--app-theme-primary) 52%, #64748b);
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

.submenu {
  background: color-mix(in srgb, var(--app-theme-surface) 88%, #f8fafc) !important;
  border-color: color-mix(in srgb, var(--app-theme-primary) 18%, #cbd5e1) !important;
}
</style>