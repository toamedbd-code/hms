import { resolveComponent, withCtx, createTextVNode, toDisplayString, createVNode, openBlock, createBlock, Fragment, renderList, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderList, ssrRenderAttr } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import "@inertiajs/vue3";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
const _sfc_main = {
  __name: "LeaveSingleView",
  __ssrInlineRender: true,
  props: {
    leaveDetails: Object,
    pageTitle: String,
    breadcrumbs: Array
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      const _component_router_link = resolveComponent("router-link");
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full p-4 mt-3 bg-white rounded-md shadow-md dark:bg-slate-100"${_scopeId}><h1 class="py-2 text-xl font-bold text-center dark:text-white"${_scopeId}>${ssrInterpolate(__props.pageTitle)}</h1><nav aria-label="Breadcrumb" class="text-center"${_scopeId}><ol class="inline-flex space-x-2 text-gray-500"${_scopeId}><!--[-->`);
            ssrRenderList(__props.breadcrumbs, (breadcrumb, index) => {
              _push2(`<li${_scopeId}>`);
              if (breadcrumb.link) {
                _push2(ssrRenderComponent(_component_router_link, {
                  to: breadcrumb.link
                }, {
                  default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                    if (_push3) {
                      _push3(`${ssrInterpolate(breadcrumb.title)}`);
                    } else {
                      return [
                        createTextVNode(toDisplayString(breadcrumb.title), 1)
                      ];
                    }
                  }),
                  _: 2
                }, _parent2, _scopeId));
              } else {
                _push2(`<span${_scopeId}>${ssrInterpolate(breadcrumb.title)}</span>`);
              }
              _push2(`</li>`);
            });
            _push2(`<!--]--></ol></nav><div class="my-4 p-4 border rounded-lg shadow-md bg-gray-50 dark:bg-gray-800 flex flex-col items-center"${_scopeId}><h2 class="text-lg font-semibold text-center"${_scopeId}>Leave Details</h2><div class="mt-2 text-left"${_scopeId}><p${_scopeId}><strong${_scopeId}>ID:</strong> ${ssrInterpolate(__props.leaveDetails.id)}</p><p${_scopeId}><strong${_scopeId}>Apply Date:</strong> ${ssrInterpolate(__props.leaveDetails.apply_date)}</p><p${_scopeId}><strong${_scopeId}>From:</strong> ${ssrInterpolate(__props.leaveDetails.from)}</p><p${_scopeId}><strong${_scopeId}>To:</strong> ${ssrInterpolate(__props.leaveDetails.to)}</p><p${_scopeId}><strong${_scopeId}>Reason:</strong> ${ssrInterpolate(__props.leaveDetails.reason)}</p><p${_scopeId}><strong${_scopeId}>Status:</strong> ${ssrInterpolate(__props.leaveDetails.status)}</p>`);
            if (__props.leaveDetails.attachment) {
              _push2(`<p${_scopeId}><strong${_scopeId}>Attachment:</strong><a${ssrRenderAttr("href", __props.leaveDetails.attachment)} class="text-blue-500 underline" target="_blank"${_scopeId}>View Attachment</a></p>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<p${_scopeId}><strong${_scopeId}>Created At:</strong> ${ssrInterpolate(__props.leaveDetails.created_at)}</p><p${_scopeId}><strong${_scopeId}>Updated At:</strong> ${ssrInterpolate(__props.leaveDetails.updated_at)}</p>`);
            if (__props.leaveDetails.deleted_at) {
              _push2(`<p${_scopeId}><strong${_scopeId}>Deleted At:</strong> ${ssrInterpolate(__props.leaveDetails.deleted_at)}</p>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full p-4 mt-3 bg-white rounded-md shadow-md dark:bg-slate-100" }, [
                createVNode("h1", { class: "py-2 text-xl font-bold text-center dark:text-white" }, toDisplayString(__props.pageTitle), 1),
                createVNode("nav", {
                  "aria-label": "Breadcrumb",
                  class: "text-center"
                }, [
                  createVNode("ol", { class: "inline-flex space-x-2 text-gray-500" }, [
                    (openBlock(true), createBlock(Fragment, null, renderList(__props.breadcrumbs, (breadcrumb, index) => {
                      return openBlock(), createBlock("li", { key: index }, [
                        breadcrumb.link ? (openBlock(), createBlock(_component_router_link, {
                          key: 0,
                          to: breadcrumb.link
                        }, {
                          default: withCtx(() => [
                            createTextVNode(toDisplayString(breadcrumb.title), 1)
                          ]),
                          _: 2
                        }, 1032, ["to"])) : (openBlock(), createBlock("span", { key: 1 }, toDisplayString(breadcrumb.title), 1))
                      ]);
                    }), 128))
                  ])
                ]),
                createVNode("div", { class: "my-4 p-4 border rounded-lg shadow-md bg-gray-50 dark:bg-gray-800 flex flex-col items-center" }, [
                  createVNode("h2", { class: "text-lg font-semibold text-center" }, "Leave Details"),
                  createVNode("div", { class: "mt-2 text-left" }, [
                    createVNode("p", null, [
                      createVNode("strong", null, "ID:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.id), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Apply Date:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.apply_date), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "From:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.from), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "To:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.to), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Reason:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.reason), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Status:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.status), 1)
                    ]),
                    __props.leaveDetails.attachment ? (openBlock(), createBlock("p", { key: 0 }, [
                      createVNode("strong", null, "Attachment:"),
                      createVNode("a", {
                        href: __props.leaveDetails.attachment,
                        class: "text-blue-500 underline",
                        target: "_blank"
                      }, "View Attachment", 8, ["href"])
                    ])) : createCommentVNode("", true),
                    createVNode("p", null, [
                      createVNode("strong", null, "Created At:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.created_at), 1)
                    ]),
                    createVNode("p", null, [
                      createVNode("strong", null, "Updated At:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.updated_at), 1)
                    ]),
                    __props.leaveDetails.deleted_at ? (openBlock(), createBlock("p", { key: 1 }, [
                      createVNode("strong", null, "Deleted At:"),
                      createTextVNode(" " + toDisplayString(__props.leaveDetails.deleted_at), 1)
                    ])) : createCommentVNode("", true)
                  ])
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/LeaveType/LeaveSingleView.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
