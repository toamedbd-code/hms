import { ref, computed, onMounted, onUnmounted, mergeProps, unref, useSSRContext, onBeforeUnmount, reactive, resolveComponent, withCtx, createTextVNode, toDisplayString, openBlock, createBlock, createCommentVNode, createVNode, Fragment, renderList, withModifiers } from "vue";
import { ssrRenderAttrs, ssrRenderClass, ssrRenderSlot, ssrRenderStyle, ssrRenderComponent, ssrInterpolate, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { usePage, Link, Head } from "@inertiajs/vue3";
import mitt from "mitt";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
import { _ as _sfc_main$5, a as _sfc_main$6 } from "./DropdownLink-64712462.mjs";
import { useDark, useToggle } from "@vueuse/core";
const _sfc_main$4 = {
  __name: "SideBarSubMenu",
  __ssrInlineRender: true,
  props: {
    align: {
      type: String,
      default: "right"
    },
    width: {
      type: String,
      default: "48"
    },
    contentClasses: {
      type: Array,
      default: () => ["py-0", "text-gray-700"]
    },
    activeRoute: {
      required: true
    }
  },
  setup(__props) {
    const props = __props;
    let open = ref(false);
    const isActiveRoute = computed(() => route().current() === props.activeRoute);
    const closeOnEscape = (e) => {
      if (open.value && e.key === "Escape") {
        open.value = false;
      }
    };
    onMounted(() => {
      document.addEventListener("keydown", closeOnEscape);
      if (isActiveRoute.value) {
        open.value = true;
      }
    });
    onUnmounted(() => document.removeEventListener("keydown", closeOnEscape));
    const widthClass = computed(() => {
      return {
        48: "w-full"
      }[props.width.toString()];
    });
    const alignmentClasses = computed(() => {
      if (props.align === "left") {
        return "ltr:origin-top-left rtl:origin-top-right start-0";
      }
      if (props.align === "right") {
        return "ltr:origin-top-right rtl:origin-top-left end-0";
      }
      return "origin-top";
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "relative" }, _attrs))}><div class="${ssrRenderClass(unref(open) ? "bg-blue-50 text-blue-700 border-l-2 border-blue-600" : "")}">`);
      ssrRenderSlot(_ctx.$slots, "trigger", {}, null, _push, _parent);
      _push(`</div><div style="${ssrRenderStyle(unref(open) ? null : { display: "none" })}" class=""></div><div style="${ssrRenderStyle([
        unref(open) ? null : { display: "none" },
        { "display": "none" }
      ])}" class="${ssrRenderClass([[widthClass.value, alignmentClasses.value], "rounded-md"])}"><div class="${ssrRenderClass([...__props.contentClasses, "bg-white rounded-md border border-gray-100"])}">`);
      ssrRenderSlot(_ctx.$slots, "content", {}, null, _push, _parent);
      _push(`</div></div></div>`);
    };
  }
};
const _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/SideBarSubMenu.vue");
  return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
