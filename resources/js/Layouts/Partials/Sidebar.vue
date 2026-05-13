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
let remoteForceHandler = null;
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
// Timestamps to avoid older remote snapshots overriding newer server props
const lastServerSnapshotAt = ref(0);
const lastRemoteSnapshotAt = ref(0);

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

  // NOTE: Client-side auto-injection of menu entries (e.g. Account Management)
  // was removed to ensure the server-provided `sideMenus` are authoritative.
  // Sidebars must reflect the permissions returned by the server's
  // `getSideMenus()` snapshot so role/permission changes take effect
  // deterministically after Inertia prop reloads or the explicit fallback
  // fetch performed by the role editor.

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
    // Track when the server-provided auth.sideMenus last updated so we can
    // avoid applying older remote snapshots that would reintroduce removed
    // menus (race condition between role save and fallback fetch).
    try {
      watch(() => page.props?.auth?.sideMenus, (v) => {
        try { lastServerSnapshotAt.value = Date.now(); } catch (e) { /* ignore */ }
      }, { immediate: true });
    } catch (e) {
      // ignore
    }
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
          // mark remote snapshot arrival time
          try { lastRemoteSnapshotAt.value = Date.now(); } catch (e) { /* ignore */ }

          // Only apply remote snapshot to the UI when it appears to be
          // newer than the last server snapshot. This prevents older
          // fallback responses from momentarily reintroducing menus that
          // were just removed by a role update.
          const preferRemote = (Array.isArray(payload) && payload.length > 0)
            && (lastRemoteSnapshotAt.value > (lastServerSnapshotAt.value || 0));

          // Special-case: if the server snapshot currently includes the
          // "Account Management" parent but the remote payload does not,
          // prefer the remote payload (apply it) so that removals of the
          // Account Management parent take effect immediately. This avoids
          // the UX where Account Management disappears only after a full
          // page reload.
          let applied = false;
          try {
            const payloadHasAccount = (Array.isArray(payload) && payload.some((m) => String(m?.name ?? '').trim().toLowerCase() === 'account management'));
            const serverMenus = page.props?.auth?.sideMenus ?? [];
            const serverHasAccount = (Array.isArray(serverMenus) && serverMenus.some((m) => String(m?.name ?? '').trim().toLowerCase() === 'account management'));

            if (serverHasAccount && !payloadHasAccount) {
              // force-apply removal
              remoteSideMenus.value = payload;
              applied = true;
            }
          } catch (e) {
            // ignore
          }

          if (!applied) {
            if (preferRemote) {
              remoteSideMenus.value = payload;
            } else {
              // keep existing remoteSideMenus (if any) and do not overwrite with
              // an older snapshot; still derive permissions for debug views
              // from the payload but do not replace the active menu list.
            }
          }

          // Derive permission names from the payload so client-side
          // filtering aligns with the server-provided snapshot.
          try {
            const perms = new Set();
            const walk = (menus) => {
              (menus || []).forEach((m) => {
                const p = m?.permission_name ?? m?.permission ?? null;
                if (p) perms.add(String(p).trim().toLowerCase());
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
    // Force-apply handler: updates sidebar immediately when a role editor
    // emits an authoritative snapshot. This bypasses timestamp ordering to
    // ensure UI reflects permission removals instantly without reload.
    remoteForceHandler = (payload) => {
      try {
        if (!Array.isArray(payload)) return;
        try { lastRemoteSnapshotAt.value = Date.now(); } catch (e) { /* ignore */ }

        // Immediately replace remoteSideMenus with the authoritative payload
        remoteSideMenus.value = payload;

        // Also update derived permissions used for client-side filtering
        try {
          const perms = new Set();
          const walk = (menus) => {
            (menus || []).forEach((m) => {
              const p = m?.permission_name ?? m?.permission ?? null;
              if (p) perms.add(String(p).trim().toLowerCase());
              const children = m?.childrens ?? m?.child ?? [];
              if (Array.isArray(children) && children.length) walk(children);
            });
          };
          walk(payload);
          overrideUserPermissions.value = Array.from(perms);
        } catch (e) {
          // ignore
        }
      } catch (e) {
        // ignore
      }
    };
    eventBus.on('sidebar.remoteUpdatedForce', remoteForceHandler);
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
    if (remoteForceHandler && eventBus && typeof eventBus.off === 'function') {
      eventBus.off('sidebar.remoteUpdatedForce', remoteForceHandler);
    }
  } catch (e) {
    // ignore
  }
});

eventBus.on("sidebarToggled", (flag) => {
  sideBar.value = flag;
});

const navSidebar = reactive([
  "flex items-center p-3 space-x-3 rounded-md cursor-pointer hover:bg-white hover:text-emerald-700 transition-all duration-200 group",
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

  // Prefer an explicit remote snapshot when present. This helps avoid a
  // race where the local Inertia shared `auth.sideMenus` prop is still
  // stale immediately after a role/permission update even though the
  // `/admin/side-menus` fallback returns the updated snapshot. Using the
  // remote snapshot ensures the sidebar reflects permission changes
  // immediately without requiring a full page reload.
  if (Array.isArray(remoteSideMenus.value) && remoteSideMenus.value.length > 0) {
    return remoteSideMenus.value;
  }

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
  'billing.Page': 'backend.billing.Page',
  'billing.index': 'backend.billing.Page',
  'journal-entry.index': 'backend.journal-entry.index',
  'accounts.vendor-payment.index': 'backend.accounts.vendor-payment.index',
  'inventory.index': 'backend.inventory.index',
  'inventory.create': 'backend.inventory.create',
  'medicineinventory.index': 'backend.medicineinventory.index',
  'medicinesupplier.index': 'backend.medicinesupplier.index',
  'medicinepurchase.index': 'backend.medicinepurchase.index',
  'supplierpayment.index': 'backend.supplierpayment.index',
  'stock.index': 'backend.stock.index',
});

// Some environments prefix route names (e.g., group 'as' + explicit names) causing
// duplicated name parts like 'backend.backend.accounts.index'. Add common aliases
// so client-side menu descriptors resolve to the Ziggy-exported names.
Object.assign(routeAliasMap, {
  'backend.billing.index': 'backend.billing.Page',
  'backend.accounts.vendor-payment.index': 'backend.supplierpayment.index',
  'backend.accounts.index': 'backend.backend.accounts.index',
  'backend.ledger.index': 'backend.backend.ledger.index',
  'backend.accounts.balances': 'backend.backend.accounts.balances',
  'backend.accounts.audit': 'backend.backend.accounts.audit',
  'backend.accounts.trial-balance': 'backend.backend.accounts.trial-balance',
  'backend.accounts.profit-loss': 'backend.backend.accounts.profit-loss',
  'backend.accounts.balance-sheet': 'backend.backend.accounts.balance-sheet',
  'backend.accounts.cash-flow': 'backend.backend.accounts.cash-flow',
  'backend.ledger.export': 'backend.backend.ledger.export',
  'backend.backend.inventory.index': 'backend.inventory.index',
  'backend.backend.inventory.create': 'backend.inventory.create',
  'backend.backend.medicineinventory.index': 'backend.medicineinventory.index',
  'backend.backend.medicinesupplier.index': 'backend.medicinesupplier.index',
  'backend.backend.medicinepurchase.index': 'backend.medicinepurchase.index',
  'backend.backend.supplierpayment.index': 'backend.supplierpayment.index',
  'backend.backend.stock.index': 'backend.stock.index',
});

const menuLabelOverrides = {
  'backend.accounts.vendor-payment.index': 'Vendor Payments',
  'backend.productreturn.index': 'Supplier Product Return',
  'backend.inventory.index': 'General Inventory',
  'backend.backend.inventory.index': 'General Inventory',
  'backend.medicineinventory.index': 'Medicine Inventory',
  'backend.backend.medicineinventory.index': 'Medicine Inventory',
  'backend.medicinesupplier.index': 'Medicine Suppliers',
  'backend.backend.medicinesupplier.index': 'Medicine Suppliers',
  'backend.medicinepurchase.index': 'Medicine Purchases',
  'backend.backend.medicinepurchase.index': 'Medicine Purchases',
  'backend.supplierpayment.index': 'Supplier Payments',
  'backend.backend.supplierpayment.index': 'Supplier Payments',
  'backend.stock.index': 'Stock Management',
  'backend.backend.stock.index': 'Stock Management',
  'backend.stock.low-stock-report': 'Low Stock Report',
  'backend.backend.stock.low-stock-report': 'Low Stock Report',
};

const fullReloadRoutes = [
  'backend.attendance.face',
  'backend.attendance.face.register',
  // Force full page reload for bKash admin settings to avoid SPA/modal behavior
  'backend.settings.payment.bkash',
];

const isFullReloadRoute = (name) => fullReloadRoutes.includes(name);

const routeExists = (name) => {
  const routeName = String(name ?? '').trim();
  if (!routeName) return false;

  try {
    const router = route();
    if (typeof router?.has === 'function') {
      return router.has(routeName);
    }

    route(routeName);
    return true;
  } catch (error) {
    return false;
  }
};

const normalizeRouteName = (name) => {
  const original = String(name ?? '').trim();
  if (!original) return '';

  const alias = routeAliasMap[original];
  if (alias) {
    // Prefer alias only when it exists in the current Ziggy route list.
    if (routeExists(alias)) return alias;
    if (routeExists(original)) return original;

    return alias;
  }

  // Generic fallback for duplicated backend prefix (backend.backend.*).
  if (original.startsWith('backend.backend.')) {
    const singlePrefixed = original.replace(/^backend\.backend\./, 'backend.');
    if (routeExists(singlePrefixed)) return singlePrefixed;
  }

  // Generic fallback when menu route omits backend prefix.
  if (!original.startsWith('backend.')) {
    const backendPrefixed = `backend.${original}`;
    if (routeExists(backendPrefixed)) return backendPrefixed;
  }

  // Generic fallback when environment uses double backend prefix.
  if (original.startsWith('backend.')) {
    const doublePrefixed = original.replace(/^backend\./, 'backend.backend.');
    if (routeExists(doublePrefixed)) return doublePrefixed;
  }

  return original;
};

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
  return routeExists(descriptor.name);
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
  let list = [];
  if (Array.isArray(overrideUserPermissions.value) && overrideUserPermissions.value.length > 0) {
    list = overrideUserPermissions.value.map((p) => String(p ?? '').trim().toLowerCase());
  } else {
    const raw = page.props.auth?.permissions ?? [];
    if (Array.isArray(raw)) {
      list = raw.map((p) => String(p ?? '').trim().toLowerCase());
    } else if (raw && typeof raw === 'object') {
      try {
        list = Object.values(raw).map((p) => String(p ?? '').trim().toLowerCase());
      } catch (e) {
        list = [];
      }
    }
  }

  // dedupe
  return Array.from(new Set(list.filter(Boolean)));
});

