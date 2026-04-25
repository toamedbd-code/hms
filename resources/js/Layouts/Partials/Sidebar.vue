<script setup>
import { reactive, ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import SideBarSubMenu from "@/Components/SideBarSubMenu.vue";
import eventBus from "@/eventBus.js";

const page = usePage();
const screenWidth = ref(window.innerWidth);
const sideBar = ref(false);
const brandingOverride = ref(null);
let brandingHandler = null;
let remoteUpdateHandler = null;
// Accept both Inertia shared prop `webSetting` and page-specific `websetting`
// (the WebSetting form returns `websetting` on partial reloads).
// Prefer any client-side branding override so runtime updates persist
// across navigation even if the server returns stale shared props.
const webSetting = computed(() => {
  const p = page.props ?? {};
  let ws = null;

  // If an in-memory branding override exists (emitted by app.js), prefer it
  if (brandingOverride.value && Object.keys(brandingOverride.value || {}).length > 0) {
    const b = brandingOverride.value;
    ws = {
      id: b.id ?? null,
      company_name: b.name ?? b.company_name ?? '',
      company_short_name: b.short_name ?? '',
      phone: b.phone ?? '',
      email: b.email ?? '',
      logo: b.logo ?? '',
      icon: b.favicon ?? b.icon ?? '',
      address: b.address ?? '',
      sorting: b.sorting ?? 0,
      status: b.status ?? 'Active',
      created_at: b.created_at ?? null,
      updated_at: b.updated_at ?? null,
      current_theme: b.current_theme ?? undefined,
    };

    return ws;
  }

  ws = p?.websetting ?? p?.webSetting ?? null;

  // If shared companyInfo exists but no webSetting, map it into webSetting-like shape
  if ((!ws || Object.keys(ws).length === 0) && p?.companyInfo) {
    const c = p.companyInfo;
    ws = {
      id: c.id ?? null,
      company_name: c.name ?? c.company_name ?? '',
      company_short_name: c.short_name ?? '',
      phone: c.phone ?? '',
      email: c.email ?? '',
      logo: c.logo ?? '',
      icon: c.favicon ?? c.icon ?? '',
      address: c.address ?? '',
      sorting: c.sorting ?? 0,
      status: c.status ?? 'Active',
      created_at: c.created_at ?? null,
      updated_at: c.updated_at ?? null,
      current_theme: c.current_theme ?? undefined,
    };
  }

  // Final fallback: parse inline data-page JSON if present
  if ((!ws || Object.keys(ws).length === 0) && typeof document !== 'undefined') {
    try {
      const pageScript = document.querySelector('script[type="application/json"][data-page="app"]')
        ?? document.querySelector('script[type="application/json"][data-page]');
      const scriptPayload = pageScript?.textContent;
      if (scriptPayload) {
        const parsed = JSON.parse(scriptPayload);
        const p2 = parsed?.props ?? {};
        if (p2?.websetting || p2?.webSetting) {
          ws = p2.websetting ?? p2.webSetting;
        } else if (p2?.companyInfo) {
          const c = p2.companyInfo;
          ws = {
            id: c.id ?? null,
            company_name: c.name ?? c.company_name ?? '',
            company_short_name: c.short_name ?? '',
            phone: c.phone ?? '',
            email: c.email ?? '',
            logo: c.logo ?? '',
            icon: c.favicon ?? c.icon ?? '',
            address: c.address ?? '',
            sorting: c.sorting ?? 0,
            status: c.status ?? 'Active',
            created_at: c.created_at ?? null,
            updated_at: c.updated_at ?? null,
            current_theme: c.current_theme ?? undefined,
          };
        }
      }
    } catch (e) {
      // ignore parse errors
    }
  }

  // If webSetting still empty, try last-known branding payload stored on window
  if ((!ws || Object.keys(ws).length === 0) && typeof window !== 'undefined' && window.__last_branding_payload) {
    try {
      const b = window.__last_branding_payload;
      ws = {
        id: b.id ?? null,
        company_name: b.name ?? b.company_name ?? '',
        company_short_name: b.short_name ?? '',
        phone: b.phone ?? '',
        email: b.email ?? '',
        logo: b.logo ?? '',
        icon: b.favicon ?? b.icon ?? '',
        address: b.address ?? '',
        sorting: b.sorting ?? 0,
        status: b.status ?? 'Active',
        created_at: b.created_at ?? null,
        updated_at: b.updated_at ?? null,
        current_theme: b.current_theme ?? undefined,
      };
    } catch (e) {
      // ignore
    }
  }

  return ws || {};
});
const sidebarScrollContainer = ref(null);
const lastClickedRoute = ref(null);

const handleResize = () => {
  screenWidth.value = window.innerWidth;
};

onMounted(() => {
  window.addEventListener("resize", handleResize);
  // If server didn't provide sideMenus, try to fetch via API (fallback)
  try {
    const serverMenus = page.props.auth?.sideMenus ?? [];
    if (!Array.isArray(serverMenus) || serverMenus.length === 0) {
      let apiUrl = null;
      try {
        if (typeof route === 'function') {
          try { apiUrl = route('backend.api.side-menus'); } catch (e) { /* ignore */ }
          if (!apiUrl) {
            try { apiUrl = route('backend.backend.api.side-menus'); } catch (e) { /* ignore */ }
          }
        }
      } catch (err) {
        // ignore
      }

      if (!apiUrl) apiUrl = '/admin/side-menus';

      // Prefer axios when available, otherwise use fetch fallback
      if (typeof window !== 'undefined' && window.axios && typeof window.axios.get === 'function') {
        window.axios.get(apiUrl)
          .then((resp) => {
            if (resp && resp.data) {
              remoteSideMenus.value = resp.data;
            }
          }).catch(() => {
            // ignore
          });
      } else if (typeof fetch === 'function') {
        try {
          fetch(apiUrl, { credentials: 'include' })
            .then((r) => r.json())
            .then((data) => {
              if (data) remoteSideMenus.value = data;
            }).catch(() => {
              // ignore
            });
        } catch (fErr) {
          // ignore
        }
      }
    }
  } catch (e) {
    // ignore
  }

  // Client-side injection: if permissions indicate Account Management should
  // be visible but the server-side menus are missing it (stale Inertia props),
  // insert a minimal Account Management menu so the sidebar shows it immediately.
  try {
    const perms = userPermissions.value || [];
    const accountPerms = ['account-management', 'chart-of-accounts', 'ledger', 'account-balances', 'activity-log-view'];
    const hasAnyAccountPerm = accountPerms.some((p) => perms.includes(p));
    const alreadyPresent = (sourceMenus.value || []).some((m) => String(m?.name ?? '').trim().toLowerCase() === 'account management');
    const isAdminPresent = Boolean(page.props?.auth?.admin) || (typeof window !== 'undefined' && Boolean(window.__inertia?.page?.props?.auth?.admin));

    // Only auto-inject Account Management when the user has any account-related
    // permission. Don't rely on the admin flag alone, otherwise disabling all
    // account permissions won't hide the menu.
    if (hasAnyAccountPerm && !alreadyPresent) {
      const possibleChildren = [
        { name: 'Chart of Accounts', icon: 'list', route: 'backend.accounts.index', permission: 'chart-of-accounts' },
        { name: 'Ledger', icon: 'book', route: 'backend.ledger.index', permission: 'ledger' },
        { name: 'Account Balances', icon: 'balance', route: 'backend.accounts.balances', permission: 'account-balances' },
        { name: 'Audit Log', icon: 'activity-log', route: 'backend.accounts.audit', permission: 'activity-log-view' },
      ];

      const children = possibleChildren.filter((c) => {
        if (c.permission && !perms.includes(c.permission)) return false;
        return hasRoute(c.route);
      }).map((c) => ({
        id: null,
        name: c.name,
        icon: c.icon,
        route: c.route,
        permission_name: c.permission,
        status: 'Active',
      }));

      // Only add the parent if we have at least one visible child or the user
      // explicitly has the parent permission.
      if (children.length > 0 || perms.includes('account-management')) {
        const accountMenu = {
          id: null,
          name: 'Account Management',
          icon: 'dollar-sign',
          route: null,
          description: 'Ledger, accounts and audit',
          sorting: 1,
          parent_id: null,
          permission_name: 'account-management',
          status: 'Active',
          childrens: children,
        };

        remoteSideMenus.value = Array.isArray(remoteSideMenus.value) ? [...remoteSideMenus.value, accountMenu] : [accountMenu];
      }
    }
  } catch (err) {
    // ignore
  }

  // Expose debug hooks for developer inspection in browser console
  try {
    window.__sidebar_debug = window.__sidebar_debug || {};
    window.__sidebar_debug.getSnapshot = () => ({
      remoteSideMenus: Array.isArray(remoteSideMenus.value) ? JSON.parse(JSON.stringify(remoteSideMenus.value)) : [],
      sourceMenus: Array.isArray(sourceMenus.value) ? JSON.parse(JSON.stringify(sourceMenus.value)) : [],
      filteredMenus: Array.isArray(filteredMenus.value) ? JSON.parse(JSON.stringify(filteredMenus.value)) : [],
      userPermissions: Array.isArray(userPermissions.value) ? [...userPermissions.value] : [],
      pageAuth: page.props?.auth ?? (window.__inertia?.page?.props?.auth ?? null),
    });

    // Keep live copies for easier inspection. Only log to console in dev.
    const __sidebar_is_dev = (typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.DEV) || (typeof process !== 'undefined' && process.env && process.env.NODE_ENV !== 'production');
    watch(remoteSideMenus, (v) => {
      window.__sidebar_debug.remoteSideMenus = v;
      if (__sidebar_is_dev) console.log('[sidebar debug] remoteSideMenus updated', v);
    }, { deep: true });
    watch(sourceMenus, (v) => {
      window.__sidebar_debug.sourceMenus = v;
      if (__sidebar_is_dev) console.log('[sidebar debug] sourceMenus updated', v);
    }, { deep: true });
    watch(filteredMenus, (v) => {
      window.__sidebar_debug.filteredMenus = v;
      if (__sidebar_is_dev) console.log('[sidebar debug] filteredMenus updated', v);
    }, { deep: true, immediate: true });

    // populate initial snapshot
    try { window.__sidebar_debug.getSnapshot(); } catch (e) { /* ignore */ }
  } catch (e) {
    // ignore
  }

  try {
    brandingHandler = (payload) => {
      try {
        brandingOverride.value = payload ?? null;
      } catch (e) {
        // ignore
      }
    };
    eventBus.on('branding.updated', brandingHandler);
    // Listen for remote side-menu updates emitted by other pages (fallback)
    remoteUpdateHandler = (payload) => {
      try {
        if (Array.isArray(payload)) {
          remoteSideMenus.value = payload;

          // Derive permission names from the payload so client-side
          // filtering aligns with the server-provided snapshot.
          try {
            const perms = new Set();
            const walk = (menus) => {
              (menus || []).forEach((m) => {
                const p = m?.permission_name ?? m?.permission ?? null;
                if (p) perms.add(String(p).trim());
                const children = m?.childrens ?? m?.child ?? [];
                if (Array.isArray(children) && children.length) walk(children);
              });
            };
            walk(payload);
            overrideUserPermissions.value = Array.from(perms);
          } catch (e) {
            // ignore
          }
        }
      } catch (e) {
        // ignore
      }
    };
    eventBus.on('sidebar.remoteUpdated', remoteUpdateHandler);
  } catch (e) {
    // ignore
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", handleResize);
  try {
    if (brandingHandler && eventBus && typeof eventBus.off === 'function') {
      eventBus.off('branding.updated', brandingHandler);
    }
    if (remoteUpdateHandler && eventBus && typeof eventBus.off === 'function') {
      eventBus.off('sidebar.remoteUpdated', remoteUpdateHandler);
    }
  } catch (e) {
    // ignore
  }
});

eventBus.on("sidebarToggled", (flag) => {
  sideBar.value = flag;
});

const navSidebar = reactive([
  "flex items-center p-3 space-x-3 rounded-md cursor-pointer hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group",
]);

// Local fallback storage for side menus if server-side Inertia props are empty
const remoteSideMenus = ref([]);
// Optional override of user permissions derived from remote side-menus
const overrideUserPermissions = ref(null);
// Controlled open state for submenu components (keyed by childUniqueKey(menu))
const openState = reactive({});

const onMenuTriggerClick = (event, key, route = null) => {
  try {
    // If the click came from inside the submenu content, don't toggle
    if (event?.target?.closest && event.target.closest('.submenu')) return;
  } catch (e) { /* ignore */ }

  openState[key] = !openState[key];
  if (route) {
    try { handleMenuClick(event, route); } catch (e) { /* ignore */ }
  }
};
const sourceMenus = computed(() => {
  const previewMenus = page.props?.previewSideMenus ?? null;
  if (Array.isArray(previewMenus) && previewMenus.length > 0) {
    return previewMenus;
  }

  const serverMenus = page.props.auth?.sideMenus ?? [];
  return (Array.isArray(serverMenus) && serverMenus.length > 0) ? serverMenus : remoteSideMenus.value;
});

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
// Additional aliases for menu entries that may omit the `backend.` prefix
Object.assign(routeAliasMap, {
  'websetting.create': 'backend.websetting.create',
  'journal-entry.index': 'backend.journal-entry.index',
});

// Some environments prefix route names (e.g., group 'as' + explicit names) causing
// duplicated name parts like 'backend.backend.accounts.index'. Add common aliases
// so client-side menu descriptors resolve to the Ziggy-exported names.
Object.assign(routeAliasMap, {
  'backend.accounts.index': 'backend.backend.accounts.index',
  'backend.ledger.index': 'backend.backend.ledger.index',
  'backend.accounts.balances': 'backend.backend.accounts.balances',
  'backend.accounts.audit': 'backend.backend.accounts.audit',
});

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
  const menus = sourceMenus.value ?? [];

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
  // Prefer an explicit override when remote side-menus were fetched
  if (Array.isArray(overrideUserPermissions.value) && overrideUserPermissions.value.length > 0) {
    return overrideUserPermissions.value;
  }

  const raw = page.props.auth?.permissions ?? [];
  if (Array.isArray(raw)) return raw;
  if (raw && typeof raw === 'object') {
    try {
      return Object.values(raw);
    } catch (e) {
      return [];
    }
  }
  return [];
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

  // If any of these quickLinks already appear in the rendered menus,
  // don't return them to avoid duplication.
  try {
    const existsInMenus = (route) => {
      try {
        const rn = parseRouteDescriptor(route).name;
        if (!rn) return false;
        return filteredMenus.value.some((m) => {
          if (m?.route && parseRouteDescriptor(m.route).name === rn) return true;
          return (m.childrens ?? []).some((c) => c?.route && parseRouteDescriptor(c.route).name === rn);
        });
      } catch (e) {
        return false;
      }
    };

    return links.filter((l) => !existsInMenus(l.route));
  } catch (e) {
    return links;
  }
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
  const base = (sourceMenus.value ?? []).map(menu => {
    if (isBlockedMenuName(menu?.name)) {
      return null;
    }

    const filteredChildren = (menu.childrens ?? []).filter(child => {
        if (isBlockedMenuName(child?.name)) {
          return false;
        }

        // Respect explicit permission on the menu item when present
        if ((child.permission_name || child.permission) && !hasPermission(child.permission_name || child.permission)) {
          return false;
        }

        if (!child?.route || !hasRoute(child.route)) {
          return false;
        }

        if (!canAccessMenuRoute(child.route)) {
          return false;
        }

        return true;
      }).filter((child, index, list) => list.findIndex((item) => childUniqueKey(item) === childUniqueKey(child)) === index);

    const eligibleModuleSubmenus = webSettingModuleSubmenus.filter((submenu) => {
      return !submenu.requiredPermission || hasPermission(submenu.requiredPermission);
    });

    const enrichedChildren = isWebSettingMenu(menu, filteredChildren) && canShowWebSettingModuleSubmenus.value
      ? [...filteredChildren, ...eligibleModuleSubmenus]
      : filteredChildren;

    let uniqueChildren = enrichedChildren.filter((child, index, list) => (
      list.findIndex((item) => childUniqueKey(item) === childUniqueKey(child)) === index
    ));

    // Ensure submenu ordering is stable: sort by `sorting` then `id` when available
    try {
      uniqueChildren.sort((a, b) => {
        const sa = parseInt((a && a.sorting) || 0, 10) || 0;
        const sb = parseInt((b && b.sorting) || 0, 10) || 0;
        if (sa !== sb) return sa - sb;
        const ida = parseInt((a && a.id) || 0, 10) || 0;
        const idb = parseInt((b && b.id) || 0, 10) || 0;
        return ida - idb;
      });
    } catch (e) {
      // ignore sort errors
    }

    const menuRoute = menu?.route ? parseRouteDescriptor(menu.route).name : null;
    // Do not automatically hide top-level menus that match quick-access routes.
    const shouldHideTopLevelQuickLink = false;

    // If the menu defines a permission requirement, enforce it for top-level visibility.
    const menuRequiresPermission = Boolean(menu?.permission_name || menu?.permission);
    const menuHasPermission = menuRequiresPermission ? hasPermission(menu.permission_name || menu.permission) : true;

    const canShowTopLevelMenu = menu.route
      && hasRoute(menu.route)
      && canAccessMenuRoute(menu.route)
      && menuHasPermission
      && !shouldHideTopLevelQuickLink;

    // Show parent menu when admin has the parent's permission even if it has no route/children
    const showBecauseHasParentPermission = (!menu.route || String(menu.route).trim() === '')
      && (menu.permission_name && hasPermission(menu.permission_name));

    if (canShowTopLevelMenu || uniqueChildren.length > 0 || showBecauseHasParentPermission) {
      return {
        ...menu,
        childrens: uniqueChildren,
      };
    }
    return null;
  }).filter(Boolean);

  // Ensure Account Management is visible only when the user has account-related permissions
  try {
    const hasAccount = base.some((m) => String(m?.name ?? '').trim().toLowerCase() === 'account management');
    const perms = userPermissions.value || [];
    const accountPerms = ['account-management', 'chart-of-accounts', 'ledger', 'account-balances', 'activity-log-view'];
    const hasAnyAccountPerm = accountPerms.some((p) => perms.includes(p));
    // Show Account Management only when the user has any account-related permission
    if (hasAnyAccountPerm && !hasAccount) {
      const possibleChildren = [
        { name: 'Chart of Accounts', icon: 'list', route: 'backend.accounts.index', permission: 'chart-of-accounts' },
        { name: 'Ledger', icon: 'book', route: 'backend.ledger.index', permission: 'ledger' },
        { name: 'Account Balances', icon: 'balance', route: 'backend.accounts.balances', permission: 'account-balances' },
        { name: 'Audit Log', icon: 'activity-log', route: 'backend.accounts.audit', permission: 'activity-log-view' },
      ];

      const children = possibleChildren.filter((c) => {
        if (!hasRoute(c.route)) return false;
        // Require explicit permissions to show children. If the permissions list
        // is empty, treat it as 'no permissions' and do not show.
        if (!perms || perms.length === 0) return false;
        return perms.includes(c.permission) || perms.includes('account-management');
      }).map((c) => ({
        id: null,
        name: c.name,
        icon: c.icon,
        route: c.route,
        permission_name: c.permission,
        status: 'Active',
      }));

      const accountMenu = {
        id: null,
        name: 'Account Management',
        icon: 'dollar-sign',
        route: null,
        description: 'Ledger, accounts and audit',
        sorting: 1,
        parent_id: null,
        permission_name: 'account-management',
        status: 'Active',
        childrens: children,
      };

      base.push(accountMenu);
    }
  } catch (err) {
    // ignore
  }

  // Deduplicate top-level menus that are also listed as children
  try {
    const deduped = base.filter((m, idx) => {
      const normalizedName = normalizeMenuName(m?.name);

      // If any other menu contains a child with the same normalized name,
      // treat this top-level menu as a duplicate and remove it.
      const isDuplicate = base.some((other, otherIdx) => {
        if (otherIdx === idx) return false;
        return (other?.childrens ?? []).some((c) => normalizeMenuName(c?.name) === normalizedName);
      });

      return !isDuplicate;
    });

    // Ensure top-level ordering matches server-side sorting: `sorting` then `id`.
    try {
      deduped.sort((a, b) => {
        const sa = parseInt((a && a.sorting) || 0, 10) || 0;
        const sb = parseInt((b && b.sorting) || 0, 10) || 0;
        if (sa !== sb) return sa - sb;
        const ida = parseInt((a && a.id) || 0, 10) || 0;
        const idb = parseInt((b && b.id) || 0, 10) || 0;
        return ida - idb;
      });
    } catch (e) {
      // ignore sort failure
    }

    return deduped;
  } catch (e) {
    return base;
  }
});

