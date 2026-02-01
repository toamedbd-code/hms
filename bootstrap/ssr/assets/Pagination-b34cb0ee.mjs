import { mergeProps, useSSRContext, unref, withCtx, createTextVNode, toDisplayString } from "vue";
import { ssrRenderAttrs, ssrRenderList, ssrInterpolate, ssrRenderClass, ssrRenderAttr, ssrRenderComponent } from "vue/server-renderer";
import { s as statusChangeConfirmation, b as deleteConfirmation } from "./responseMessage-d505224b.mjs";
import $ from "jquery";
import { Link } from "@inertiajs/vue3";
const _sfc_main$1 = {
  __name: "BaseTable",
  __ssrInlineRender: true,
  setup(__props) {
    $(function() {
      $(".statusChange").on("click", function(e) {
        e.preventDefault();
        const url = $(this).attr("href");
        statusChangeConfirmation(url);
      });
      $(".deleteButton").on("click", function(e) {
        e.preventDefault();
        const url = $(this).attr("href");
        deleteConfirmation(url);
      });
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<table${ssrRenderAttrs(mergeProps({ class: "w-full text-gray-700 border-collapse" }, _attrs))}><thead class="text-gray-700 bg-gray-100"><tr class="text-[12px]"><!--[-->`);
      ssrRenderList(_ctx.$page.props.tableHeaders, (header) => {
        _push(`<th scope="col" class="px-6 py-3 border border-gray-300">${ssrInterpolate(header)}</th>`);
      });
      _push(`<!--]--></tr></thead><tbody class="text-[12px] 2xl:text-[14px]"><!--[-->`);
      ssrRenderList(_ctx.$page.props.datas.data, (data, dataIndex) => {
        _push(`<tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors duration-200"><!--[-->`);
        ssrRenderList(_ctx.$page.props.dataFields, (dateField, dateFieldIndex) => {
          _push(`<td class="${ssrRenderClass([dateField.class, "px-4 py-2 border border-gray-200"])}"><p>${data[dateField.fieldName] ?? "" ?? ""}</p></td>`);
        });
        _push(`<!--]-->`);
        if (data.links) {
          _push(`<td class="px-4 py-2 border border-gray-200"><div class="flex justify-center w-full space-x-1"><!--[-->`);
          ssrRenderList(data.links, (linkInfo, linkIndex) => {
            _push(`<!--[-->`);
            if (linkInfo.actionName) {
              _push(`<button class="${ssrRenderClass([linkInfo.linkClass, "px-3 py-1 rounded hover:bg-green-500 transition-colors duration-200"])}"><span>${linkInfo.linkLabel ?? ""}</span></button>`);
            } else {
              _push(`<a${ssrRenderAttr("href", linkInfo.link)} class="${ssrRenderClass([linkInfo.linkClass, "px-3 py-1 rounded hover:bg-green-500 transition-colors duration-200"])}"${ssrRenderAttr("target", linkInfo.target || "_self")}><span>${linkInfo.linkLabel ?? ""}</span></a>`);
            }
            _push(`<!--]-->`);
          });
          _push(`<!--]--></div></td>`);
        } else {
          _push(`<!---->`);
        }
        _push(`</tr>`);
      });
      _push(`<!--]--></tbody></table>`);
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/BaseTable.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const _sfc_main = {
  __name: "Pagination",
  __ssrInlineRender: true,
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      var _a, _b, _c;
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "grid grid-cols-1 gap-4 pt-2 my-4 md:grid-cols-2 justify-items-center" }, _attrs))}><div class="w-full text-center md:text-start"><p class="text-sm text-gray-600">${ssrInterpolate(`Displaying ${((_a = _ctx.$page.props.datas) == null ? void 0 : _a.from) ?? 0} to ${((_b = _ctx.$page.props.datas) == null ? void 0 : _b.to) ?? 0} of ${((_c = _ctx.$page.props.datas) == null ? void 0 : _c.total) ?? 0} items`)}</p></div><nav class="w-full dark:text-gray-50"><ul class="flex items-center justify-center space-x-2 md:justify-end"><li class="${ssrRenderClass({ "disabled": _ctx.$page.props.datas.links[0].url === null })}">`);
      if (_ctx.$page.props.datas.links[0].url) {
        _push(ssrRenderComponent(unref(Link), {
          href: _ctx.$page.props.datas.links[0].url,
          class: "px-3 py-2 transition-colors border border-gray-300 rounded-md cursor-pointer hover:bg-gray-200 focus:outline-none focus:ring focus:border-blue-300",
          rel: "prev"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(` « `);
            } else {
              return [
                createTextVNode(" « ")
              ];
            }
          }),
          _: 1
        }, _parent));
      } else {
        _push(`<span class="px-3 py-2 border border-gray-300 rounded-md">«</span>`);
      }
      _push(`</li><!--[-->`);
      ssrRenderList(_ctx.$page.props.datas.links.slice(1, -1), (link) => {
        _push(`<li>`);
        if (link.url) {
          _push(ssrRenderComponent(unref(Link), {
            href: link.url,
            class: ["px-3 py-2 transition-colors border border-gray-300 rounded-md cursor-pointer dark:hover:text-slate-800 hover:bg-gray-200 focus:outline-none focus:ring focus:border-blue-300", { "bg-gray-200 text-slate-800": link.active }]
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(`${ssrInterpolate(link.label)}`);
              } else {
                return [
                  createTextVNode(toDisplayString(link.label), 1)
                ];
              }
            }),
            _: 2
          }, _parent));
        } else {
          _push(`<span class="px-3 py-2 border border-gray-300 rounded-md">${ssrInterpolate(link.label)}</span>`);
        }
        _push(`</li>`);
      });
      _push(`<!--]--><li class="${ssrRenderClass({ "disabled": _ctx.$page.props.datas.links[_ctx.$page.props.datas.links.length - 1].url === null })}">`);
      if (_ctx.$page.props.datas.links[_ctx.$page.props.datas.links.length - 1].url) {
        _push(ssrRenderComponent(unref(Link), {
          href: _ctx.$page.props.datas.links[_ctx.$page.props.datas.links.length - 1].url,
          class: "px-3 py-2 transition-colors border border-gray-300 rounded-md cursor-pointer hover:bg-gray-200 focus:outline-none focus:ring focus:border-blue-300",
          rel: "next"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(` » `);
            } else {
              return [
                createTextVNode(" » ")
              ];
            }
          }),
          _: 1
        }, _parent));
      } else {
        _push(`<span class="px-3 py-2 border border-gray-300 rounded-md">»</span>`);
      }
      _push(`</li></ul></nav></div>`);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Pagination.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main$1 as _,
  _sfc_main as a
};
