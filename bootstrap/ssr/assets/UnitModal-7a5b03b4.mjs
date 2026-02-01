import { computed, watch, mergeProps, withCtx, unref, createTextVNode, createVNode, withModifiers, withDirectives, vModelText, openBlock, createBlock, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderAttr, ssrRenderClass } from "vue/server-renderer";
import { useForm } from "@inertiajs/vue3";
import { _ as _sfc_main$3 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$5 } from "./PrimaryButton-b82fb16e.mjs";
import { _ as _sfc_main$4 } from "./SecondaryButton-0974b11b.mjs";
import { _ as _sfc_main$1 } from "./Modal-452973b5.mjs";
import { a as displayWarning, d as displayResponse } from "./responseMessage-d505224b.mjs";
const _sfc_main = {
  __name: "UnitModal",
  __ssrInlineRender: true,
  props: {
    show: Boolean,
    existingUnits: {
      type: Array,
      default: () => []
    }
  },
  emits: ["close", "unit-created"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const emit = __emit;
    const form = useForm({
      name: ""
    });
    const isDuplicate = computed(() => {
      if (!form.name)
        return false;
      return props.existingUnits.some(
        (unit) => unit.name.toLowerCase() === form.name.toLowerCase()
      );
    });
    watch(() => props.show, (newVal) => {
      if (newVal) {
        form.reset();
      }
    });
    const submit = () => {
      if (isDuplicate.value) {
        displayWarning({ message: "A unit with this name already exists." });
        return;
      }
      form.post(route("backend.pathologyunit.store"), {
        onSuccess: (response) => {
          form.reset();
          displayResponse(response);
          emit("unit-created", response);
          emit("close");
        },
        onError: (errors) => {
          displayWarning(errors);
        }
      });
    };
    const close = () => {
      form.reset();
      emit("close");
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, mergeProps({
        show: __props.show,
        onClose: close,
        "max-width": "lg"
      }, _attrs), {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="p-6"${_scopeId}><h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4"${_scopeId}> Add New Unit </h2><form${_scopeId}><div class="grid grid-cols-1 gap-4"${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Unit Name*"
            }, null, _parent2, _scopeId));
            _push2(`<input id="name" type="text" required${ssrRenderAttr("value", unref(form).name)} class="${ssrRenderClass([{ "border-red-500": isDuplicate.value }, "block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"])}"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            if (isDuplicate.value) {
              _push2(`<p class="mt-1 text-sm text-red-600"${_scopeId}> A unit with this name already exists. </p>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div><div class="mt-6 flex justify-end space-x-3"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, { onClick: close }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Cancel `);
                } else {
                  return [
                    createTextVNode(" Cancel ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$5, {
              type: "submit",
              class: { "opacity-25": unref(form).processing },
              disabled: unref(form).processing || isDuplicate.value
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Create Unit `);
                } else {
                  return [
                    createTextVNode(" Create Unit ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode("div", { class: "p-6" }, [
                createVNode("h2", { class: "text-lg font-medium text-gray-900 dark:text-gray-100 mb-4" }, " Add New Unit "),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"])
                }, [
                  createVNode("div", { class: "grid grid-cols-1 gap-4" }, [
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Unit Name*"
                      }),
                      withDirectives(createVNode("input", {
                        id: "name",
                        type: "text",
                        required: "",
                        class: ["block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600", { "border-red-500": isDuplicate.value }],
                        "onUpdate:modelValue": ($event) => unref(form).name = $event
                      }, null, 10, ["onUpdate:modelValue"]), [
                        [vModelText, unref(form).name]
                      ]),
                      createVNode(_sfc_main$3, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"]),
                      isDuplicate.value ? (openBlock(), createBlock("p", {
                        key: 0,
                        class: "mt-1 text-sm text-red-600"
                      }, " A unit with this name already exists. ")) : createCommentVNode("", true)
                    ])
                  ]),
                  createVNode("div", { class: "mt-6 flex justify-end space-x-3" }, [
                    createVNode(_sfc_main$4, { onClick: close }, {
                      default: withCtx(() => [
                        createTextVNode(" Cancel ")
                      ]),
                      _: 1
                    }),
                    createVNode(_sfc_main$5, {
                      type: "submit",
                      class: { "opacity-25": unref(form).processing },
                      disabled: unref(form).processing || isDuplicate.value
                    }, {
                      default: withCtx(() => [
                        createTextVNode(" Create Unit ")
                      ]),
                      _: 1
                    }, 8, ["class", "disabled"])
                  ])
                ], 32)
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/UnitModal.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as _
};