// Previously we auto-expanded parents with quick-access children on load.
// That behaviour is disabled to ensure all menus remain closed after a
// full page refresh. Users can still expand menus by clicking them.

// Helper: find the original menu object from source or remote lists
const findRawMenu = (menu) => {
  try {
    const name = normalizeMenuName(menu?.name);
    const server = sourceMenus.value ?? [];
    let found = server.find(m => (m?.id && menu?.id && m.id === menu.id) || normalizeMenuName(m?.name) === name);
    if (found) return found;
    const remote = Array.isArray(remoteSideMenus.value) ? remoteSideMenus.value : [];
    found = remote.find(m => (m?.id && menu?.id && m.id === menu.id) || normalizeMenuName(m?.name) === name);
    return found || null;
  } catch (e) {
    return null;
  }
};

// Get children to render for a menu: prefer already-filtered children, otherwise
// fall back to raw source children (with light permission filtering).
const getRenderedChildren = (mainMenu) => {
  try {
    if (mainMenu?.childrens && mainMenu.childrens.length > 0) return mainMenu.childrens;
    const raw = findRawMenu(mainMenu);
    if (!raw || !Array.isArray(raw.childrens) || raw.childrens.length === 0) return [];

    return raw.childrens.filter((child) => {
      if (isBlockedMenuName(child?.name)) return false;
      // require a route to render as submenu link
      if (!child?.route) return false;
      // Respect permission check (if the submenu has a permission_name, require it)
      if (child.permission_name && !hasPermission(child.permission_name)) return false;
      return true;
    });
  } catch (e) {
    return [];
  }
};