const eventBus = mitt();
const Sidebar_vue_vue_type_style_index_0_scoped_936ada36_lang = "";
const _sfc_main$3 = {
  __name: "Sidebar",
  __ssrInlineRender: true,
  setup(__props) {
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
      "flex items-center p-3 space-x-3 rounded-md cursor-pointer hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
    ]);
    const hasRolePermission = (permissionName) => {
      var _a;
      const admin = (_a = page.props.auth) == null ? void 0 : _a.admin;
      if (!admin || !admin.roles)
        return false;
      return admin.roles.some(
        (role) => {
          var _a2;
          return (_a2 = role.permissions) == null ? void 0 : _a2.some(
            (permission) => permission.name === permissionName
          );
        }
      );
    };
    const filteredMenus = computed(() => {
      var _a;
      return (((_a = page.props.auth) == null ? void 0 : _a.sideMenus) ?? []).map((menu) => {
        const hasMainPermission = hasRolePermission(menu.permission_name);
        if (!hasMainPermission)
          return null;
        const filteredChildren = (menu.childrens ?? []).filter(
          (child) => hasRolePermission(child.permission_name) && child.route
        );
        if (menu.route || filteredChildren.length > 0) {
          return {
            ...menu,
            childrens: filteredChildren
          };
        }
        return null;
      }).filter(Boolean);
    });
    const getActiveRoute = (mainMenu) => {
      if (!mainMenu.childrens)
        return null;
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
    return (_ctx, _push, _parent, _attrs) => {
      const _component_FeatherIcon = resolveComponent("FeatherIcon");
      _push(`<div${ssrRenderAttrs(mergeProps({ class: sidebarClasses.value }, _attrs))} data-v-936ada36><div class="w-full flex items-center h-[50px] border-b border-gray-200 bg-gray-100 px-4" data-v-936ada36>`);
      _push(ssrRenderComponent(unref(Link), {
        href: _ctx.route("backend.dashboard"),
        class: "text-xl font-bold text-gray-800 hover:text-blue-600 transition-colors duration-200"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          var _a, _b, _c, _d;
          if (_push2) {
            _push2(`${ssrInterpolate(sideBar.value ? (_a = webSetting.value) == null ? void 0 : _a.company_short_name : ((_b = webSetting.value) == null ? void 0 : _b.company_name) || "Company Name")} `);
            if (!sideBar.value) {
              _push2(`<span class="block text-xs font-normal text-gray-500 mt-0.5" data-v-936ada36${_scopeId}></span>`);
            } else {
              _push2(`<!---->`);
            }
          } else {
            return [
              createTextVNode(toDisplayString(sideBar.value ? (_c = webSetting.value) == null ? void 0 : _c.company_short_name : ((_d = webSetting.value) == null ? void 0 : _d.company_name) || "Company Name") + " ", 1),
              !sideBar.value ? (openBlock(), createBlock("span", {
                key: 0,
                class: "block text-xs font-normal text-gray-500 mt-0.5"
              })) : createCommentVNode("", true)
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div><div style="${ssrRenderStyle({ "width": "inherit" })}" class="h-[calc(100vh-60px)] overflow-y-auto bg-gray-100" data-v-936ada36><ul class="w-full px-3 py-4 space-y-1" data-v-936ada36><!--[-->`);
      ssrRenderList(filteredMenus.value, (mainMenu, Index) => {
        var _a;
        _push(`<!--[-->`);
        if (((_a = mainMenu.childrens) == null ? void 0 : _a.length) > 0) {
          _push(`<li class="${ssrRenderClass([{ "flex justify-center": sideBar.value }, "relative"])}" data-v-936ada36>`);
          _push(ssrRenderComponent(_sfc_main$4, {
            align: "left",
            activeRoute: getActiveRoute(mainMenu)
          }, {
            trigger: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(`<div class="${ssrRenderClass([
                  navSidebar,
                  getActiveRoute(mainMenu) ? "bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500" : ""
                ])}" data-v-936ada36${_scopeId}><div class="flex items-center justify-center w-5 h-5" data-v-936ada36${_scopeId}>`);
                _push2(ssrRenderComponent(_component_FeatherIcon, {
                  name: mainMenu.icon,
                  size: "18",
                  class: [
                    "transition-colors duration-200",
                    getActiveRoute(mainMenu) ? "text-blue-600" : "text-gray-500 group-hover:text-blue-600"
                  ]
                }, null, _parent2, _scopeId));
                _push2(`</div>`);
                if (!sideBar.value) {
                  _push2(`<span class="truncate font-medium text-sm" data-v-936ada36${_scopeId}>${ssrInterpolate(mainMenu.name)}</span>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</div>`);
              } else {
                return [
                  createVNode("div", {
                    class: [
                      navSidebar,
                      getActiveRoute(mainMenu) ? "bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500" : ""
                    ]
                  }, [
                    createVNode("div", { class: "flex items-center justify-center w-5 h-5" }, [
                      createVNode(_component_FeatherIcon, {
                        name: mainMenu.icon,
                        size: "18",
                        class: [
                          "transition-colors duration-200",
                          getActiveRoute(mainMenu) ? "text-blue-600" : "text-gray-500 group-hover:text-blue-600"
                        ]
                      }, null, 8, ["name", "class"])
                    ]),
                    !sideBar.value ? (openBlock(), createBlock("span", {
                      key: 0,
                      class: "truncate font-medium text-sm"
                    }, toDisplayString(mainMenu.name), 1)) : createCommentVNode("", true)
                  ], 2)
                ];
              }
            }),
            content: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(`<ul class="submenu bg-gray-100 border border-gray-200 rounded-md py-1" data-v-936ada36${_scopeId}><!--[-->`);
                ssrRenderList(mainMenu.childrens, (submenu, subIndex) => {
                  _push2(`<li data-v-936ada36${_scopeId}>`);
                  if (submenu.route) {
                    _push2(ssrRenderComponent(unref(Link), {
                      href: _ctx.route(submenu.route),
                      class: [
                        _ctx.route().current(submenu.route) ? "bg-blue-50 text-blue-600 font-medium" : "text-gray-700 hover:bg-gray-50",
                        "flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-sm mx-1",
                        sideBar.value ? "" : "ml-3"
                      ]
                    }, {
                      default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                        if (_push3) {
                          _push3(ssrRenderComponent(_component_FeatherIcon, {
                            name: submenu.icon,
                            size: "16",
                            class: "text-gray-500"
                          }, null, _parent3, _scopeId2));
                          if (!sideBar.value) {
                            _push3(`<span class="truncate text-sm" data-v-936ada36${_scopeId2}>${ssrInterpolate(submenu.name)}</span>`);
                          } else {
                            _push3(`<!---->`);
                          }
                        } else {
                          return [
                            createVNode(_component_FeatherIcon, {
                              name: submenu.icon,
                              size: "16",
                              class: "text-gray-500"
                            }, null, 8, ["name"]),
                            !sideBar.value ? (openBlock(), createBlock("span", {
                              key: 0,
                              class: "truncate text-sm"
                            }, toDisplayString(submenu.name), 1)) : createCommentVNode("", true)
                          ];
                        }
                      }),
                      _: 2
                    }, _parent2, _scopeId));
                  } else {
                    _push2(`<!---->`);
                  }
                  _push2(`</li>`);
                });
                _push2(`<!--]--></ul>`);
              } else {
                return [
                  createVNode("ul", { class: "submenu bg-gray-100 border border-gray-200 rounded-md py-1" }, [
                    (openBlock(true), createBlock(Fragment, null, renderList(mainMenu.childrens, (submenu, subIndex) => {
                      return openBlock(), createBlock("li", { key: subIndex }, [
                        submenu.route ? (openBlock(), createBlock(unref(Link), {
                          key: 0,
                          href: _ctx.route(submenu.route),
                          class: [
                            _ctx.route().current(submenu.route) ? "bg-blue-50 text-blue-600 font-medium" : "text-gray-700 hover:bg-gray-50",
                            "flex items-center px-4 py-2 space-x-3 transition-colors duration-200 rounded-sm mx-1",
                            sideBar.value ? "" : "ml-3"
                          ]
                        }, {
                          default: withCtx(() => [
                            createVNode(_component_FeatherIcon, {
                              name: submenu.icon,
                              size: "16",
                              class: "text-gray-500"
                            }, null, 8, ["name"]),
                            !sideBar.value ? (openBlock(), createBlock("span", {
                              key: 0,
                              class: "truncate text-sm"
                            }, toDisplayString(submenu.name), 1)) : createCommentVNode("", true)
                          ]),
                          _: 2
                        }, 1032, ["href", "class"])) : createCommentVNode("", true)
                      ]);
                    }), 128))
                  ])
                ];
              }
            }),
            _: 2
          }, _parent));
          _push(`</li>`);
        } else {
          _push(`<li class="${ssrRenderClass({ "flex justify-center": sideBar.value })}" data-v-936ada36>`);
          if (mainMenu.route) {
            _push(ssrRenderComponent(unref(Link), {
              href: _ctx.route(mainMenu.route),
              class: [[
                _ctx.route().current(mainMenu.route) ? "bg-blue-50 text-blue-600 font-medium border-l-3 border-blue-500" : "text-gray-700 hover:bg-blue-50 hover:text-blue-600",
                navSidebar
              ], "w-full"]
            }, {
              default: withCtx((_, _push2, _parent2, _scopeId) => {
                if (_push2) {
                  _push2(`<div class="flex items-center justify-center w-5 h-5" data-v-936ada36${_scopeId}>`);
                  _push2(ssrRenderComponent(_component_FeatherIcon, {
                    name: mainMenu.icon,
                    size: "18",
                    class: [
                      "transition-colors duration-200",
                      _ctx.route().current(mainMenu.route) ? "text-blue-600" : "text-gray-500 group-hover:text-blue-600"
                    ]
                  }, null, _parent2, _scopeId));
                  _push2(`</div>`);
                  if (!sideBar.value) {
                    _push2(`<span class="truncate text-sm" data-v-936ada36${_scopeId}>${ssrInterpolate(mainMenu.name)}</span>`);
                  } else {
                    _push2(`<!---->`);
                  }
                } else {
                  return [
                    createVNode("div", { class: "flex items-center justify-center w-5 h-5" }, [
                      createVNode(_component_FeatherIcon, {
                        name: mainMenu.icon,
                        size: "18",
                        class: [
                          "transition-colors duration-200",
                          _ctx.route().current(mainMenu.route) ? "text-blue-600" : "text-gray-500 group-hover:text-blue-600"
                        ]
                      }, null, 8, ["name", "class"])
                    ]),
                    !sideBar.value ? (openBlock(), createBlock("span", {
                      key: 0,
                      class: "truncate text-sm"
                    }, toDisplayString(mainMenu.name), 1)) : createCommentVNode("", true)
                  ];
                }
              }),
              _: 2
            }, _parent));
          } else {
            _push(`<!---->`);
          }
          _push(`</li>`);
        }
        _push(`<!--]-->`);
      });
      _push(`<!--]--></ul></div></div>`);
    };
  }
};
const _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/Partials/Sidebar.vue");
  return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