const hasPermission = (permissionName) => {
  if (!permissionName) return false;
  return userPermissions.value.includes(String(permissionName).trim().toLowerCase());
};
const canManageAllWebSettings = computed(() => hasPermission('websetting-add'));
const canManageCmsSettings = computed(() => canManageAllWebSettings.value || hasPermission('cms-setting'));
const canManageGeneralSettings = computed(() => canManageAllWebSettings.value || hasPermission('general-setting-add'));
const canShowWebSettingModuleSubmenus = computed(() => (
  canManageAllWebSettings.value
  && hasRoute('backend.websetting.section.module')
  // Do not depend on a dedicated sidebar menu row for module settings.
  // We intentionally hide the generic "Module Setting" menu entry,
  // while still exposing the 4 focused module submenus under web settings.
  && (
    canAccessMenuRoute('backend.websetting.section.cms')
    || canAccessMenuRoute('backend.websetting.section.general')
    || canAccessMenuRoute('backend.websetting.create')
    || canAccessMenuRoute('backend.websetting.section.module')
  )
));

const webSettingModuleSubmenus = [
  {
    name: 'Attendance Module Setting',
    icon: 'check-square',
    route: 'backend.websetting.module.attendance',
    requiredPermission: 'attendance-settings',
  },
  {
    name: 'Machine Integration Setting',
    icon: 'activity',
    route: 'backend.websetting.module.pathology',
    requiredPermission: 'machine-integration-setting',
  },
  {
    name: 'Payroll Module Setting',
    icon: 'dollar-sign',
    route: 'backend.websetting.module.payroll',
    requiredPermission: 'payroll-management',
  },
  {
    name: 'Reporting Module Setting',
    icon: 'bar-chart-2',
    route: 'backend.websetting.module.reporting',
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

const isAccountManagementMenu = (menu) => {
  return normalizeMenuName(menu?.name) === 'account management';
};

const shouldHideDuplicateSupplierPayment = (menu, child) => {
  const childRoute = parseRouteDescriptor(child?.route ?? '').name;
  const childName = normalizeMenuName(child?.name);

  const isSupplierPaymentRoute = (
    childRoute === 'backend.supplierpayment.index'
    || childRoute === 'backend.pharmacy.supplier.payment'
  );

  const isSupplierPaymentName = childName === 'supplier payment' || childName === 'vendor payment';

  if (!isSupplierPaymentRoute && !isSupplierPaymentName) {
    return false;
  }

  return !isAccountManagementMenu(menu);
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

  // Auto-open the parent menu whose child matches the active route
  try {
    const activeMenu = orderedMenus.value.find((menu) => Boolean(getActiveRoute(menu)));
    if (activeMenu) {
      const key = childUniqueKey(activeMenu);
      if (!openState[key]) {
        openState[key] = true;
      }
    }
  } catch (e) {
    // ignore
  }

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

        if (shouldHideDuplicateSupplierPayment(menu, child)) {
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

    const hideTopLevelDuplicateSupplierPayment = (
      !isAccountManagementMenu(menu)
      && (
        parseRouteDescriptor(menu?.route ?? '').name === 'backend.supplierpayment.index'
        || normalizeMenuName(menu?.name) === 'supplier payment'
      )
    );

    // Show parent menu when admin has the parent's permission even if it has no route/children
    const showBecauseHasParentPermission = (!menu.route || String(menu.route).trim() === '')
      && (menu.permission_name && hasPermission(menu.permission_name));

    if (!hideTopLevelDuplicateSupplierPayment && (canShowTopLevelMenu || uniqueChildren.length > 0 || showBecauseHasParentPermission)) {
      return {
        ...menu,
        childrens: uniqueChildren,
      };
    }
    return null;
  }).filter(Boolean);

  // NOTE: Do not synthesize top-level menus on the client. The server's
  // `sideMenus` should fully determine what appears in the sidebar so that
  // role/permission changes are accurately represented after a save.

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
    return Array.isArray(orderedMenus.value) ? orderedMenus.value.length : 0;
  } catch (e) {
    return 0;
  }
});