const navigateFallback = (routeDescriptorValue, event) => {
  // First try: build an href via getMenuHref (preferred path)
  try {
    const href = getMenuHref(routeDescriptorValue);
    if (href) {
      window.location.href = href;
      return;
    }
  } catch (e) {
    // ignore
  }

  // Second try: attempt several route-name variants using Ziggy's `route()`
  try {
    const descriptor = parseRouteDescriptor(routeDescriptorValue || '');
    const rawName = String(routeDescriptorValue ?? '').split('?')[0] || '';

    const candidates = [];
    if (descriptor.name) candidates.push(descriptor.name);
    if (rawName) candidates.push(rawName);

    // include normalized alias (if any)
    try {
      const normalized = normalizeRouteName(rawName);
      if (normalized) candidates.push(normalized);
    } catch (e) {}

    // try toggling common backend prefix duplications
    try {
      if (descriptor.name && descriptor.name.startsWith('backend.backend.')) {
        candidates.push(descriptor.name.replace(/^backend\.backend\./, 'backend.'));
      } else if (descriptor.name) {
        candidates.push(('backend.backend.' + descriptor.name).replace(/(^backend\.backend\.|^backend\.)/, (m) => m));
        // also try ensuring single 'backend.' prefix
        candidates.push(('backend.' + descriptor.name).replace(/^backend\.backend\./, 'backend.'));
      }
    } catch (e) {}

    const unique = [...new Set(candidates.filter(Boolean))];

    for (const name of unique) {
      try {
        const url = route(name, descriptor.params || {});
        if (typeof url === 'string') {
          window.location.href = url;
          return;
        }
      } catch (err) {
        // ignore and try next candidate
      }
    }
  } catch (err) {
    // ignore
  }

  // Last-resort: if the routeDescriptorValue looks like a path, navigate directly
  try {
    if (typeof routeDescriptorValue === 'string' && routeDescriptorValue.startsWith('/')) {
      window.location.href = routeDescriptorValue;
      return;
    }
  } catch (e) {}

  console.warn('[Sidebar] fallback navigation failed for', routeDescriptorValue);
};

