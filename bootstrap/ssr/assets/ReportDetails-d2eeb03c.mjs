import { ref, computed, watch, withCtx, createVNode, toDisplayString, withDirectives, vModelText, openBlock, createBlock, Fragment, renderList, createTextVNode, createCommentVNode, useSSRContext } from "vue";
import { ssrRenderComponent, ssrInterpolate, ssrRenderAttr, ssrRenderList } from "vue/server-renderer";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import "@inertiajs/vue3";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
const _sfc_main = {
  __name: "ReportDetails",
  __ssrInlineRender: true,
  props: {
    dateDatas: { type: Object, default: () => ({ attendanceData: [] }) },
    filters: { type: Object, default: () => ({}) },
    staffName: { type: String, default: "" },
    staffId: { type: String, default: "" },
    datas: { type: Array, default: () => [] },
    leaves: { type: Object, default: () => ({ leaves: [] }) }
  },
  setup(__props) {
    const props = __props;
    const filters = ref({
      staff_id: props.filters.staff_id || "",
      name: props.filters.name || "",
      numOfData: props.filters.numOfData || 10,
      month: (/* @__PURE__ */ new Date()).toISOString().split("T")[0].slice(0, 7)
    });
    const selectedYear = computed(() => filters.value.month.split("-")[0]);
    const currentMonth = computed(() => parseInt(filters.value.month.split("-")[1]));
    const daysInMonth = (month, year) => new Date(year, month, 0).getDate();
    const getFirstDayOfMonth = (month, year) => new Date(year, month - 1, 1).getDay();
    const generateMonthGrid = (year, month) => {
      const totalDays = daysInMonth(month, year);
      const firstDay = getFirstDayOfMonth(month, year);
      const weeks = [];
      let currentWeek = Array(7).fill(null);
      for (let day = 1; day <= totalDays; day++) {
        const dayOfWeek = (firstDay + day - 1) % 7;
        currentWeek[dayOfWeek] = day;
        if (dayOfWeek === 6 || day === totalDays) {
          weeks.push(currentWeek);
          currentWeek = Array(7).fill(null);
        }
      }
      return weeks;
    };
    const monthGrid = computed(
      () => generateMonthGrid(selectedYear.value, currentMonth.value)
    );
    const attendanceData = computed(() => props.dateDatas.attendanceData || []);
    const getAttendanceStatus = (day) => {
      var _a, _b, _c, _d;
      const date = `${filters.value.month}-${day.toString().padStart(2, "0")}`;
      const record = attendanceData.value.find((record2) => record2.attendance_date === date);
      console.log(props.leaves);
      const leaveRecord = (_b = (_a = props.leaves) == null ? void 0 : _a.leaves) == null ? void 0 : _b.find((leave) => {
        const leaveStart = new Date(leave.from);
        const leaveEnd = new Date(leave.to);
        const currentDate = new Date(date);
        return currentDate >= leaveStart && currentDate <= leaveEnd;
      });
      if (record) {
        if (record.attendance_status === "Absent" && leaveRecord) {
          return `Absent (${(_c = leaveRecord == null ? void 0 : leaveRecord.leave_type) == null ? void 0 : _c.type_name})`;
        }
        return record.attendance_status;
      } else if (leaveRecord) {
        return `Leave (${(_d = leaveRecord == null ? void 0 : leaveRecord.leave_type) == null ? void 0 : _d.type_name})`;
      }
      return "--";
    };
    const formattedMonthYear = computed(() => {
      const [year, month] = filters.value.month.split("-");
      const normalizedMonth = month.replace(/^0+/, "");
      const date = new Date(year, normalizedMonth - 1);
      return date.toLocaleString("default", { month: "long", year: "numeric" });
    });
    const totalAttendance = computed(() => {
      var _a, _b;
      let total = {
        present: 0,
        absent: 0,
        late: 0,
        holiday: 0,
        paidDays: 0,
        unpaidDays: 0,
        approvedLeaves: 0,
        rejectedLeaves: 0
      };
      const daysInCurrentMonth = daysInMonth(currentMonth.value, selectedYear.value);
      for (let day = 1; day <= daysInCurrentMonth; day++) {
        const date = `${selectedYear.value}-${currentMonth.value.toString().padStart(2, "0")}-${day.toString().padStart(2, "0")}`;
        const record = attendanceData.value.find((r) => r.attendance_date === date);
        const leaveRecord = (_b = (_a = props.leaves) == null ? void 0 : _a.leaves) == null ? void 0 : _b.find((leave) => {
          const leaveStart = new Date(leave.from);
          const leaveEnd = new Date(leave.to);
          const currentDate = new Date(date);
          return currentDate >= leaveStart && currentDate <= leaveEnd;
        });
        if (record) {
          if (record.attendance_status === "Present") {
            total.present++;
            total.paidDays++;
          } else if (record.attendance_status === "Late") {
            total.late++;
            total.paidDays++;
          } else if (record.attendance_status === "Holiday") {
            total.holiday++;
            total.paidDays++;
          } else if (record.attendance_status === "Absent") {
            if (leaveRecord) {
              if (leaveRecord.status === "Approved") {
                total.approvedLeaves++;
                total.paidDays++;
              } else {
                total.rejectedLeaves++;
                total.unpaidDays++;
                total.absent++;
              }
            } else {
              total.absent++;
              total.unpaidDays++;
            }
          }
        } else if (leaveRecord) {
          if (leaveRecord.status === "Approved") {
            total.approvedLeaves++;
            total.paidDays++;
          } else {
            total.rejectedLeaves++;
            total.unpaidDays++;
            total.absent++;
          }
        } else {
          total.absent++;
          total.unpaidDays++;
        }
      }
      return total;
    });
    const attendancePercentage = computed(() => {
      const totalDays = daysInMonth(currentMonth.value, selectedYear.value);
      return {
        present: (totalAttendance.value.present / totalDays * 100).toFixed(2),
        absent: (totalAttendance.value.absent / totalDays * 100).toFixed(2),
        late: (totalAttendance.value.late / totalDays * 100).toFixed(2),
        holiday: (totalAttendance.value.holiday / totalDays * 100).toFixed(2),
        paidDays: (totalAttendance.value.paidDays / totalDays * 100).toFixed(2),
        unpaidDays: (totalAttendance.value.unpaidDays / totalDays * 100).toFixed(2)
      };
    });
    const applyFilter = () => {
      const month = filters.value.month.split("-")[1];
      const year = selectedYear.value;
      `/backend/staffattendance/report/${props.staffId}?numOfData=${filters.value.numOfData}&month=${month}&year=${year}`;
    };
    watch(filters, applyFilter, { deep: true });
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full p-4 mt-3 bg-white rounded-md shadow-md dark:bg-slate-900"${_scopeId}><div class="flex items-center justify-between"${_scopeId}><h1 class="py-2 text-xl font-bold dark:text-white"${_scopeId}>${ssrInterpolate(_ctx.$page.props.pageTitle)}</h1><a${ssrRenderAttr("href", `/backend/staff/payslip/${__props.staffId}?month=${filters.value.month}&unpaidDays=${totalAttendance.value.unpaidDays}`)} class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"${_scopeId}> Payslip </a></div><div class="grid w-full grid-cols-1 gap-2 md:grid-cols-5 my-4"${_scopeId}><input id="month"${ssrRenderAttr("value", filters.value.month)} class="block w-full p-2 text-sm rounded-md" type="month"${_scopeId}></div><div class="w-full mt-4"${_scopeId}><table class="w-full text-xs text-gray-700 dark:text-gray-200"${_scopeId}><caption class="font-medium font-bold text-left text-lg"${_scopeId}> Attendance Details of ${ssrInterpolate(formattedMonthYear.value)}</caption><thead class="bg-gray-200 dark:bg-gray-700"${_scopeId}><tr${_scopeId}><!--[-->`);
            ssrRenderList([
              "Sunday",
              "Monday",
              "Tuesday",
              "Wednesday",
              "Thursday",
              "Friday",
              "Saturday"
            ], (day) => {
              _push2(`<th class="text-sm text-center border border-slate-700"${_scopeId}>${ssrInterpolate(day)}</th>`);
            });
            _push2(`<!--]--></tr></thead><tbody${_scopeId}><!--[-->`);
            ssrRenderList(monthGrid.value, (week, index) => {
              _push2(`<tr${_scopeId}><!--[-->`);
              ssrRenderList(week, (day, dayIndex) => {
                _push2(`<td class="text-center border border-slate-700"${_scopeId}>`);
                if (day) {
                  _push2(`<div${_scopeId}>${ssrInterpolate(day)} <div class="text-xs"${_scopeId}>${ssrInterpolate(getAttendanceStatus(day))}</div></div>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</td>`);
              });
              _push2(`<!--]--></tr>`);
            });
            _push2(`<!--]--></tbody></table></div><div class="w-full mt-6"${_scopeId}><table class="w-full text-xs text-gray-700 dark:text-gray-200"${_scopeId}><caption class="font-medium font-bold text-left text-lg"${_scopeId}> Salary Calculation for ${ssrInterpolate(formattedMonthYear.value)}</caption><thead class="bg-gray-200 dark:bg-gray-700"${_scopeId}><tr${_scopeId}><th class="text-sm text-center border border-slate-700"${_scopeId}>Staff Id</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Staff Name</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Total Days</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Present</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Absent</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Lates</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Holidays</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Paid Days</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Unpaid Days</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Approved Leaves</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Rejected Leaves</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Paid %</th><th class="text-sm text-center border border-slate-700"${_scopeId}>Unpaid %</th></tr></thead><tbody${_scopeId}><tr${_scopeId}><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(__props.staffId)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(__props.staffName)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(daysInMonth(currentMonth.value, selectedYear.value))}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(totalAttendance.value.present)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(totalAttendance.value.absent)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(totalAttendance.value.late)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(totalAttendance.value.holiday)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(totalAttendance.value.paidDays)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(totalAttendance.value.unpaidDays)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(totalAttendance.value.approvedLeaves)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(totalAttendance.value.rejectedLeaves)}</td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(attendancePercentage.value.paidDays)}% </td><td class="text-center border border-slate-700"${_scopeId}>${ssrInterpolate(attendancePercentage.value.unpaidDays)}% </td></tr></tbody></table></div></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full p-4 mt-3 bg-white rounded-md shadow-md dark:bg-slate-900" }, [
                createVNode("div", { class: "flex items-center justify-between" }, [
                  createVNode("h1", { class: "py-2 text-xl font-bold dark:text-white" }, toDisplayString(_ctx.$page.props.pageTitle), 1),
                  createVNode("a", {
                    href: `/backend/staff/payslip/${__props.staffId}?month=${filters.value.month}&unpaidDays=${totalAttendance.value.unpaidDays}`,
                    class: "px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                  }, " Payslip ", 8, ["href"])
                ]),
                createVNode("div", { class: "grid w-full grid-cols-1 gap-2 md:grid-cols-5 my-4" }, [
                  withDirectives(createVNode("input", {
                    id: "month",
                    "onUpdate:modelValue": ($event) => filters.value.month = $event,
                    class: "block w-full p-2 text-sm rounded-md",
                    type: "month",
                    onChange: applyFilter
                  }, null, 40, ["onUpdate:modelValue"]), [
                    [vModelText, filters.value.month]
                  ])
                ]),
                createVNode("div", { class: "w-full mt-4" }, [
                  createVNode("table", { class: "w-full text-xs text-gray-700 dark:text-gray-200" }, [
                    createVNode("caption", { class: "font-medium font-bold text-left text-lg" }, " Attendance Details of " + toDisplayString(formattedMonthYear.value), 1),
                    createVNode("thead", { class: "bg-gray-200 dark:bg-gray-700" }, [
                      createVNode("tr", null, [
                        (openBlock(), createBlock(Fragment, null, renderList([
                          "Sunday",
                          "Monday",
                          "Tuesday",
                          "Wednesday",
                          "Thursday",
                          "Friday",
                          "Saturday"
                        ], (day) => {
                          return createVNode("th", {
                            key: day,
                            class: "text-sm text-center border border-slate-700"
                          }, toDisplayString(day), 1);
                        }), 64))
                      ])
                    ]),
                    createVNode("tbody", null, [
                      (openBlock(true), createBlock(Fragment, null, renderList(monthGrid.value, (week, index) => {
                        return openBlock(), createBlock("tr", { key: index }, [
                          (openBlock(true), createBlock(Fragment, null, renderList(week, (day, dayIndex) => {
                            return openBlock(), createBlock("td", {
                              key: dayIndex,
                              class: "text-center border border-slate-700"
                            }, [
                              day ? (openBlock(), createBlock("div", { key: 0 }, [
                                createTextVNode(toDisplayString(day) + " ", 1),
                                createVNode("div", { class: "text-xs" }, toDisplayString(getAttendanceStatus(day)), 1)
                              ])) : createCommentVNode("", true)
                            ]);
                          }), 128))
                        ]);
                      }), 128))
                    ])
                  ])
                ]),
                createVNode("div", { class: "w-full mt-6" }, [
                  createVNode("table", { class: "w-full text-xs text-gray-700 dark:text-gray-200" }, [
                    createVNode("caption", { class: "font-medium font-bold text-left text-lg" }, " Salary Calculation for " + toDisplayString(formattedMonthYear.value), 1),
                    createVNode("thead", { class: "bg-gray-200 dark:bg-gray-700" }, [
                      createVNode("tr", null, [
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Staff Id"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Staff Name"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Total Days"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Present"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Absent"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Lates"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Holidays"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Paid Days"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Unpaid Days"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Approved Leaves"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Rejected Leaves"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Paid %"),
                        createVNode("th", { class: "text-sm text-center border border-slate-700" }, "Unpaid %")
                      ])
                    ]),
                    createVNode("tbody", null, [
                      createVNode("tr", null, [
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(__props.staffId), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(__props.staffName), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(daysInMonth(currentMonth.value, selectedYear.value)), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(totalAttendance.value.present), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(totalAttendance.value.absent), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(totalAttendance.value.late), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(totalAttendance.value.holiday), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(totalAttendance.value.paidDays), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(totalAttendance.value.unpaidDays), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(totalAttendance.value.approvedLeaves), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(totalAttendance.value.rejectedLeaves), 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(attendancePercentage.value.paidDays) + "% ", 1),
                        createVNode("td", { class: "text-center border border-slate-700" }, toDisplayString(attendancePercentage.value.unpaidDays) + "% ", 1)
                      ])
                    ])
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/StaffAttendance/ReportDetails.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