// Keep server/menu sorting stable so sidebar items do not jump position
// while navigating related pages inside the same module.
const orderedMenus = computed(() => {
  return Array.isArray(filteredMenus.value) ? filteredMenus.value : [];
});

const getRouteGroupKey = (routeName) => {
  const normalized = String(routeName ?? '').trim();
  if (!normalized) return '';

  const segments = normalized.split('.').filter(Boolean);
  if (segments.length < 2) return normalized;

  // backend.employee.index/create/edit => backend.employee
  return `${segments[0]}.${segments[1]}`;
};

const getActiveRoute = (mainMenu) => {
  // Use rendered children so parent remains highlighted even when children
  // come from fallback/raw menu sources instead of `mainMenu.childrens`.
  const children = getRenderedChildren(mainMenu);
  if (Array.isArray(children)) {
    for (const childMenu of children) {
      if (childMenu?.route && isRouteActive(childMenu.route)) {
        return childMenu.route;
      }
    }

    // Fallback: if current route-name matches a child route-name, treat parent as active.
    // This covers cases where query/default section params differ across menu definitions.
    const currentName = currentRouteName.value;
    if (currentName) {
      for (const childMenu of children) {
        const descriptor = parseRouteDescriptor(childMenu?.route ?? '');
        if (!descriptor.name || descriptor.name !== currentName) continue;

        if (descriptor.section && currentSection.value && descriptor.section !== currentSection.value) {
          continue;
        }

        if (descriptor.module && currentModule.value && descriptor.module !== currentModule.value) {
          continue;
        }

        return childMenu.route;
      }

      // Related-route fallback: keep parent marked for CRUD sibling pages
      // (e.g., employee.index -> employee.create/edit/update).
      const currentGroup = getRouteGroupKey(currentName);
      if (currentGroup) {
        for (const childMenu of children) {
          const descriptor = parseRouteDescriptor(childMenu?.route ?? '');
          if (!descriptor.name) continue;

          if (getRouteGroupKey(descriptor.name) === currentGroup) {
            return childMenu.route;
          }
        }
      }
    }
  }

  // If parent itself is directly active, keep it marked as active too.
  if (mainMenu?.route && isRouteActive(mainMenu.route)) {
    return mainMenu.route;
  }

  // Parent route-name fallback match (without strict query dependence).
  const parentDescriptor = parseRouteDescriptor(mainMenu?.route ?? '');
  if (parentDescriptor.name && currentRouteName.value === parentDescriptor.name) {
    return mainMenu.route;
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
        class="text-xl font-bold text-gray-800 hover:text-emerald-700 transition-colors duration-200">
      {{ sideBar ? webSetting?.company_short_name : webSetting?.company_name || 'Company Name' }}
      <span v-if="!sideBar" class="block text-xs font-normal text-gray-500 mt-0.5"></span>
      </Link>
    </div>

    <!-- Navigation Menu -->
    <div ref="sidebarScrollContainer" style="width: inherit" class="h-[calc(100vh-60px)] overflow-y-auto bg-white">
      <ul class="w-full px-3 py-4 space-y-1">
        <!-- quickLinks will be inserted inline within the menu list (see insertionIndex) -->

        <template v-for="(mainMenu, Index) in orderedMenus" :key="childUniqueKey(mainMenu)">
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
                  getActiveRoute(mainMenu) ? 'bg-emerald-500 text-white font-medium border-l-3 border-emerald-600' : 'bg-white text-gray-700'
                ]">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="mainMenu.icon" size="18" :class="[
                      'transition-colors duration-200',
                      getActiveRoute(mainMenu) ? 'text-white' : 'text-gray-500 group-hover:text-emerald-700'
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
                            ? 'bg-emerald-500 text-white font-medium'
                            : 'bg-white text-gray-700 hover:bg-white hover:text-emerald-700',
                          'flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-md mx-1',
                          sideBar ? '' : 'ml-3',
                        ]">
                          <FeatherIcon :name="submenu.icon" size="16" :class="isRouteActive(submenu.route) ? 'text-white' : 'text-gray-500'" />
                          <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(submenu) }}</span>
                        </a>
                      </template>
                      <template v-else>
                        <Link :href="getMenuHref(submenu.route)" :data-menu-route="normalizeRouteName(submenu.route)" @click="handleMenuClick($event, submenu.route)" :class="[
                          isRouteActive(submenu.route)
                            ? 'bg-emerald-500 text-white font-medium'
                            : 'bg-white text-gray-700 hover:bg-white hover:text-emerald-700',
                          'flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-md mx-1',
                          sideBar ? '' : 'ml-3',
                        ]">
                          <FeatherIcon :name="submenu.icon" size="16" :class="isRouteActive(submenu.route) ? 'text-white' : 'text-gray-500'" />
                          <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(submenu) }}</span>
                        </Link>
                      </template>
                    </template>
                    <template v-else>
                      <a href="#" @click.prevent="navigateFallback(submenu.route, $event)" :class="[
                        'bg-white text-gray-700 hover:bg-white hover:text-emerald-700',
                        'flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-md mx-1',
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
                    ? 'bg-emerald-500 text-white font-medium border-l-3 border-emerald-600'
                    : 'bg-white text-gray-700 hover:bg-white hover:text-emerald-700',
                  navSidebar,
                ]" class="w-full">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="mainMenu.icon" size="18" :class="[
                      'transition-colors duration-200',
                      isRouteActive(mainMenu.route) ? 'text-white' : 'text-gray-500 group-hover:text-emerald-700'
                    ]" />
                  </div>
                  <span v-if="!sideBar" class="truncate text-sm">{{ getMenuDisplayName(mainMenu) }}</span>
                </a>
              </template>
              <template v-else>
                <Link :href="getMenuHref(mainMenu.route)" :data-menu-route="normalizeRouteName(mainMenu.route)" @click="handleMenuClick($event, mainMenu.route)" :class="[
                  isRouteActive(mainMenu.route)
                    ? 'bg-emerald-500 text-white font-medium border-l-3 border-emerald-600'
                    : 'bg-white text-gray-700 hover:bg-white hover:text-emerald-700',
                  navSidebar,
                ]" class="w-full">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="mainMenu.icon" size="18" :class="[
                      'transition-colors duration-200',
                      isRouteActive(mainMenu.route) ? 'text-white' : 'text-gray-500 group-hover:text-emerald-700'
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
                    ? 'bg-emerald-500 text-white font-medium border-l-3 border-emerald-600'
                    : 'bg-white text-gray-700 hover:bg-white hover:text-emerald-700',
                  navSidebar,
                ]" class="w-full">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="quickLink.icon" size="18" :class="[
                      'transition-colors duration-200',
                      isRouteActive(quickLink.route) ? 'text-white' : 'text-gray-500 group-hover:text-emerald-700'
                    ]" />
                  </div>
                  <span v-if="!sideBar" class="truncate text-sm">{{ quickLink.label }}</span>
                </a>
              </template>

              <template v-else>
                <Link :href="getMenuHref(quickLink.route)" :data-menu-route="normalizeRouteName(quickLink.route)" @click="handleMenuClick($event, quickLink.route)" :class="[
                  isRouteActive(quickLink.route)
                    ? 'bg-emerald-500 text-white font-medium border-l-3 border-emerald-600'
                    : 'bg-white text-gray-700 hover:bg-white hover:text-emerald-700',
                  navSidebar,
                ]" class="w-full">
                  <div class="flex items-center justify-center w-5 h-5">
                    <FeatherIcon :name="quickLink.icon" size="18" :class="[
                      'transition-colors duration-200',
                      isRouteActive(quickLink.route) ? 'text-white' : 'text-gray-500 group-hover:text-emerald-700'
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