const menuHasChildren = (mainMenu) => {
  try {
    const arr = getRenderedChildren(mainMenu);
    return Array.isArray(arr) && arr.length > 0;
  } catch (e) {
    return false;
  }
};

// Insert HR quick links at the end of the menu list so they appear lower.
const insertionIndex = computed(() => {
  if (!showHrHub) return -1;
  try {
    return Array.isArray(filteredMenus.value) ? filteredMenus.value.length : 0;
  } catch (e) {
    return 0;
  }
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
        <!-- quickLinks will be inserted inline within the menu list (see insertionIndex) -->

        <template v-for="(mainMenu, Index) in filteredMenus" :key="childUniqueKey(mainMenu)">
          <!-- Menu with Submenu -->
          <li v-if="menuHasChildren(mainMenu)" :class="{ 'flex justify-center': sideBar }" class="relative" @click="onMenuTriggerClick($event, childUniqueKey(mainMenu), getActiveRoute(mainMenu) || (getRenderedChildren(mainMenu)[0] && getRenderedChildren(mainMenu)[0].route))">
            <SideBarSubMenu
              align="left"
              :activeRoute="getActiveRoute(mainMenu)"
              :open="openState[childUniqueKey(mainMenu)]"
              @update:open="(v) => (openState[childUniqueKey(mainMenu)] = v)"
              @toggle="handleMenuClick($event, getActiveRoute(mainMenu) || (getRenderedChildren(mainMenu)[0] && getRenderedChildren(mainMenu)[0].route))"
            >
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
                  <span v-if="!sideBar" class="truncate font-medium text-sm">{{ getMenuDisplayName(mainMenu) }}</span>
                </div>
              </template>

              <template #content>
                <ul class="submenu bg-gray-100 border border-gray-200 rounded-md py-1">
                  <li v-for="(submenu, subIndex) in getRenderedChildren(mainMenu)" :key="childUniqueKey(submenu)">
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
                    <template v-else>
                      <a href="#" @click.prevent="navigateFallback(submenu.route, $event)" :class="[
                        'text-gray-700 hover:bg-gray-50',
                        'flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-sm mx-1',
                        sideBar ? '' : 'ml-3',
                      ]">
                        <FeatherIcon :name="submenu.icon" size="16" class="text-gray-500" />
                        <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(submenu) }}</span>
                      </a>
                    </template>
                  </li>
                </ul>
              </template>
            </SideBarSubMenu>
          </li>

          <!-- Single Menu Item -->
          <li v-else :class="{ 'flex justify-center': sideBar }">
            <div v-if="getMenuHref(mainMenu.route)">
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
            </div>

            <div v-else @click="handleMenuClick($event)" :class="[navSidebar, 'cursor-default']">
              <div class="flex items-center justify-center w-5 h-5">
                <FeatherIcon :name="mainMenu.icon" size="18" class="text-gray-500" />
              </div>
              <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(mainMenu) }}</span>
            </div>
          </li>

          <template v-if="showHrHub && Index === insertionIndex">
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