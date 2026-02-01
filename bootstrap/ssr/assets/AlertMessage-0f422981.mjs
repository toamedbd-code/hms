import { ssrRenderAttrs, ssrInterpolate } from "vue/server-renderer";
import { useSSRContext } from "vue";
import { _ as _export_sfc } from "./_plugin-vue_export-helper-cc2b3d55.mjs";
const _sfc_main = {};
function _sfc_ssrRender(_ctx, _push, _parent, _attrs) {
  _push(`<div${ssrRenderAttrs(_attrs)}>`);
  if (_ctx.$page.props.flash.successMessage) {
    _push(`<div class="p-4 mb-1 text-green-600 bg-green-300 border-green-500 shadow-md alert">${ssrInterpolate(_ctx.$page.props.flash.successMessage)}</div>`);
  } else {
    _push(`<!---->`);
  }
  if (_ctx.$page.props.flash.errorMessage) {
    _push(`<div class="p-4 mb-1 text-white bg-red-500 bg-opacity-75 shadow-md alert">${ssrInterpolate(_ctx.$page.props.flash.errorMessage)}</div>`);
  } else {
    _push(`<!---->`);
  }
  if (_ctx.$page.props.flash.warningMessage) {
    _push(`<div class="p-4 mb-1 text-yellow-500 bg-yellow-100 border-yellow-300 shadow-md alert">${ssrInterpolate(_ctx.$page.props.flash.warningMessage)}</div>`);
  } else {
    _push(`<!---->`);
  }
  _push(`</div>`);
}
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/AlertMessage.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const AlertMessage = /* @__PURE__ */ _export_sfc(_sfc_main, [["ssrRender", _sfc_ssrRender]]);
export {
  AlertMessage as A
};