.bg-emerald-50 {
  background-color: var(--app-theme-soft) !important;
}

.bg-gray-100,
.bg-gray-50 {
  background-color: color-mix(in srgb, var(--app-theme-soft) 36%, #f8fafc) !important;
}

.bg-white {
  background-color: #ffffff !important;
}

.bg-emerald-500 {
  background-color: #10b981 !important;
}

.border-gray-200 {
  border-color: color-mix(in srgb, var(--app-theme-primary) 20%, #cbd5e1) !important;
}

.text-gray-500,
.text-gray-700,
.text-gray-800 {
  color: color-mix(in srgb, var(--app-theme-contrast) 70%, #334155) !important;
}

.text-emerald-700,
.text-emerald-600 {
  color: var(--app-theme-primary) !important;
}

.border-emerald-500 {
  border-color: var(--app-theme-primary) !important;
}

.hover\:bg-gray-50:hover {
  background-color: color-mix(in srgb, var(--app-theme-soft) 62%, white) !important;
}

.hover\:bg-emerald-50:hover {
  background-color: var(--app-theme-soft) !important;
}

.hover\:bg-white:hover {
  background-color: #ffffff !important;
}

.hover\:text-emerald-700:hover,
.hover\:text-emerald-600:hover {
  color: var(--app-theme-primary) !important;
}

.group:hover .group-hover\:text-emerald-700 {
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