import { watch, withCtx, unref, createTextVNode, createVNode, toDisplayString, openBlock, createBlock, withModifiers, withDirectives, vModelText, Fragment, renderList, vModelSelect, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrRenderList, ssrIncludeBooleanAttr, ssrLooseContain, ssrLooseEqual } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { useForm, router } from "@inertiajs/vue3";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$3 } from "./PrimaryButton-b82fb16e.mjs";
import "./AlertMessage-0f422981.mjs";
import { a as displayWarning, d as displayResponse } from "./responseMessage-d505224b.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
import "toastr";
import "sweetalert2";
const _sfc_main = {
  __name: "Form",
  __ssrInlineRender: true,
  props: ["staffattendance", "id", "users"],
  setup(__props) {
    var _a, _b;
    const props = __props;
    const initialRecords = props.staffattendance ? [{
      staff_id: props.staffattendance.staff_id,
      name: props.staffattendance.name,
      attendance_status: props.staffattendance.attendance_status || "",
      in_time: props.staffattendance.in_time || "",
      out_time: props.staffattendance.out_time || "",
      note: props.staffattendance.note || ""
    }] : props.users.map((user) => ({
      staff_id: user.id,
      name: `${user.first_name} ${user.last_name}`,
      attendance_status: "",
      in_time: "",
      out_time: "",
      note: ""
    }));
    const form = useForm({
      attendance_date: ((_a = props.staffattendance) == null ? void 0 : _a.attendance_date) ?? "",
      records: initialRecords,
      _method: ((_b = props.staffattendance) == null ? void 0 : _b.id) ? "put" : "post"
    });
    watch(() => form.attendance_date, async (newDate) => {
      if (newDate) {
        await fetchAttendanceRecords(newDate);
      } else {
        form.records = initialRecords.map((user) => ({
          staff_id: user.staff_id,
          name: user.name,
          attendance_status: "",
          in_time: "",
          out_time: "",
          note: ""
        }));
      }
    });
    const fetchAttendanceRecords = async (date) => {
      try {
        const response = await fetch(route("backend.staffattendance.fetch", { date }), {
          method: "GET",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json"
          }
        });
        const data = await response.json();
        if (response.ok) {
          form.records = data.records.length ? data.records : initialRecords;
        } else {
          displayWarning(data.message || "Failed to fetch records.");
        }
      } catch (error) {
        displayWarning("An error occurred while fetching records.");
      }
    };
    const isTimeFieldsActive = (status) => {
      return status === "Present" || status === "Late";
    };
    const setCurrentTime = (record, field) => {
      if (!record[field] && isTimeFieldsActive(record.attendance_status)) {
        const now = /* @__PURE__ */ new Date();
        const hours = now.getHours().toString().padStart(2, "0");
        const minutes = now.getMinutes().toString().padStart(2, "0");
        record[field] = `${hours}:${minutes}`;
      }
    };
    const submit = () => {
      const routeName = props.id ? route("backend.staffattendance.update", props.id) : route("backend.staffattendance.store");
      form.post(routeName, {
        onSuccess: (response) => {
          if (!props.id)
            form.reset();
          displayResponse(response);
        },
        onError: (errorObject) => {
          displayWarning(errorObject);
        }
      });
    };
    const goToStaffAttendanceList = () => {
      router.visit(route("backend.staffattendance.index"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md"${_scopeId}><div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md"${_scopeId}><div${_scopeId}><h1 class="p-4 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1></div><div class="p-2 py-2 flex items-center space-x-2"${_scopeId}><div class="flex items-center space-x-3"${_scopeId}><button class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"${_scopeId}><svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"${_scopeId}></path></svg> Attendance List </button></div></div></div><form class="p-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "attendance_date",
              value: "Attendance Date"
            }, null, _parent2, _scopeId));
            _push2(`<input id="attendance_date" class="block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${ssrRenderAttr("value", unref(form).attendance_date)} type="date"${_scopeId}><table class="w-full grid-cols-1 gap-3 text-xs text-gray-700 border border-gray-300 rid sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4"${_scopeId}><thead class="bg-gray-200"${_scopeId}><tr${_scopeId}><th class="px-2 py-2 border border-gray-400"${_scopeId}>Sl/No</th><th class="px-2 py-2 border border-gray-400"${_scopeId}>Staff Id</th><th class="px-2 py-2 border border-gray-400"${_scopeId}>Name</th><th class="px-2 py-2 border border-gray-400"${_scopeId}>Attendance Status</th><th class="px-2 py-2 border border-gray-400"${_scopeId}>In Time</th><th class="px-2 py-2 border border-gray-400"${_scopeId}>Out Time</th><th class="px-2 py-2 border border-gray-400"${_scopeId}>Note</th></tr></thead><tbody${_scopeId}><!--[-->`);
            ssrRenderList(unref(form).records, (record, index) => {
              _push2(`<tr${_scopeId}><td class="px-2 py-1 text-center border border-gray-300"${_scopeId}>${ssrInterpolate(index + 1)}</td><td class="px-2 py-1 text-center border border-gray-300"${_scopeId}>${ssrInterpolate(record.staff_id)}</td><td class="px-2 py-1 text-center border border-gray-300"${_scopeId}>${ssrInterpolate(record.name)}</td><td class="px-2 py-1 border border-gray-300"${_scopeId}><select class="w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}><option value=""${ssrIncludeBooleanAttr(Array.isArray(record.attendance_status) ? ssrLooseContain(record.attendance_status, "") : ssrLooseEqual(record.attendance_status, "")) ? " selected" : ""}${_scopeId}>Select Attendance</option><option value="Present"${ssrIncludeBooleanAttr(Array.isArray(record.attendance_status) ? ssrLooseContain(record.attendance_status, "Present") : ssrLooseEqual(record.attendance_status, "Present")) ? " selected" : ""}${_scopeId}>Present</option><option value="Late"${ssrIncludeBooleanAttr(Array.isArray(record.attendance_status) ? ssrLooseContain(record.attendance_status, "Late") : ssrLooseEqual(record.attendance_status, "Late")) ? " selected" : ""}${_scopeId}>Late</option><option value="Absent"${ssrIncludeBooleanAttr(Array.isArray(record.attendance_status) ? ssrLooseContain(record.attendance_status, "Absent") : ssrLooseEqual(record.attendance_status, "Absent")) ? " selected" : ""}${_scopeId}>Absent</option><option value="Holiday"${ssrIncludeBooleanAttr(Array.isArray(record.attendance_status) ? ssrLooseContain(record.attendance_status, "Holiday") : ssrLooseEqual(record.attendance_status, "Holiday")) ? " selected" : ""}${_scopeId}>Holiday</option></select></td><td class="px-2 py-1 border border-gray-300"${_scopeId}><input${ssrRenderAttr("value", record.in_time)} type="time"${ssrIncludeBooleanAttr(!isTimeFieldsActive(record.attendance_status)) ? " disabled" : ""} class="w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}></td><td class="px-2 py-1 border border-gray-300"${_scopeId}><input${ssrRenderAttr("value", record.out_time)} type="time"${ssrIncludeBooleanAttr(!isTimeFieldsActive(record.attendance_status)) ? " disabled" : ""} class="w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}></td><td class="px-2 py-1 border border-gray-300"${_scopeId}><textarea placeholder="Note" class="w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"${_scopeId}>${ssrInterpolate(record.note)}</textarea></td></tr>`);
            });
            _push2(`<!--]--></tbody></table><div class="flex items-center justify-end mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, {
              type: "submit",
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Submit `);
                } else {
                  return [
                    createTextVNode(" Submit ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full transition duration-1000 ease-in-out transform bg-white rounded-md" }, [
                createVNode("div", { class: "flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md" }, [
                  createVNode("div", null, [
                    createVNode("h1", { class: "p-4 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1)
                  ]),
                  createVNode("div", { class: "p-2 py-2 flex items-center space-x-2" }, [
                    createVNode("div", { class: "flex items-center space-x-3" }, [
                      createVNode("button", {
                        onClick: goToStaffAttendanceList,
                        class: "inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2"
                      }, [
                        (openBlock(), createBlock("svg", {
                          class: "w-4 h-4 mr-2 -ml-1",
                          fill: "none",
                          stroke: "currentColor",
                          viewBox: "0 0 24 24",
                          "stroke-width": "2"
                        }, [
                          createVNode("path", {
                            "stroke-linecap": "round",
                            "stroke-linejoin": "round",
                            d: "M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
                          })
                        ])),
                        createTextVNode(" Attendance List ")
                      ])
                    ])
                  ])
                ]),
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"]),
                  class: "p-4"
                }, [
                  createVNode(_sfc_main$2, {
                    for: "attendance_date",
                    value: "Attendance Date"
                  }),
                  withDirectives(createVNode("input", {
                    id: "attendance_date",
                    class: "block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                    "onUpdate:modelValue": ($event) => unref(form).attendance_date = $event,
                    type: "date"
                  }, null, 8, ["onUpdate:modelValue"]), [
                    [vModelText, unref(form).attendance_date]
                  ]),
                  createVNode("table", { class: "w-full grid-cols-1 gap-3 text-xs text-gray-700 border border-gray-300 rid sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4" }, [
                    createVNode("thead", { class: "bg-gray-200" }, [
                      createVNode("tr", null, [
                        createVNode("th", { class: "px-2 py-2 border border-gray-400" }, "Sl/No"),
                        createVNode("th", { class: "px-2 py-2 border border-gray-400" }, "Staff Id"),
                        createVNode("th", { class: "px-2 py-2 border border-gray-400" }, "Name"),
                        createVNode("th", { class: "px-2 py-2 border border-gray-400" }, "Attendance Status"),
                        createVNode("th", { class: "px-2 py-2 border border-gray-400" }, "In Time"),
                        createVNode("th", { class: "px-2 py-2 border border-gray-400" }, "Out Time"),
                        createVNode("th", { class: "px-2 py-2 border border-gray-400" }, "Note")
                      ])
                    ]),
                    createVNode("tbody", null, [
                      (openBlock(true), createBlock(Fragment, null, renderList(unref(form).records, (record, index) => {
                        return openBlock(), createBlock("tr", { key: index }, [
                          createVNode("td", { class: "px-2 py-1 text-center border border-gray-300" }, toDisplayString(index + 1), 1),
                          createVNode("td", { class: "px-2 py-1 text-center border border-gray-300" }, toDisplayString(record.staff_id), 1),
                          createVNode("td", { class: "px-2 py-1 text-center border border-gray-300" }, toDisplayString(record.name), 1),
                          createVNode("td", { class: "px-2 py-1 border border-gray-300" }, [
                            withDirectives(createVNode("select", {
                              class: "w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600",
                              "onUpdate:modelValue": ($event) => record.attendance_status = $event
                            }, [
                              createVNode("option", { value: "" }, "Select Attendance"),
                              createVNode("option", { value: "Present" }, "Present"),
                              createVNode("option", { value: "Late" }, "Late"),
                              createVNode("option", { value: "Absent" }, "Absent"),
                              createVNode("option", { value: "Holiday" }, "Holiday")
                            ], 8, ["onUpdate:modelValue"]), [
                              [vModelSelect, record.attendance_status]
                            ])
                          ]),
                          createVNode("td", { class: "px-2 py-1 border border-gray-300" }, [
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => record.in_time = $event,
                              type: "time",
                              disabled: !isTimeFieldsActive(record.attendance_status),
                              onClick: ($event) => setCurrentTime(record, "in_time"),
                              class: "w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            }, null, 8, ["onUpdate:modelValue", "disabled", "onClick"]), [
                              [vModelText, record.in_time]
                            ])
                          ]),
                          createVNode("td", { class: "px-2 py-1 border border-gray-300" }, [
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => record.out_time = $event,
                              type: "time",
                              disabled: !isTimeFieldsActive(record.attendance_status),
                              onClick: ($event) => setCurrentTime(record, "out_time"),
                              class: "w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            }, null, 8, ["onUpdate:modelValue", "disabled", "onClick"]), [
                              [vModelText, record.out_time]
                            ])
                          ]),
                          createVNode("td", { class: "px-2 py-1 border border-gray-300" }, [
                            withDirectives(createVNode("textarea", {
                              "onUpdate:modelValue": ($event) => record.note = $event,
                              placeholder: "Note",
                              class: "w-full block p-2 mb-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            }, null, 8, ["onUpdate:modelValue"]), [
                              [vModelText, record.note]
                            ])
                          ])
                        ]);
                      }), 128))
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-4" }, [
                    createVNode(_sfc_main$3, {
                      type: "submit",
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(" Submit ")
                      ]),
                      _: 1
                    }, 8, ["disabled"])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/StaffAttendance/Form.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
