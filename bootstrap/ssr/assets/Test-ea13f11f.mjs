import { mergeProps, withCtx, unref, createTextVNode, createVNode, openBlock, createBlock, withModifiers, useSSRContext } from "vue";
import { ssrRenderComponent, ssrRenderStyle } from "vue/server-renderer";
import { useForm } from "@inertiajs/vue3";
import { _ as _sfc_main$1 } from "./BackendLayout-d755bdca.mjs";
import { _ as _sfc_main$4 } from "./InputError-6aeb8d97.mjs";
import { _ as _sfc_main$2 } from "./InputLabel-70ca52d1.mjs";
import { _ as _sfc_main$5 } from "./PrimaryButton-b82fb16e.mjs";
import { _ as _sfc_main$3 } from "./TextInput-6c08ecf5.mjs";
import "mitt";
import "./_plugin-vue_export-helper-cc2b3d55.mjs";
import "./DropdownLink-64712462.mjs";
import "@vueuse/core";
const _sfc_main = {
  __name: "Test",
  __ssrInlineRender: true,
  setup(__props) {
    const form = useForm({
      name: "",
      email: "",
      password: "",
      password_confirmation: "",
      terms: false
    });
    const submit = () => {
      form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation")
      });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$1, mergeProps({ title: "Dashboard d" }, _attrs), {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out"${_scopeId}><div class="grid xs:grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"${_scopeId}><div class="pl-1 w-full h-20 bg-green-400 rounded-lg shadow-md"${_scopeId}><div class="flex w-full h-full py-2 px-4 bg-gray-50 rounded-lg justify-between"${_scopeId}><div class="my-auto"${_scopeId}><p class="font-bold text-sm sm:text-md"${_scopeId}>EARNINGS (MONTHLY)</p><p class="text-lg"${_scopeId}>$40,000</p></div><div class="my-auto"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"${_scopeId}></path></svg></div></div></div><div class="pl-1 w-full h-20 bg-blue-500 rounded-lg shadow-md"${_scopeId}><div class="flex w-full h-full py-2 px-4 bg-gray-50 rounded-lg justify-between"${_scopeId}><div class="my-auto"${_scopeId}><p class="font-bold text-sm sm:text-md"${_scopeId}>EARNINGS (ANNUAL)</p><p class="text-lg"${_scopeId}>$215,000</p></div><div class="my-auto"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"${_scopeId}></path></svg></div></div></div><div class="pl-1 w-full h-20 bg-purple-500 rounded-lg shadow-md"${_scopeId}><div class="flex w-full h-full py-2 px-4 bg-gray-100 rounded-lg justify-between"${_scopeId}><div class="my-auto"${_scopeId}><p class="font-bold text-sm sm:text-md"${_scopeId}>TASKS</p><p class="text-lg"${_scopeId}>50%</p></div><div class="my-auto"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"${_scopeId}></path></svg></div></div></div><div class="pl-1 w-full h-20 bg-yellow-400 rounded-lg shadow-md"${_scopeId}><div class="flex w-full h-full py-2 px-4 bg-gray-100 rounded-lg justify-between"${_scopeId}><div class="my-auto"${_scopeId}><p class="font-bold text-sm sm:text-md"${_scopeId}>PENDING REQUESTS</p><p class="text-lg"${_scopeId}>18</p></div><div class="my-auto"${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"${_scopeId}><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"${_scopeId}></path></svg></div></div></div></div></div><div class="w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out"${_scopeId}><div class="grid grid-cols-3 gap-4"${_scopeId}><div class="w-full"${_scopeId}><div class="rounded-lg shadow-sm mb-4"${_scopeId}><div class="rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden"${_scopeId}><div class="px-3 pt-8 pb-10 text-center relative z-10"${_scopeId}><h4 class="text-sm uppercase text-gray-500 leading-tight"${_scopeId}>Users</h4><h3 class="text-3xl text-gray-700 font-semibold leading-tight my-3"${_scopeId}>3,682</h3><p class="text-xs text-green-500 leading-tight"${_scopeId}>▲ 57.1%</p></div><div class="absolute bottom-0 inset-x-0"${_scopeId}><canvas id="chart1" height="70"${_scopeId}></canvas></div></div></div></div><div class="w-full"${_scopeId}><div class="rounded-lg shadow-sm mb-4"${_scopeId}><div class="rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden"${_scopeId}><div class="px-3 pt-8 pb-10 text-center relative z-10"${_scopeId}><h4 class="text-sm uppercase text-gray-500 leading-tight"${_scopeId}>Subscribers</h4><h3 class="text-3xl text-gray-700 font-semibold leading-tight my-3"${_scopeId}>11,427</h3><p class="text-xs text-red-500 leading-tight"${_scopeId}>▼ 42.8%</p></div><div class="absolute bottom-0 inset-x-0"${_scopeId}><canvas id="chart2" height="70"${_scopeId}></canvas></div></div></div></div><div class="w-full"${_scopeId}><div class="rounded-lg shadow-sm mb-4"${_scopeId}><div class="rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden"${_scopeId}><div class="px-3 pt-8 pb-10 text-center relative z-10"${_scopeId}><h4 class="text-sm uppercase text-gray-500 leading-tight"${_scopeId}>Comments</h4><h3 class="text-3xl text-gray-700 font-semibold leading-tight my-3"${_scopeId}>8,028</h3><p class="text-xs text-green-500 leading-tight"${_scopeId}>▲ 8.2%</p></div><div class="absolute bottom-0 inset-x-0"${_scopeId}><canvas id="chart3" height="70"${_scopeId}></canvas></div></div></div></div></div></div><div class="w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out"${_scopeId}><form${_scopeId}><div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3"${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              placeholder: "Enter Your Name",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "last_name",
              value: "Last Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "last_name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "last_name",
              value: "Last Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "last_name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              placeholder: "Enter Your Name",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "last_name",
              value: "Last Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "last_name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "last_name",
              value: "Last Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "last_name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "name",
              value: "Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              placeholder: "Enter Your Name",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "last_name",
              value: "Last Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "last_name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              for: "last_name",
              value: "Last Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$3, {
              id: "last_name",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              type: "text",
              class: "block w-full",
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$4, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div></div><div class="flex items-center justify-end mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, {
              class: ["ms-4", { "opacity-25": unref(form).processing }],
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
            _push2(`</div></form></div><div class="w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out"${_scopeId}><div class="container mx-auto px-4 sm:px-8"${_scopeId}><div class="py-8"${_scopeId}><div${_scopeId}><h2 class="text-2xl font-semibold leading-tight"${_scopeId}>Invoices</h2></div><div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto"${_scopeId}><div class="inline-block min-w-full shadow-md rounded-lg overflow-hidden"${_scopeId}><table class="min-w-full leading-normal"${_scopeId}><thead${_scopeId}><tr${_scopeId}><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"${_scopeId}> Client / Invoice </th><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"${_scopeId}> Amount </th><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"${_scopeId}> Issued / Due </th><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"${_scopeId}> Status </th><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100"${_scopeId}></th></tr></thead><tbody${_scopeId}><tr${_scopeId}><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><div class="flex"${_scopeId}><div class="flex-shrink-0 w-10 h-10"${_scopeId}><img class="w-full h-full rounded-full" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=facearea&amp;facepad=2.2&amp;w=160&amp;h=160&amp;q=80" alt=""${_scopeId}></div><div class="ml-3"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Molly Sanders </p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>000004</p></div></div></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>$20,000</p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>USD</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Sept 28, 2019</p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>Due in 3 days</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><span class="relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight"${_scopeId}><span aria-hidden class="absolute inset-0 bg-green-200 opacity-50 rounded-full"${_scopeId}></span><span class="relative"${_scopeId}>Paid</span></span></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right"${_scopeId}><button type="button" class="inline-block text-gray-500 hover:text-gray-700"${_scopeId}><svg class="inline-block h-6 w-6 fill-current" viewBox="0 0 24 24"${_scopeId}><path d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm-2 6a2 2 0 104 0 2 2 0 00-4 0z"${_scopeId}></path></svg></button></td></tr><tr${_scopeId}><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><div class="flex"${_scopeId}><div class="flex-shrink-0 w-10 h-10"${_scopeId}><img class="w-full h-full rounded-full" src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=facearea&amp;facepad=2.2&amp;w=160&amp;h=160&amp;q=80" alt=""${_scopeId}></div><div class="ml-3"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Michael Roberts </p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>000003</p></div></div></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>$214,000</p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>USD</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Sept 25, 2019</p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>Due in 6 days</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><span class="relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight"${_scopeId}><span aria-hidden class="absolute inset-0 bg-green-200 opacity-50 rounded-full"${_scopeId}></span><span class="relative"${_scopeId}>Paid</span></span></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right"${_scopeId}><button type="button" class="inline-block text-gray-500 hover:text-gray-700"${_scopeId}><svg class="inline-block h-6 w-6 fill-current" viewBox="0 0 24 24"${_scopeId}><path d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm-2 6a2 2 0 104 0 2 2 0 00-4 0z"${_scopeId}></path></svg></button></td></tr><tr${_scopeId}><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><div class="flex"${_scopeId}><div class="flex-shrink-0 w-10 h-10"${_scopeId}><img class="w-full h-full rounded-full" src="https://images.unsplash.com/photo-1540845511934-7721dd7adec3?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=facearea&amp;facepad=2.2&amp;w=160&amp;h=160&amp;q=80" alt=""${_scopeId}></div><div class="ml-3"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Devin Childs </p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>000002</p></div></div></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>$20,000</p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>USD</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Sept 14, 2019</p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>Due in 2 weeks</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><span class="relative inline-block px-3 py-1 font-semibold text-orange-900 leading-tight"${_scopeId}><span aria-hidden class="absolute inset-0 bg-orange-200 opacity-50 rounded-full"${_scopeId}></span><span class="relative"${_scopeId}>Pending</span></span></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right"${_scopeId}><button type="button" class="inline-block text-gray-500 hover:text-gray-700"${_scopeId}><svg class="inline-block h-6 w-6 fill-current" viewBox="0 0 24 24"${_scopeId}><path d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm-2 6a2 2 0 104 0 2 2 0 00-4 0z"${_scopeId}></path></svg></button></td></tr><tr${_scopeId}><td class="px-5 py-5 bg-white text-sm"${_scopeId}><div class="flex"${_scopeId}><div class="flex-shrink-0 w-10 h-10"${_scopeId}><img class="w-full h-full rounded-full" src="https://images.unsplash.com/photo-1522609925277-66fea332c575?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=facearea&amp;facepad=2.2&amp;h=160&amp;w=160&amp;q=80" alt=""${_scopeId}></div><div class="ml-3"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Frederick Nicholas </p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>000001</p></div></div></td><td class="px-5 py-5 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>$12,000</p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}>USD</p></td><td class="px-5 py-5 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Sept 6, 2019</p><p class="text-gray-600 whitespace-no-wrap"${_scopeId}> Due 3 weeks ago </p></td><td class="px-5 py-5 bg-white text-sm"${_scopeId}><span class="relative inline-block px-3 py-1 font-semibold text-red-900 leading-tight"${_scopeId}><span aria-hidden class="absolute inset-0 bg-red-200 opacity-50 rounded-full"${_scopeId}></span><span class="relative"${_scopeId}>Overdue</span></span></td><td class="px-5 py-5 bg-white text-sm text-right"${_scopeId}><button type="button" class="inline-block text-gray-500 hover:text-gray-700"${_scopeId}><svg class="inline-block h-6 w-6 fill-current" viewBox="0 0 24 24"${_scopeId}><path d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm-2 6a2 2 0 104 0 2 2 0 00-4 0z"${_scopeId}></path></svg></button></td></tr></tbody></table></div></div></div></div></div><div class="w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out"${_scopeId}><h1 class="font-bold text-xl"${_scopeId}> Kullanıcılar ve iş sayıları</h1><div class="relative overflow-x-auto"${_scopeId}><table class="w-full text-sm text-left text-gray-500 dark:text-gray-400"${_scopeId}><thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400"${_scopeId}><tr${_scopeId}><th scope="col" class="px-6 py-3"${_scopeId}>#</th><th scope="col" class="px-6 py-3"${_scopeId}>Name</th><th scope="col" class="px-6 py-3"${_scopeId}>Surname</th><th scope="col" class="px-6 py-3"${_scopeId}>Work Count</th></tr></thead><tbody${_scopeId}><tr class="hover:bg-gray-100 bg-white border-b dark:bg-gray-800 dark:border-gray-700"${_scopeId}><th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"${_scopeId}> 1 </th><td class="px-6 py-4"${_scopeId}>Hakan</td><td class="px-6 py-4"${_scopeId}>Akgul</td><td class="px-6 py-4"${_scopeId}>22</td></tr><tr class="hover:bg-gray-100 bg-white border-b dark:bg-gray-800 dark:border-gray-700"${_scopeId}><th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"${_scopeId}> 1 </th><td class="px-6 py-4"${_scopeId}>John</td><td class="px-6 py-4"${_scopeId}>Smith</td><td class="px-6 py-4"${_scopeId}>4</td></tr><tr class="hover:bg-gray-100 bg-white border-b dark:bg-gray-800 dark:border-gray-700"${_scopeId}><th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"${_scopeId}> 1 </th><td class="px-6 py-4"${_scopeId}>Hakan</td><td class="px-6 py-4"${_scopeId}>Akgül</td><td class="px-6 py-4"${_scopeId}>22</td></tr></tbody></table></div></div><div class="w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out"${_scopeId}><div class="container mx-auto px-4 sm:px-8"${_scopeId}><div class="py-8"${_scopeId}><div${_scopeId}><h2 class="text-2xl font-semibold leading-tight"${_scopeId}>Users</h2></div><div class="my-2 flex sm:flex-row flex-col"${_scopeId}><div class="flex flex-row mb-1 sm:mb-0"${_scopeId}><div class="relative"${_scopeId}><select class="appearance-none h-full rounded-l border block appearance-none w-full bg-white border-gray-400 text-gray-700 py-2 px-4 pr-8 leading-tight focus:outline-none focus:bg-white focus:border-gray-500"${_scopeId}><option${_scopeId}>5</option><option${_scopeId}>10</option><option${_scopeId}>20</option></select><div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700"${_scopeId}><svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"${_scopeId}><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"${_scopeId}></path></svg></div></div><div class="relative"${_scopeId}><select class="appearance-none h-full rounded-r border-t sm:rounded-r-none sm:border-r-0 border-r border-b block appearance-none w-full bg-white border-gray-400 text-gray-700 py-2 px-4 pr-8 leading-tight focus:outline-none focus:border-l focus:border-r focus:bg-white focus:border-gray-500"${_scopeId}><option${_scopeId}>All</option><option${_scopeId}>Active</option><option${_scopeId}>Inactive</option></select><div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700"${_scopeId}><svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"${_scopeId}><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"${_scopeId}></path></svg></div></div></div><div class="block relative"${_scopeId}><span class="h-full absolute inset-y-0 left-0 flex items-center pl-2"${_scopeId}><svg viewBox="0 0 24 24" class="h-4 w-4 fill-current text-gray-500"${_scopeId}><path d="M10 4a6 6 0 100 12 6 6 0 000-12zm-8 6a8 8 0 1114.32 4.906l5.387 5.387a1 1 0 01-1.414 1.414l-5.387-5.387A8 8 0 012 10z"${_scopeId}></path></svg></span><input placeholder="Search" class="appearance-none rounded-r rounded-l sm:rounded-l-none border border-gray-400 border-b block pl-8 pr-6 py-2 w-full bg-white text-sm placeholder-gray-400 text-gray-700 focus:bg-white focus:placeholder-gray-600 focus:text-gray-700 focus:outline-none"${_scopeId}></div></div><div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto"${_scopeId}><div class="inline-block min-w-full shadow rounded-lg overflow-hidden"${_scopeId}><table class="min-w-full leading-normal"${_scopeId}><thead${_scopeId}><tr${_scopeId}><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"${_scopeId}> User </th><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"${_scopeId}> Rol </th><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"${_scopeId}> Created at </th><th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"${_scopeId}> Status </th></tr></thead><tbody${_scopeId}><tr${_scopeId}><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0 w-10 h-10"${_scopeId}><img class="w-full h-full rounded-full" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=facearea&amp;facepad=2.2&amp;w=160&amp;h=160&amp;q=80" alt=""${_scopeId}></div><div class="ml-3"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Vera Carpenter </p></div></div></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Admin</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Jan 21, 2020 </p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><span class="relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight"${_scopeId}><span aria-hidden class="absolute inset-0 bg-green-200 opacity-50 rounded-full"${_scopeId}></span><span class="relative"${_scopeId}>Activo</span></span></td></tr><tr${_scopeId}><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0 w-10 h-10"${_scopeId}><img class="w-full h-full rounded-full" src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=facearea&amp;facepad=2.2&amp;w=160&amp;h=160&amp;q=80" alt=""${_scopeId}></div><div class="ml-3"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Blake Bowman </p></div></div></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Editor</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Jan 01, 2020 </p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><span class="relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight"${_scopeId}><span aria-hidden class="absolute inset-0 bg-green-200 opacity-50 rounded-full"${_scopeId}></span><span class="relative"${_scopeId}>Activo</span></span></td></tr><tr${_scopeId}><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0 w-10 h-10"${_scopeId}><img class="w-full h-full rounded-full" src="https://images.unsplash.com/photo-1540845511934-7721dd7adec3?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=facearea&amp;facepad=2.2&amp;w=160&amp;h=160&amp;q=80" alt=""${_scopeId}></div><div class="ml-3"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Dana Moore </p></div></div></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Editor</p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Jan 10, 2020 </p></td><td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"${_scopeId}><span class="relative inline-block px-3 py-1 font-semibold text-orange-900 leading-tight"${_scopeId}><span aria-hidden class="absolute inset-0 bg-orange-200 opacity-50 rounded-full"${_scopeId}></span><span class="relative"${_scopeId}>Suspended</span></span></td></tr><tr${_scopeId}><td class="px-5 py-5 bg-white text-sm"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0 w-10 h-10"${_scopeId}><img class="w-full h-full rounded-full" src="https://images.unsplash.com/photo-1522609925277-66fea332c575?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=facearea&amp;facepad=2.2&amp;h=160&amp;w=160&amp;q=80" alt=""${_scopeId}></div><div class="ml-3"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}> Alonzo Cox </p></div></div></td><td class="px-5 py-5 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Admin</p></td><td class="px-5 py-5 bg-white text-sm"${_scopeId}><p class="text-gray-900 whitespace-no-wrap"${_scopeId}>Jan 18, 2020</p></td><td class="px-5 py-5 bg-white text-sm"${_scopeId}><span class="relative inline-block px-3 py-1 font-semibold text-red-900 leading-tight"${_scopeId}><span aria-hidden class="absolute inset-0 bg-red-200 opacity-50 rounded-full"${_scopeId}></span><span class="relative"${_scopeId}>Inactive</span></span></td></tr></tbody></table><div class="px-5 py-5 bg-white border-t flex flex-col xs:flex-row items-center xs:justify-between"${_scopeId}><span class="text-xs xs:text-sm text-gray-900"${_scopeId}> Showing 1 to 4 of 50 Entries </span><div class="inline-flex mt-2 xs:mt-0"${_scopeId}><button class="text-sm bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-l"${_scopeId}> Prev </button><button class="text-sm bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-r"${_scopeId}> Next </button></div></div></div></div></div></div></div><div class="w-full bg-slate-400 p-8 mt-3"${_scopeId}><div class="flex flex-col"${_scopeId}><div class="my-2 overflow-x-auto sm:-max-6 lg:-mx-8"${_scopeId}><div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8"${_scopeId}><div class="shadow overflow-hidden border-b border-gray-200 m-4 sm:rounded-lg"${_scopeId}><table class="min-w-full divide-y divide-gray-200"${_scopeId}><thead class="bg-gray-50"${_scopeId}><tr${_scopeId}><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Name </th><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Title </th><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Status </th><th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"${_scopeId}> Role </th><th scope="col" class="relative px-6 py-3"${_scopeId}><span class="sr-only"${_scopeId}>Edit</span></th></tr></thead><tbody class="bg-white divide-y divide-gray-200"${_scopeId}><tr${_scopeId}><td class="px-6 py-4 whitespace-nowrap"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0 h-10 w-10"${_scopeId}><img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=Sebas+Luna&amp;background=random" alt="Some"${_scopeId}></div><div class="ml-4"${_scopeId}><div class="text-sm font-medium text-gray-900"${_scopeId}> Sebastian Luna </div><div class="text-sm text-gray-500"${_scopeId}> email@email.com </div></div></div></td><td class="px-6 py-4 whitespace-nowrap"${_scopeId}><div class="text-sm text-gray-900"${_scopeId}>Co-founder</div><div class="text-sm text-gray-500"${_scopeId}>Developer</div></td><td class="px-6 py-4 whitespace-nowrap"${_scopeId}><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"${_scopeId}>Active</span></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"${_scopeId}> Admin </td><td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"${_scopeId}><a href="#" class="text-indigo-600 hover:text-indigo-900"${_scopeId}>Edit</a></td></tr><tr${_scopeId}><td class="px-6 py-4 whitespace-nowrap"${_scopeId}><div class="flex items-center"${_scopeId}><div class="flex-shrink-0 h-10 w-10"${_scopeId}><img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=Vilfer+Alvarez&amp;background=random" alt="Some"${_scopeId}></div><div class="ml-4"${_scopeId}><div class="text-sm font-medium text-gray-900"${_scopeId}> Vilfer Alvarez </div><div class="text-sm text-gray-500"${_scopeId}> email@email.com </div></div></div></td><td class="px-6 py-4 whitespace-nowrap"${_scopeId}><div class="text-sm text-gray-900"${_scopeId}>Co-founder</div><div class="text-sm text-gray-500"${_scopeId}>Developer</div></td><td class="px-6 py-4 whitespace-nowrap"${_scopeId}><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"${_scopeId}>Active</span></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"${_scopeId}> Admin </td><td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"${_scopeId}><a href="#" class="text-indigo-600 hover:text-indigo-900"${_scopeId}>Edit</a></td></tr></tbody></table></div></div></div></div></div><div class="w-full bg-slate-400 p-8 mt-3"${_scopeId}><table class="border-collapse border border-slate-500 w-full"${_scopeId}><thead${_scopeId}><tr class="bg-gray-200"${_scopeId}><th class="border border-slate-600 ..."${_scopeId}>State</th><th class="border border-slate-600 ..."${_scopeId}>City</th><th class="border border-slate-600 ..."${_scopeId}>State</th><th class="border border-slate-600 ..."${_scopeId}>City</th></tr></thead><tbody${_scopeId}><tr${_scopeId}><td class="border border-slate-700 px-2"${_scopeId}>Indiana</td><td class="border border-slate-700 px-2"${_scopeId}>Indianapolis</td><td class="border border-slate-700 px-2"${_scopeId}>Indiana</td><td class="border border-slate-700 px-2"${_scopeId}>Indianapolis</td></tr><tr${_scopeId}><td class="border border-slate-700 px-2"${_scopeId}>Ohio</td><td class="border border-slate-700 px-2"${_scopeId}>Columbus</td><td class="border border-slate-700 px-2"${_scopeId}>Ohio</td><td class="border border-slate-700 px-2"${_scopeId}>Columbus</td></tr><tr${_scopeId}><td class="border border-slate-700 px-2"${_scopeId}>Michigan</td><td class="border border-slate-700 px-2"${_scopeId}>Detroit</td><td class="border border-slate-700 px-2"${_scopeId}>Michigan</td><td class="border border-slate-700 px-2"${_scopeId}>Detroit</td></tr><tr${_scopeId}><td class="border border-slate-700 px-2"${_scopeId}>Michigan</td><td class="border border-slate-700 px-2"${_scopeId}>Detroit</td><td class="border border-slate-700 px-2"${_scopeId}>Michigan</td><td class="border border-slate-700 px-2"${_scopeId}>Detroit</td></tr><tr${_scopeId}><td class="border border-slate-700 px-2"${_scopeId}>Michigan</td><td class="border border-slate-700 px-2"${_scopeId}>Detroit</td><td class="border border-slate-700 px-2"${_scopeId}>Michigan</td><td class="border border-slate-700 px-2"${_scopeId}>Detroit</td></tr></tbody></table></div><div class="w-full bg-slate-400 p-8 mt-3"${_scopeId}><div class="w-full bg-grey-lightest" style="${ssrRenderStyle({ "padding-top": "4rem" })}"${_scopeId}><div class="container mx-auto py-8"${_scopeId}><div class="w-5/6 lg:w-1/2 mx-auto bg-white rounded shadow"${_scopeId}><div class="py-4 px-8 text-black text-xl border-b border-grey-lighter"${_scopeId}>Register for a free account </div><div class="py-4 px-8"${_scopeId}><div class="flex mb-4"${_scopeId}><div class="w-1/2 mr-1"${_scopeId}><label class="block text-grey-darker text-sm font-bold mb-2" for="first_name"${_scopeId}>First Name</label><input class="appearance-none border rounded w-full py-2 px-3 text-grey-darker" id="first_name" type="text" placeholder="Your first name"${_scopeId}></div><div class="w-1/2 ml-1"${_scopeId}><label class="block text-grey-darker text-sm font-bold mb-2" for="last_name"${_scopeId}>Last Name</label><input class="appearance-none border rounded w-full py-2 px-3 text-grey-darker" id="last_name" type="text" placeholder="Your last name"${_scopeId}></div></div><div class="mb-4"${_scopeId}><label class="block text-grey-darker text-sm font-bold mb-2" for="email"${_scopeId}>Email Address</label><input class="appearance-none border rounded w-full py-2 px-3 text-grey-darker" id="email" type="email" placeholder="Your email address"${_scopeId}></div><div class="mb-4"${_scopeId}><label class="block text-grey-darker text-sm font-bold mb-2" for="password"${_scopeId}>Password</label><input class="appearance-none border rounded w-full py-2 px-3 text-grey-darker" id="password" type="password" placeholder="Your secure password"${_scopeId}><p class="text-grey text-xs mt-1"${_scopeId}>At least 6 characters</p></div><div class="flex items-center justify-between mt-8"${_scopeId}><button class="bg-blue hover:bg-blue-dark text-white font-bold py-2 px-4 rounded-full" type="submit"${_scopeId}> Sign Up </button></div></div></div><p class="text-center my-4"${_scopeId}><a href="#" class="text-grey-dark text-sm no-underline hover:text-grey-darker"${_scopeId}>I already have an account</a></p></div></div></div><div class="w-full bg-slate-400 p-8 mt-3"${_scopeId}><div class="min-w-screen min-h-screen bg-gray-200 flex items-center justify-center px-5 py-5"${_scopeId}><div class="w-full max-w-3xl"${_scopeId}><div class="-mx-2 md:flex"${_scopeId}><div class="w-full md:w-1/3 px-2"${_scopeId}><div class="rounded-lg shadow-sm mb-4"${_scopeId}><div class="rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden"${_scopeId}><div class="px-3 pt-8 pb-10 text-center relative z-10"${_scopeId}><h4 class="text-sm uppercase text-gray-500 leading-tight"${_scopeId}>Users</h4><h3 class="text-3xl text-gray-700 font-semibold leading-tight my-3"${_scopeId}>3,682</h3><p class="text-xs text-green-500 leading-tight"${_scopeId}>▲ 57.1%</p></div><div class="absolute bottom-0 inset-x-0"${_scopeId}><canvas id="chart1" height="70"${_scopeId}></canvas></div></div></div></div><div class="w-full md:w-1/3 px-2"${_scopeId}><div class="rounded-lg shadow-sm mb-4"${_scopeId}><div class="rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden"${_scopeId}><div class="px-3 pt-8 pb-10 text-center relative z-10"${_scopeId}><h4 class="text-sm uppercase text-gray-500 leading-tight"${_scopeId}>Subscribers</h4><h3 class="text-3xl text-gray-700 font-semibold leading-tight my-3"${_scopeId}>11,427</h3><p class="text-xs text-red-500 leading-tight"${_scopeId}>▼ 42.8%</p></div><div class="absolute bottom-0 inset-x-0"${_scopeId}><canvas id="chart2" height="70"${_scopeId}></canvas></div></div></div></div><div class="w-full md:w-1/3 px-2"${_scopeId}><div class="rounded-lg shadow-sm mb-4"${_scopeId}><div class="rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden"${_scopeId}><div class="px-3 pt-8 pb-10 text-center relative z-10"${_scopeId}><h4 class="text-sm uppercase text-gray-500 leading-tight"${_scopeId}>Comments</h4><h3 class="text-3xl text-gray-700 font-semibold leading-tight my-3"${_scopeId}>8,028</h3><p class="text-xs text-green-500 leading-tight"${_scopeId}>▲ 8.2%</p></div><div class="absolute bottom-0 inset-x-0"${_scopeId}><canvas id="chart3" height="70"${_scopeId}></canvas></div></div></div></div></div></div></div></div><div class="grid grid-cols-1 sm:grid-cols-2 gap-3 py-2"${_scopeId}><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div></div><div class="grid grid-cols-2 sm:grid-cols-3 gap-3 py-2"${_scopeId}><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div></div><div class="grid grid-cols-2 sm:grid-cols-4 gap-3 py-2"${_scopeId}><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div></div><div class="grid grid-cols-2 sm:grid-cols-3 gap-3 py-2"${_scopeId}><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div><div class="bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm"${_scopeId}><h1${_scopeId}>Card</h1></div></div>`);
          } else {
            return [
              createVNode("div", { class: "w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out" }, [
                createVNode("div", { class: "grid xs:grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" }, [
                  createVNode("div", { class: "pl-1 w-full h-20 bg-green-400 rounded-lg shadow-md" }, [
                    createVNode("div", { class: "flex w-full h-full py-2 px-4 bg-gray-50 rounded-lg justify-between" }, [
                      createVNode("div", { class: "my-auto" }, [
                        createVNode("p", { class: "font-bold text-sm sm:text-md" }, "EARNINGS (MONTHLY)"),
                        createVNode("p", { class: "text-lg" }, "$40,000")
                      ]),
                      createVNode("div", { class: "my-auto" }, [
                        (openBlock(), createBlock("svg", {
                          xmlns: "http://www.w3.org/2000/svg",
                          class: "h-6 w-6",
                          fill: "none",
                          viewBox: "0 0 24 24",
                          stroke: "currentColor"
                        }, [
                          createVNode("path", {
                            "stroke-linecap": "round",
                            "stroke-linejoin": "round",
                            "stroke-width": "2",
                            d: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                          })
                        ]))
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "pl-1 w-full h-20 bg-blue-500 rounded-lg shadow-md" }, [
                    createVNode("div", { class: "flex w-full h-full py-2 px-4 bg-gray-50 rounded-lg justify-between" }, [
                      createVNode("div", { class: "my-auto" }, [
                        createVNode("p", { class: "font-bold text-sm sm:text-md" }, "EARNINGS (ANNUAL)"),
                        createVNode("p", { class: "text-lg" }, "$215,000")
                      ]),
                      createVNode("div", { class: "my-auto" }, [
                        (openBlock(), createBlock("svg", {
                          xmlns: "http://www.w3.org/2000/svg",
                          class: "h-6 w-6",
                          fill: "none",
                          viewBox: "0 0 24 24",
                          stroke: "currentColor"
                        }, [
                          createVNode("path", {
                            "stroke-linecap": "round",
                            "stroke-linejoin": "round",
                            "stroke-width": "2",
                            d: "M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                          })
                        ]))
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "pl-1 w-full h-20 bg-purple-500 rounded-lg shadow-md" }, [
                    createVNode("div", { class: "flex w-full h-full py-2 px-4 bg-gray-100 rounded-lg justify-between" }, [
                      createVNode("div", { class: "my-auto" }, [
                        createVNode("p", { class: "font-bold text-sm sm:text-md" }, "TASKS"),
                        createVNode("p", { class: "text-lg" }, "50%")
                      ]),
                      createVNode("div", { class: "my-auto" }, [
                        (openBlock(), createBlock("svg", {
                          xmlns: "http://www.w3.org/2000/svg",
                          class: "h-6 w-6",
                          fill: "none",
                          viewBox: "0 0 24 24",
                          stroke: "currentColor"
                        }, [
                          createVNode("path", {
                            "stroke-linecap": "round",
                            "stroke-linejoin": "round",
                            "stroke-width": "2",
                            d: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                          })
                        ]))
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "pl-1 w-full h-20 bg-yellow-400 rounded-lg shadow-md" }, [
                    createVNode("div", { class: "flex w-full h-full py-2 px-4 bg-gray-100 rounded-lg justify-between" }, [
                      createVNode("div", { class: "my-auto" }, [
                        createVNode("p", { class: "font-bold text-sm sm:text-md" }, "PENDING REQUESTS"),
                        createVNode("p", { class: "text-lg" }, "18")
                      ]),
                      createVNode("div", { class: "my-auto" }, [
                        (openBlock(), createBlock("svg", {
                          xmlns: "http://www.w3.org/2000/svg",
                          class: "h-6 w-6",
                          fill: "none",
                          viewBox: "0 0 24 24",
                          stroke: "currentColor"
                        }, [
                          createVNode("path", {
                            "stroke-linecap": "round",
                            "stroke-linejoin": "round",
                            "stroke-width": "2",
                            d: "M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                          })
                        ]))
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out" }, [
                createVNode("div", { class: "grid grid-cols-3 gap-4" }, [
                  createVNode("div", { class: "w-full" }, [
                    createVNode("div", { class: "rounded-lg shadow-sm mb-4" }, [
                      createVNode("div", { class: "rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden" }, [
                        createVNode("div", { class: "px-3 pt-8 pb-10 text-center relative z-10" }, [
                          createVNode("h4", { class: "text-sm uppercase text-gray-500 leading-tight" }, "Users"),
                          createVNode("h3", { class: "text-3xl text-gray-700 font-semibold leading-tight my-3" }, "3,682"),
                          createVNode("p", { class: "text-xs text-green-500 leading-tight" }, "▲ 57.1%")
                        ]),
                        createVNode("div", { class: "absolute bottom-0 inset-x-0" }, [
                          createVNode("canvas", {
                            id: "chart1",
                            height: "70"
                          })
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "w-full" }, [
                    createVNode("div", { class: "rounded-lg shadow-sm mb-4" }, [
                      createVNode("div", { class: "rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden" }, [
                        createVNode("div", { class: "px-3 pt-8 pb-10 text-center relative z-10" }, [
                          createVNode("h4", { class: "text-sm uppercase text-gray-500 leading-tight" }, "Subscribers"),
                          createVNode("h3", { class: "text-3xl text-gray-700 font-semibold leading-tight my-3" }, "11,427"),
                          createVNode("p", { class: "text-xs text-red-500 leading-tight" }, "▼ 42.8%")
                        ]),
                        createVNode("div", { class: "absolute bottom-0 inset-x-0" }, [
                          createVNode("canvas", {
                            id: "chart2",
                            height: "70"
                          })
                        ])
                      ])
                    ])
                  ]),
                  createVNode("div", { class: "w-full" }, [
                    createVNode("div", { class: "rounded-lg shadow-sm mb-4" }, [
                      createVNode("div", { class: "rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden" }, [
                        createVNode("div", { class: "px-3 pt-8 pb-10 text-center relative z-10" }, [
                          createVNode("h4", { class: "text-sm uppercase text-gray-500 leading-tight" }, "Comments"),
                          createVNode("h3", { class: "text-3xl text-gray-700 font-semibold leading-tight my-3" }, "8,028"),
                          createVNode("p", { class: "text-xs text-green-500 leading-tight" }, "▲ 8.2%")
                        ]),
                        createVNode("div", { class: "absolute bottom-0 inset-x-0" }, [
                          createVNode("canvas", {
                            id: "chart3",
                            height: "70"
                          })
                        ])
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out" }, [
                createVNode("form", {
                  onSubmit: withModifiers(submit, ["prevent"])
                }, [
                  createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3" }, [
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        placeholder: "Enter Your Name",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "last_name",
                        value: "Last Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "last_name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "last_name",
                        value: "Last Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "last_name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        placeholder: "Enter Your Name",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "last_name",
                        value: "Last Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "last_name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "last_name",
                        value: "Last Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "last_name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "name",
                        value: "Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        placeholder: "Enter Your Name",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "last_name",
                        value: "Last Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "last_name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ]),
                    createVNode("div", null, [
                      createVNode(_sfc_main$2, {
                        for: "last_name",
                        value: "Last Name"
                      }),
                      createVNode(_sfc_main$3, {
                        id: "last_name",
                        modelValue: unref(form).name,
                        "onUpdate:modelValue": ($event) => unref(form).name = $event,
                        type: "text",
                        class: "block w-full",
                        required: "",
                        autofocus: "",
                        autocomplete: "name"
                      }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                      createVNode(_sfc_main$4, {
                        class: "mt-2",
                        message: unref(form).errors.name
                      }, null, 8, ["message"])
                    ])
                  ]),
                  createVNode("div", { class: "flex items-center justify-end mt-4" }, [
                    createVNode(_sfc_main$5, {
                      class: ["ms-4", { "opacity-25": unref(form).processing }],
                      disabled: unref(form).processing
                    }, {
                      default: withCtx(() => [
                        createTextVNode(" Submit ")
                      ]),
                      _: 1
                    }, 8, ["class", "disabled"])
                  ])
                ], 32)
              ]),
              createVNode("div", { class: "w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out" }, [
                createVNode("div", { class: "container mx-auto px-4 sm:px-8" }, [
                  createVNode("div", { class: "py-8" }, [
                    createVNode("div", null, [
                      createVNode("h2", { class: "text-2xl font-semibold leading-tight" }, "Invoices")
                    ]),
                    createVNode("div", { class: "-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto" }, [
                      createVNode("div", { class: "inline-block min-w-full shadow-md rounded-lg overflow-hidden" }, [
                        createVNode("table", { class: "min-w-full leading-normal" }, [
                          createVNode("thead", null, [
                            createVNode("tr", null, [
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider" }, " Client / Invoice "),
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider" }, " Amount "),
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider" }, " Issued / Due "),
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider" }, " Status "),
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100" })
                            ])
                          ]),
                          createVNode("tbody", null, [
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("div", { class: "flex" }, [
                                  createVNode("div", { class: "flex-shrink-0 w-10 h-10" }, [
                                    createVNode("img", {
                                      class: "w-full h-full rounded-full",
                                      src: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2.2&w=160&h=160&q=80",
                                      alt: ""
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-3" }, [
                                    createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Molly Sanders "),
                                    createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "000004")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "$20,000"),
                                createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "USD")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Sept 28, 2019"),
                                createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "Due in 3 days")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("span", { class: "relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight" }, [
                                  createVNode("span", {
                                    "aria-hidden": "",
                                    class: "absolute inset-0 bg-green-200 opacity-50 rounded-full"
                                  }),
                                  createVNode("span", { class: "relative" }, "Paid")
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm text-right" }, [
                                createVNode("button", {
                                  type: "button",
                                  class: "inline-block text-gray-500 hover:text-gray-700"
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    class: "inline-block h-6 w-6 fill-current",
                                    viewBox: "0 0 24 24"
                                  }, [
                                    createVNode("path", { d: "M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm-2 6a2 2 0 104 0 2 2 0 00-4 0z" })
                                  ]))
                                ])
                              ])
                            ]),
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("div", { class: "flex" }, [
                                  createVNode("div", { class: "flex-shrink-0 w-10 h-10" }, [
                                    createVNode("img", {
                                      class: "w-full h-full rounded-full",
                                      src: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2.2&w=160&h=160&q=80",
                                      alt: ""
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-3" }, [
                                    createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Michael Roberts "),
                                    createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "000003")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "$214,000"),
                                createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "USD")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Sept 25, 2019"),
                                createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "Due in 6 days")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("span", { class: "relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight" }, [
                                  createVNode("span", {
                                    "aria-hidden": "",
                                    class: "absolute inset-0 bg-green-200 opacity-50 rounded-full"
                                  }),
                                  createVNode("span", { class: "relative" }, "Paid")
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm text-right" }, [
                                createVNode("button", {
                                  type: "button",
                                  class: "inline-block text-gray-500 hover:text-gray-700"
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    class: "inline-block h-6 w-6 fill-current",
                                    viewBox: "0 0 24 24"
                                  }, [
                                    createVNode("path", { d: "M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm-2 6a2 2 0 104 0 2 2 0 00-4 0z" })
                                  ]))
                                ])
                              ])
                            ]),
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("div", { class: "flex" }, [
                                  createVNode("div", { class: "flex-shrink-0 w-10 h-10" }, [
                                    createVNode("img", {
                                      class: "w-full h-full rounded-full",
                                      src: "https://images.unsplash.com/photo-1540845511934-7721dd7adec3?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2.2&w=160&h=160&q=80",
                                      alt: ""
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-3" }, [
                                    createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Devin Childs "),
                                    createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "000002")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "$20,000"),
                                createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "USD")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Sept 14, 2019"),
                                createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "Due in 2 weeks")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("span", { class: "relative inline-block px-3 py-1 font-semibold text-orange-900 leading-tight" }, [
                                  createVNode("span", {
                                    "aria-hidden": "",
                                    class: "absolute inset-0 bg-orange-200 opacity-50 rounded-full"
                                  }),
                                  createVNode("span", { class: "relative" }, "Pending")
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm text-right" }, [
                                createVNode("button", {
                                  type: "button",
                                  class: "inline-block text-gray-500 hover:text-gray-700"
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    class: "inline-block h-6 w-6 fill-current",
                                    viewBox: "0 0 24 24"
                                  }, [
                                    createVNode("path", { d: "M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm-2 6a2 2 0 104 0 2 2 0 00-4 0z" })
                                  ]))
                                ])
                              ])
                            ]),
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm" }, [
                                createVNode("div", { class: "flex" }, [
                                  createVNode("div", { class: "flex-shrink-0 w-10 h-10" }, [
                                    createVNode("img", {
                                      class: "w-full h-full rounded-full",
                                      src: "https://images.unsplash.com/photo-1522609925277-66fea332c575?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2.2&h=160&w=160&q=80",
                                      alt: ""
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-3" }, [
                                    createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Frederick Nicholas "),
                                    createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "000001")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "$12,000"),
                                createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, "USD")
                              ]),
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Sept 6, 2019"),
                                createVNode("p", { class: "text-gray-600 whitespace-no-wrap" }, " Due 3 weeks ago ")
                              ]),
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm" }, [
                                createVNode("span", { class: "relative inline-block px-3 py-1 font-semibold text-red-900 leading-tight" }, [
                                  createVNode("span", {
                                    "aria-hidden": "",
                                    class: "absolute inset-0 bg-red-200 opacity-50 rounded-full"
                                  }),
                                  createVNode("span", { class: "relative" }, "Overdue")
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm text-right" }, [
                                createVNode("button", {
                                  type: "button",
                                  class: "inline-block text-gray-500 hover:text-gray-700"
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    class: "inline-block h-6 w-6 fill-current",
                                    viewBox: "0 0 24 24"
                                  }, [
                                    createVNode("path", { d: "M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm-2 6a2 2 0 104 0 2 2 0 00-4 0z" })
                                  ]))
                                ])
                              ])
                            ])
                          ])
                        ])
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out" }, [
                createVNode("h1", { class: "font-bold text-xl" }, " Kullanıcılar ve iş sayıları"),
                createVNode("div", { class: "relative overflow-x-auto" }, [
                  createVNode("table", { class: "w-full text-sm text-left text-gray-500 dark:text-gray-400" }, [
                    createVNode("thead", { class: "text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400" }, [
                      createVNode("tr", null, [
                        createVNode("th", {
                          scope: "col",
                          class: "px-6 py-3"
                        }, "#"),
                        createVNode("th", {
                          scope: "col",
                          class: "px-6 py-3"
                        }, "Name"),
                        createVNode("th", {
                          scope: "col",
                          class: "px-6 py-3"
                        }, "Surname"),
                        createVNode("th", {
                          scope: "col",
                          class: "px-6 py-3"
                        }, "Work Count")
                      ])
                    ]),
                    createVNode("tbody", null, [
                      createVNode("tr", { class: "hover:bg-gray-100 bg-white border-b dark:bg-gray-800 dark:border-gray-700" }, [
                        createVNode("th", {
                          scope: "row",
                          class: "px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"
                        }, " 1 "),
                        createVNode("td", { class: "px-6 py-4" }, "Hakan"),
                        createVNode("td", { class: "px-6 py-4" }, "Akgul"),
                        createVNode("td", { class: "px-6 py-4" }, "22")
                      ]),
                      createVNode("tr", { class: "hover:bg-gray-100 bg-white border-b dark:bg-gray-800 dark:border-gray-700" }, [
                        createVNode("th", {
                          scope: "row",
                          class: "px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"
                        }, " 1 "),
                        createVNode("td", { class: "px-6 py-4" }, "John"),
                        createVNode("td", { class: "px-6 py-4" }, "Smith"),
                        createVNode("td", { class: "px-6 py-4" }, "4")
                      ]),
                      createVNode("tr", { class: "hover:bg-gray-100 bg-white border-b dark:bg-gray-800 dark:border-gray-700" }, [
                        createVNode("th", {
                          scope: "row",
                          class: "px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white"
                        }, " 1 "),
                        createVNode("td", { class: "px-6 py-4" }, "Hakan"),
                        createVNode("td", { class: "px-6 py-4" }, "Akgül"),
                        createVNode("td", { class: "px-6 py-4" }, "22")
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "w-full bg-white dark:bg-slate-900 p-8 mt-3 transition duration-1000 ease-in-out" }, [
                createVNode("div", { class: "container mx-auto px-4 sm:px-8" }, [
                  createVNode("div", { class: "py-8" }, [
                    createVNode("div", null, [
                      createVNode("h2", { class: "text-2xl font-semibold leading-tight" }, "Users")
                    ]),
                    createVNode("div", { class: "my-2 flex sm:flex-row flex-col" }, [
                      createVNode("div", { class: "flex flex-row mb-1 sm:mb-0" }, [
                        createVNode("div", { class: "relative" }, [
                          createVNode("select", { class: "appearance-none h-full rounded-l border block appearance-none w-full bg-white border-gray-400 text-gray-700 py-2 px-4 pr-8 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" }, [
                            createVNode("option", null, "5"),
                            createVNode("option", null, "10"),
                            createVNode("option", null, "20")
                          ]),
                          createVNode("div", { class: "pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700" }, [
                            (openBlock(), createBlock("svg", {
                              class: "fill-current h-4 w-4",
                              xmlns: "http://www.w3.org/2000/svg",
                              viewBox: "0 0 20 20"
                            }, [
                              createVNode("path", { d: "M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" })
                            ]))
                          ])
                        ]),
                        createVNode("div", { class: "relative" }, [
                          createVNode("select", { class: "appearance-none h-full rounded-r border-t sm:rounded-r-none sm:border-r-0 border-r border-b block appearance-none w-full bg-white border-gray-400 text-gray-700 py-2 px-4 pr-8 leading-tight focus:outline-none focus:border-l focus:border-r focus:bg-white focus:border-gray-500" }, [
                            createVNode("option", null, "All"),
                            createVNode("option", null, "Active"),
                            createVNode("option", null, "Inactive")
                          ]),
                          createVNode("div", { class: "pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700" }, [
                            (openBlock(), createBlock("svg", {
                              class: "fill-current h-4 w-4",
                              xmlns: "http://www.w3.org/2000/svg",
                              viewBox: "0 0 20 20"
                            }, [
                              createVNode("path", { d: "M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" })
                            ]))
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "block relative" }, [
                        createVNode("span", { class: "h-full absolute inset-y-0 left-0 flex items-center pl-2" }, [
                          (openBlock(), createBlock("svg", {
                            viewBox: "0 0 24 24",
                            class: "h-4 w-4 fill-current text-gray-500"
                          }, [
                            createVNode("path", { d: "M10 4a6 6 0 100 12 6 6 0 000-12zm-8 6a8 8 0 1114.32 4.906l5.387 5.387a1 1 0 01-1.414 1.414l-5.387-5.387A8 8 0 012 10z" })
                          ]))
                        ]),
                        createVNode("input", {
                          placeholder: "Search",
                          class: "appearance-none rounded-r rounded-l sm:rounded-l-none border border-gray-400 border-b block pl-8 pr-6 py-2 w-full bg-white text-sm placeholder-gray-400 text-gray-700 focus:bg-white focus:placeholder-gray-600 focus:text-gray-700 focus:outline-none"
                        })
                      ])
                    ]),
                    createVNode("div", { class: "-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto" }, [
                      createVNode("div", { class: "inline-block min-w-full shadow rounded-lg overflow-hidden" }, [
                        createVNode("table", { class: "min-w-full leading-normal" }, [
                          createVNode("thead", null, [
                            createVNode("tr", null, [
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" }, " User "),
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" }, " Rol "),
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" }, " Created at "),
                              createVNode("th", { class: "px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider" }, " Status ")
                            ])
                          ]),
                          createVNode("tbody", null, [
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("div", { class: "flex items-center" }, [
                                  createVNode("div", { class: "flex-shrink-0 w-10 h-10" }, [
                                    createVNode("img", {
                                      class: "w-full h-full rounded-full",
                                      src: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2.2&w=160&h=160&q=80",
                                      alt: ""
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-3" }, [
                                    createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Vera Carpenter ")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Admin")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Jan 21, 2020 ")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("span", { class: "relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight" }, [
                                  createVNode("span", {
                                    "aria-hidden": "",
                                    class: "absolute inset-0 bg-green-200 opacity-50 rounded-full"
                                  }),
                                  createVNode("span", { class: "relative" }, "Activo")
                                ])
                              ])
                            ]),
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("div", { class: "flex items-center" }, [
                                  createVNode("div", { class: "flex-shrink-0 w-10 h-10" }, [
                                    createVNode("img", {
                                      class: "w-full h-full rounded-full",
                                      src: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2.2&w=160&h=160&q=80",
                                      alt: ""
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-3" }, [
                                    createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Blake Bowman ")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Editor")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Jan 01, 2020 ")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("span", { class: "relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight" }, [
                                  createVNode("span", {
                                    "aria-hidden": "",
                                    class: "absolute inset-0 bg-green-200 opacity-50 rounded-full"
                                  }),
                                  createVNode("span", { class: "relative" }, "Activo")
                                ])
                              ])
                            ]),
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("div", { class: "flex items-center" }, [
                                  createVNode("div", { class: "flex-shrink-0 w-10 h-10" }, [
                                    createVNode("img", {
                                      class: "w-full h-full rounded-full",
                                      src: "https://images.unsplash.com/photo-1540845511934-7721dd7adec3?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2.2&w=160&h=160&q=80",
                                      alt: ""
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-3" }, [
                                    createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Dana Moore ")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Editor")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Jan 10, 2020 ")
                              ]),
                              createVNode("td", { class: "px-5 py-5 border-b border-gray-200 bg-white text-sm" }, [
                                createVNode("span", { class: "relative inline-block px-3 py-1 font-semibold text-orange-900 leading-tight" }, [
                                  createVNode("span", {
                                    "aria-hidden": "",
                                    class: "absolute inset-0 bg-orange-200 opacity-50 rounded-full"
                                  }),
                                  createVNode("span", { class: "relative" }, "Suspended")
                                ])
                              ])
                            ]),
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm" }, [
                                createVNode("div", { class: "flex items-center" }, [
                                  createVNode("div", { class: "flex-shrink-0 w-10 h-10" }, [
                                    createVNode("img", {
                                      class: "w-full h-full rounded-full",
                                      src: "https://images.unsplash.com/photo-1522609925277-66fea332c575?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2.2&h=160&w=160&q=80",
                                      alt: ""
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-3" }, [
                                    createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, " Alonzo Cox ")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Admin")
                              ]),
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm" }, [
                                createVNode("p", { class: "text-gray-900 whitespace-no-wrap" }, "Jan 18, 2020")
                              ]),
                              createVNode("td", { class: "px-5 py-5 bg-white text-sm" }, [
                                createVNode("span", { class: "relative inline-block px-3 py-1 font-semibold text-red-900 leading-tight" }, [
                                  createVNode("span", {
                                    "aria-hidden": "",
                                    class: "absolute inset-0 bg-red-200 opacity-50 rounded-full"
                                  }),
                                  createVNode("span", { class: "relative" }, "Inactive")
                                ])
                              ])
                            ])
                          ])
                        ]),
                        createVNode("div", { class: "px-5 py-5 bg-white border-t flex flex-col xs:flex-row items-center xs:justify-between" }, [
                          createVNode("span", { class: "text-xs xs:text-sm text-gray-900" }, " Showing 1 to 4 of 50 Entries "),
                          createVNode("div", { class: "inline-flex mt-2 xs:mt-0" }, [
                            createVNode("button", { class: "text-sm bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-l" }, " Prev "),
                            createVNode("button", { class: "text-sm bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-r" }, " Next ")
                          ])
                        ])
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "w-full bg-slate-400 p-8 mt-3" }, [
                createVNode("div", { class: "flex flex-col" }, [
                  createVNode("div", { class: "my-2 overflow-x-auto sm:-max-6 lg:-mx-8" }, [
                    createVNode("div", { class: "py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8" }, [
                      createVNode("div", { class: "shadow overflow-hidden border-b border-gray-200 m-4 sm:rounded-lg" }, [
                        createVNode("table", { class: "min-w-full divide-y divide-gray-200" }, [
                          createVNode("thead", { class: "bg-gray-50" }, [
                            createVNode("tr", null, [
                              createVNode("th", {
                                scope: "col",
                                class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                              }, " Name "),
                              createVNode("th", {
                                scope: "col",
                                class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                              }, " Title "),
                              createVNode("th", {
                                scope: "col",
                                class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                              }, " Status "),
                              createVNode("th", {
                                scope: "col",
                                class: "px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                              }, " Role "),
                              createVNode("th", {
                                scope: "col",
                                class: "relative px-6 py-3"
                              }, [
                                createVNode("span", { class: "sr-only" }, "Edit")
                              ])
                            ])
                          ]),
                          createVNode("tbody", { class: "bg-white divide-y divide-gray-200" }, [
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                createVNode("div", { class: "flex items-center" }, [
                                  createVNode("div", { class: "flex-shrink-0 h-10 w-10" }, [
                                    createVNode("img", {
                                      class: "h-10 w-10 rounded-full",
                                      src: "https://ui-avatars.com/api/?name=Sebas+Luna&background=random",
                                      alt: "Some"
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-4" }, [
                                    createVNode("div", { class: "text-sm font-medium text-gray-900" }, " Sebastian Luna "),
                                    createVNode("div", { class: "text-sm text-gray-500" }, " email@email.com ")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                createVNode("div", { class: "text-sm text-gray-900" }, "Co-founder"),
                                createVNode("div", { class: "text-sm text-gray-500" }, "Developer")
                              ]),
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                createVNode("span", { class: "px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800" }, "Active")
                              ]),
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-500" }, " Admin "),
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-right text-sm font-medium" }, [
                                createVNode("a", {
                                  href: "#",
                                  class: "text-indigo-600 hover:text-indigo-900"
                                }, "Edit")
                              ])
                            ]),
                            createVNode("tr", null, [
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                createVNode("div", { class: "flex items-center" }, [
                                  createVNode("div", { class: "flex-shrink-0 h-10 w-10" }, [
                                    createVNode("img", {
                                      class: "h-10 w-10 rounded-full",
                                      src: "https://ui-avatars.com/api/?name=Vilfer+Alvarez&background=random",
                                      alt: "Some"
                                    })
                                  ]),
                                  createVNode("div", { class: "ml-4" }, [
                                    createVNode("div", { class: "text-sm font-medium text-gray-900" }, " Vilfer Alvarez "),
                                    createVNode("div", { class: "text-sm text-gray-500" }, " email@email.com ")
                                  ])
                                ])
                              ]),
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                createVNode("div", { class: "text-sm text-gray-900" }, "Co-founder"),
                                createVNode("div", { class: "text-sm text-gray-500" }, "Developer")
                              ]),
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap" }, [
                                createVNode("span", { class: "px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800" }, "Active")
                              ]),
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-sm text-gray-500" }, " Admin "),
                              createVNode("td", { class: "px-6 py-4 whitespace-nowrap text-right text-sm font-medium" }, [
                                createVNode("a", {
                                  href: "#",
                                  class: "text-indigo-600 hover:text-indigo-900"
                                }, "Edit")
                              ])
                            ])
                          ])
                        ])
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "w-full bg-slate-400 p-8 mt-3" }, [
                createVNode("table", { class: "border-collapse border border-slate-500 w-full" }, [
                  createVNode("thead", null, [
                    createVNode("tr", { class: "bg-gray-200" }, [
                      createVNode("th", { class: "border border-slate-600 ..." }, "State"),
                      createVNode("th", { class: "border border-slate-600 ..." }, "City"),
                      createVNode("th", { class: "border border-slate-600 ..." }, "State"),
                      createVNode("th", { class: "border border-slate-600 ..." }, "City")
                    ])
                  ]),
                  createVNode("tbody", null, [
                    createVNode("tr", null, [
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Indiana"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Indianapolis"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Indiana"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Indianapolis")
                    ]),
                    createVNode("tr", null, [
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Ohio"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Columbus"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Ohio"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Columbus")
                    ]),
                    createVNode("tr", null, [
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Michigan"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Detroit"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Michigan"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Detroit")
                    ]),
                    createVNode("tr", null, [
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Michigan"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Detroit"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Michigan"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Detroit")
                    ]),
                    createVNode("tr", null, [
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Michigan"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Detroit"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Michigan"),
                      createVNode("td", { class: "border border-slate-700 px-2" }, "Detroit")
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "w-full bg-slate-400 p-8 mt-3" }, [
                createVNode("div", {
                  class: "w-full bg-grey-lightest",
                  style: { "padding-top": "4rem" }
                }, [
                  createVNode("div", { class: "container mx-auto py-8" }, [
                    createVNode("div", { class: "w-5/6 lg:w-1/2 mx-auto bg-white rounded shadow" }, [
                      createVNode("div", { class: "py-4 px-8 text-black text-xl border-b border-grey-lighter" }, "Register for a free account "),
                      createVNode("div", { class: "py-4 px-8" }, [
                        createVNode("div", { class: "flex mb-4" }, [
                          createVNode("div", { class: "w-1/2 mr-1" }, [
                            createVNode("label", {
                              class: "block text-grey-darker text-sm font-bold mb-2",
                              for: "first_name"
                            }, "First Name"),
                            createVNode("input", {
                              class: "appearance-none border rounded w-full py-2 px-3 text-grey-darker",
                              id: "first_name",
                              type: "text",
                              placeholder: "Your first name"
                            })
                          ]),
                          createVNode("div", { class: "w-1/2 ml-1" }, [
                            createVNode("label", {
                              class: "block text-grey-darker text-sm font-bold mb-2",
                              for: "last_name"
                            }, "Last Name"),
                            createVNode("input", {
                              class: "appearance-none border rounded w-full py-2 px-3 text-grey-darker",
                              id: "last_name",
                              type: "text",
                              placeholder: "Your last name"
                            })
                          ])
                        ]),
                        createVNode("div", { class: "mb-4" }, [
                          createVNode("label", {
                            class: "block text-grey-darker text-sm font-bold mb-2",
                            for: "email"
                          }, "Email Address"),
                          createVNode("input", {
                            class: "appearance-none border rounded w-full py-2 px-3 text-grey-darker",
                            id: "email",
                            type: "email",
                            placeholder: "Your email address"
                          })
                        ]),
                        createVNode("div", { class: "mb-4" }, [
                          createVNode("label", {
                            class: "block text-grey-darker text-sm font-bold mb-2",
                            for: "password"
                          }, "Password"),
                          createVNode("input", {
                            class: "appearance-none border rounded w-full py-2 px-3 text-grey-darker",
                            id: "password",
                            type: "password",
                            placeholder: "Your secure password"
                          }),
                          createVNode("p", { class: "text-grey text-xs mt-1" }, "At least 6 characters")
                        ]),
                        createVNode("div", { class: "flex items-center justify-between mt-8" }, [
                          createVNode("button", {
                            class: "bg-blue hover:bg-blue-dark text-white font-bold py-2 px-4 rounded-full",
                            type: "submit"
                          }, " Sign Up ")
                        ])
                      ])
                    ]),
                    createVNode("p", { class: "text-center my-4" }, [
                      createVNode("a", {
                        href: "#",
                        class: "text-grey-dark text-sm no-underline hover:text-grey-darker"
                      }, "I already have an account")
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "w-full bg-slate-400 p-8 mt-3" }, [
                createVNode("div", { class: "min-w-screen min-h-screen bg-gray-200 flex items-center justify-center px-5 py-5" }, [
                  createVNode("div", { class: "w-full max-w-3xl" }, [
                    createVNode("div", { class: "-mx-2 md:flex" }, [
                      createVNode("div", { class: "w-full md:w-1/3 px-2" }, [
                        createVNode("div", { class: "rounded-lg shadow-sm mb-4" }, [
                          createVNode("div", { class: "rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden" }, [
                            createVNode("div", { class: "px-3 pt-8 pb-10 text-center relative z-10" }, [
                              createVNode("h4", { class: "text-sm uppercase text-gray-500 leading-tight" }, "Users"),
                              createVNode("h3", { class: "text-3xl text-gray-700 font-semibold leading-tight my-3" }, "3,682"),
                              createVNode("p", { class: "text-xs text-green-500 leading-tight" }, "▲ 57.1%")
                            ]),
                            createVNode("div", { class: "absolute bottom-0 inset-x-0" }, [
                              createVNode("canvas", {
                                id: "chart1",
                                height: "70"
                              })
                            ])
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "w-full md:w-1/3 px-2" }, [
                        createVNode("div", { class: "rounded-lg shadow-sm mb-4" }, [
                          createVNode("div", { class: "rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden" }, [
                            createVNode("div", { class: "px-3 pt-8 pb-10 text-center relative z-10" }, [
                              createVNode("h4", { class: "text-sm uppercase text-gray-500 leading-tight" }, "Subscribers"),
                              createVNode("h3", { class: "text-3xl text-gray-700 font-semibold leading-tight my-3" }, "11,427"),
                              createVNode("p", { class: "text-xs text-red-500 leading-tight" }, "▼ 42.8%")
                            ]),
                            createVNode("div", { class: "absolute bottom-0 inset-x-0" }, [
                              createVNode("canvas", {
                                id: "chart2",
                                height: "70"
                              })
                            ])
                          ])
                        ])
                      ]),
                      createVNode("div", { class: "w-full md:w-1/3 px-2" }, [
                        createVNode("div", { class: "rounded-lg shadow-sm mb-4" }, [
                          createVNode("div", { class: "rounded-lg bg-white shadow-lg md:shadow-xl relative overflow-hidden" }, [
                            createVNode("div", { class: "px-3 pt-8 pb-10 text-center relative z-10" }, [
                              createVNode("h4", { class: "text-sm uppercase text-gray-500 leading-tight" }, "Comments"),
                              createVNode("h3", { class: "text-3xl text-gray-700 font-semibold leading-tight my-3" }, "8,028"),
                              createVNode("p", { class: "text-xs text-green-500 leading-tight" }, "▲ 8.2%")
                            ]),
                            createVNode("div", { class: "absolute bottom-0 inset-x-0" }, [
                              createVNode("canvas", {
                                id: "chart3",
                                height: "70"
                              })
                            ])
                          ])
                        ])
                      ])
                    ])
                  ])
                ])
              ]),
              createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 gap-3 py-2" }, [
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ]),
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ])
              ]),
              createVNode("div", { class: "grid grid-cols-2 sm:grid-cols-3 gap-3 py-2" }, [
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ]),
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ]),
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ])
              ]),
              createVNode("div", { class: "grid grid-cols-2 sm:grid-cols-4 gap-3 py-2" }, [
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ]),
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ]),
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ]),
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ])
              ]),
              createVNode("div", { class: "grid grid-cols-2 sm:grid-cols-3 gap-3 py-2" }, [
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ]),
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
                ]),
                createVNode("div", { class: "bg-slate-300 hover:bg-slate-400 dark:bg-slate-900 dark:hover:bg-slate-800 transition duration-1000 ease-in-out h-64 w-full rounded-sm" }, [
                  createVNode("h1", null, "Card")
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
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Backend/Test.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