const Sidebar = /* @__PURE__ */ _export_sfc(_sfc_main$3, [["__scopeId", "data-v-936ada36"]]);
const _sfc_main$2 = {
  __name: "Navbar",
  __ssrInlineRender: true,
  setup(__props) {
    const isDark = useDark();
    useToggle(isDark);
    const sideBarFlag = ref(false);
    ref(false);
    const logout = () => {
      window.open(route("backend.auth.logout"), "_self");
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "relative w-full" }, _attrs))}><div class="${ssrRenderClass(["absolute", "w-full", { "md:pl-[70px]": sideBarFlag.value, "pl-[240px]": !sideBarFlag.value }])}"><div class="flex px-4 items-center justify-between w-full border-b border-gray-200 bg-gray-100 py-3 h-[50px]"><div><button type="button" class="p-2 rounded text-gray-500 hover:text-blue-600 hover:bg-gray-100 transition-colors duration-200"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"></path></svg></button></div><div><ul class="flex items-center space-x-2"><li><button type="button" class="p-2 rounded text-gray-500 hover:text-blue-600 hover:bg-gray-100 transition-colors duration-200"${ssrRenderAttr("title", unref(isDark) ? "Switch to Light Mode" : "Switch to Dark Mode")}>`);
      if (unref(isDark)) {
        _push(`<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"></path></svg>`);
      } else {
        _push(`<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"></path></svg>`);
      }
      _push(`</button></li><li class="relative"><a href="#" class="p-2 rounded text-gray-500 hover:text-blue-600 hover:bg-gray-100 transition-colors duration-200 block relative"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"></path></svg></a></li><li class="relative"><div class="cursor-pointer p-2 rounded text-gray-500 hover:text-blue-600 hover:bg-gray-100 transition-colors duration-200"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"></path></svg></div></li><li><div class="relative"><div class="flex items-center text-gray-500 hover:text-blue-600 transition-colors duration-200 p-2 rounded cursor-pointer">`);
      _push(ssrRenderComponent(_sfc_main$5, { align: "right" }, {
        trigger: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"${_scopeId}></path></svg>`);
          } else {
            return [
              (openBlock(), createBlock("svg", {
                xmlns: "http://www.w3.org/2000/svg",
                fill: "none",
                viewBox: "0 0 24 24",
                "stroke-width": "1.5",
                stroke: "currentColor",
                class: "w-5 h-5"
              }, [
                createVNode("path", {
                  "stroke-linecap": "round",
                  "stroke-linejoin": "round",
                  d: "M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                })
              ]))
            ];
          }
        }),
        content: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(_sfc_main$6, null, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Profile `);
                } else {
                  return [
                    createTextVNode(" Profile ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`<div class="border-t border-gray-200"${_scopeId}></div><form${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$6, { as: "button" }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Log Out `);
                } else {
                  return [
                    createTextVNode(" Log Out ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</form>`);
          } else {
            return [
              createVNode(_sfc_main$6, null, {
                default: withCtx(() => [
                  createTextVNode(" Profile ")
                ]),
                _: 1
              }),
              createVNode("div", { class: "border-t border-gray-200" }),
              createVNode("form", {
                onSubmit: withModifiers(logout, ["prevent"])
              }, [
                createVNode(_sfc_main$6, { as: "button" }, {
                  default: withCtx(() => [
                    createTextVNode(" Log Out ")
                  ]),
                  _: 1
                })
              ], 32)
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div></div></li></ul></div></div></div></div>`);
    };
  }
};
const _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/Partials/Navbar.vue");
  return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
const _sfc_main$1 = {
  __name: "Breadcrumb",
  __ssrInlineRender: true,
  props: ["breadcrumbs"],
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      if (__props.breadcrumbs) {
        _push(`<header${ssrRenderAttrs(mergeProps({ class: "duration-1000 bg-white rounded-md dark:bg-slate-900 shadow-md shadow-gray-800/50" }, _attrs))}><div class="px-4 py-2"><ul class="flex items-center space-x-2"><!--[-->`);
        ssrRenderList(__props.breadcrumbs, (breadcrumb, breadcrumbIndex) => {
          var _a;
          _push(`<!--[--><li>`);
          if (breadcrumb.link) {
            _push(ssrRenderComponent(unref(Link), {
              href: breadcrumb.link,
              class: "text-sm font-semibold leading-tight transition duration-1000 ease-in-out text-slate-900 dark:text-slate-300"
            }, {
              default: withCtx((_, _push2, _parent2, _scopeId) => {
                if (_push2) {
                  _push2(`${ssrInterpolate(breadcrumb.title)}`);
                } else {
                  return [
                    createTextVNode(toDisplayString(breadcrumb.title), 1)
                  ];
                }
              }),
              _: 2
            }, _parent));
          } else {
            _push(`<a href="javascript:void(0)" class="text-sm font-semibold leading-tight transition duration-1000 ease-in-out text-slate-900 dark:text-slate-300">${ssrInterpolate(breadcrumb.title)}</a>`);
          }
          _push(`</li>`);
          if (breadcrumbIndex != parseInt(((_a = __props.breadcrumbs) == null ? void 0 : _a.length) ?? 0) - 1) {
            _push(`<li class="text-sm text-gray-500 dark:text-gray-400">/</li>`);
          } else {
            _push(`<!---->`);
          }
          _push(`<!--]-->`);
        });
        _push(`<!--]--></ul></div></header>`);
      } else {
        _push(`<!---->`);
      }
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/Partials/Breadcrumb.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const BackendLayout_vue_vue_type_style_index_0_lang = "";
const _sfc_main = {
  __name: "BackendLayout",
  __ssrInlineRender: true,
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}>`);
      _push(ssrRenderComponent(unref(Head), {
        title: _ctx.$page.props.pageTitle
      }, null, _parent));
      _push(`<div class="w-full bg-slate-300 dark:bg-slate-950">`);
      _push(ssrRenderComponent(_sfc_main$2, null, null, _parent));
      _push(`<div class="flex w-full">`);
      _push(ssrRenderComponent(Sidebar, null, null, _parent));
      _push(`<div class="w-full h-[calc(100vh-48px)] mt-12 overflow-y-auto p-2 bg-gray-200">`);
      _push(ssrRenderComponent(_sfc_main$1, {
        breadcrumbs: _ctx.$page.props.breadcrumbs
      }, null, _parent));
      _push(`<main>`);
      ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
      _push(`</main></div></div></div></div>`);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/BackendLayout.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as _
};
