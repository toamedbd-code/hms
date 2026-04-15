
<script setup>
import { ref, computed, watch } from 'vue';

// Props
const props = defineProps({
    show: {
        type: Boolean,
        default: false
    }
});

// Emits
const emit = defineEmits(['close', 'save']);

const now = new Date();
const pad2 = (value) => String(value).padStart(2, '0');
const billingDate = ref(
    `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())}`
);
const billingTime = ref(`${pad2(now.getHours())}:${pad2(now.getMinutes())}`);

// Reactive data
const itemForm = ref({
    category: '',
    itemName: 'CBC',
    unitPrice: 600,
    quantity: 1.00,
    totalAmount: 600.0
});

const patientForm = ref({
    selDoctor: '',
    pc: '',
    patientMobile: '',
    gender: '',
    cardType: 'Cash',
    payMode: 'Cash',
    cardType2: ''
});

const summary = ref({
    total: 600,
    corpDueAmt: 0.00,
    pvtPcAmt: 600,
    paidAmt: 600,
    changeAmt: 0.00,
    receivingAmt: 0.00,
    deliveryDate: '01-May-2023 95:00 PM',
    remarks: ''
});

const commission = ref({
    total: 0.00,
    physystAmt: 0.00,
    slider: 50
});

const items = ref([
    {
        name: 'Anti HAV',
        unitPrice: 600,
        quantity: 1,
        totalAmount: 600,
        discount: 0,
        rugound: 0,
        netAmount: 600
    }
]);

// Watchers
watch([() => itemForm.value.quantity, () => itemForm.value.unitPrice], () => {
    itemForm.value.totalAmount = itemForm.value.quantity * itemForm.value.unitPrice;
});

// Methods
const addItem = () => {
    if (itemForm.value.itemName && itemForm.value.unitPrice > 0) {
        items.value.push({
            name: itemForm.value.itemName,
            unitPrice: itemForm.value.unitPrice,
            quantity: itemForm.value.quantity,
            totalAmount: itemForm.value.totalAmount,
            discount: 0,
            rugound: 0,
            netAmount: itemForm.value.totalAmount
        });

        // Reset form
        itemForm.value = {
            category: '',
            itemName: 'CBC',
            unitPrice: 600,
            quantity: 1.00,
            totalAmount: 600.0
        };

        updateSummary();
    }
};

const removeItem = (index) => {
    items.value.splice(index, 1);
    updateSummary();
};

const updateSummary = () => {
    const total = items.value.reduce((sum, item) => sum + item.netAmount, 0);
    summary.value.total = total;
    summary.value.pvtPcAmt = total;
    summary.value.paidAmt = total;
};

const closeModal = () => {
    emit('close');
};

const saveBill = () => {
    const billData = {
        items: items.value,
        patient: patientForm.value,
        summary: summary.value,
        commission: commission.value,
        billingDate: billingDate.value,
        billingTime: billingTime.value
    };
    
    emit('save', billData);
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-lg shadow-2xl max-w-[95vw] w-full mx-4 max-h-[95vh] overflow-y-auto">
            <div class="p-4">
                <!-- Item Details Section -->
                <div class="mb-4">
                    <div
                        class="flex flex-wrap justify-between items-center bg-[#053855] text-white px-4 py-2 text-sm font-semibold rounded-t gap-2">
                        <div>ITEM DETAILS</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex items-center gap-2 text-white">
                                <span class="text-[11px] font-semibold">Billing Date &amp; Time</span>
                                <input
                                    type="date"
                                    v-model="billingDate"
                                    class="px-2 py-1 border border-white/30 rounded text-[11px] bg-white/10 focus:border-white focus:outline-none"
                                />
                                <input
                                    type="time"
                                    v-model="billingTime"
                                    class="px-2 py-1 border border-white/30 rounded text-[11px] bg-white/10 focus:border-white focus:outline-none"
                                />
                            </div>
                            <button class="text-white hover:text-gray-300 p-1">📋</button>
                            <button class="text-white hover:text-gray-300 p-1">🖨</button>
                            <button class="text-white hover:text-gray-300 p-1">👤</button>
                            <button @click="closeModal"
                                class="text-red-500 hover:text-red-700 p-1">X</button>
                        </div>
                    </div>

                    <div class="border border-gray-300 border-t-0 p-4 bg-gray-50 rounded-b">
                        <div class="flex items-center space-x-6 text-sm">
                            <span class="font-medium text-gray-700"><strong>UNIT:</strong> Toamed
                                Ltd.</span>
                            <span class="font-medium text-gray-700"><strong>Counter:</strong> Pharmacy</span>
                            <span class="font-medium text-gray-700"><strong>Sales Person:</strong> System Adm</span>
                        </div>

                        <div class="grid grid-cols-12 gap-3 items-end mt-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                                <select v-model="itemForm.category"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">Select</option>
                                    <option value="pathology">Pathology</option>
                                    <option value="radiology">Radiology</option>
                                </select>
                            </div>

                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Item Name</label>
                                <select v-model="itemForm.itemName"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="CBC">CBC</option>
                                    <option value="Anti HAV">Anti HAV</option>
                                </select>
                            </div>

                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">U/Price</label>
                                <div class="flex">
                                    <input v-model="itemForm.unitPrice" type="number"
                                        class="w-20 px-2 py-1.5 border border-gray-300 rounded-l text-sm bg-yellow-100 focus:bg-yellow-200 focus:outline-none">
                                    <span
                                        class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs">%</span>
                                </div>
                            </div>

                            <div class="col-span-1">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Qty</label>
                                <input v-model="itemForm.quantity" type="number" step="0.01"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                            </div>

                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">T.Amt</label>
                                <input v-model="itemForm.totalAmount" type="number"
                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm bg-gray-100"
                                    readonly>
                            </div>

                            <div class="col-span-1">
                                <button @click="addItem"
                                    class="w-full h-8 bg-teal-600 text-white rounded hover:bg-teal-700 flex items-center justify-center font-bold text-lg">
                                    ✚
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Item List Table -->
                <div class="mb-4">
                    <div class="bg-slate-700 text-white px-4 py-2 text-sm font-semibold rounded-t">
                        ITEM LIST
                    </div>

                    <div class="border border-gray-300 border-t-0 rounded-b overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-teal-700 text-white">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Item Name</th>
                                    <th class="px-3 py-2 text-center font-semibold">U/Price</th>
                                    <th class="px-3 py-2 text-center font-semibold">Qty</th>
                                    <th class="px-3 py-2 text-center font-semibold">T.Amt</th>
                                    <th class="px-3 py-2 text-center font-semibold">Disc%</th>
                                    <th class="px-3 py-2 text-center font-semibold">Rugound</th>
                                    <th class="px-3 py-2 text-center font-semibold">Net Amt</th>
                                    <th class="px-3 py-2 text-center font-semibold">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <tr v-for="(item, index) in items" :key="index"
                                    class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-3 py-2 font-medium">{{ item.name }}</td>
                                    <td class="px-3 py-2 text-center">{{ item.unitPrice }}</td>
                                    <td class="px-3 py-2 text-center">{{ item.quantity }}</td>
                                    <td class="px-3 py-2 text-center">{{ item.totalAmount }}</td>
                                    <td class="px-3 py-2 text-center">{{ item.discount }}</td>
                                    <td class="px-3 py-2 text-center">{{ item.rugound }}</td>
                                    <td class="px-3 py-2 text-center font-semibold">{{ item.netAmount }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <button @click="removeItem(index)"
                                            class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600">
                                            🗑
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bottom Section - 3 Columns -->
                <div class="grid grid-cols-3 gap-4">

                    <!-- Patient Details -->
                    <div>
                        <div class="bg-teal-600 text-white px-4 py-2 text-sm font-semibold rounded-t">
                            PATIENT DETAILS
                        </div>
                        <div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-gray-700 w-20">Sel. Doctor</label>
                                <select v-model="patientForm.selDoctor"
                                    class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">Select</option>
                                </select>
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-gray-700 w-20">PC</label>
                                <select v-model="patientForm.pc"
                                    class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">Select</option>
                                </select>
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-gray-700 w-20">Patient Mobile</label>
                                <input v-model="patientForm.patientMobile" type="text"
                                    placeholder="Enter Patient Mobile"
                                    class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm bg-yellow-100 focus:bg-yellow-200 focus:outline-none">
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-gray-700 w-20">Gender</label>
                                <select v-model="patientForm.gender"
                                    class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-gray-700 w-20">Card type</label>
                                <select v-model="patientForm.cardType"
                                    class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                </select>
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-gray-700 w-20">Pay Mode</label>
                                <select v-model="patientForm.payMode"
                                    class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                </select>
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-gray-700 w-20">Card type</label>
                                <select v-model="patientForm.cardType2"
                                    class="flex-1 ml-2 px-2 py-1.5 border border-gray-300 rounded text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">Select</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Total Summary -->
                    <div>
                        <div class="bg-teal-600 text-white px-4 py-2 text-sm font-semibold rounded-t">
                            TOTAL SUMMARY
                        </div>
                        <div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white space-y-2">
                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Total:</label>
                                <input v-model="summary.total" type="number" step="0.01"
                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-yellow-100 text-right font-semibold"
                                    readonly>
                            </div>

                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Corp. Due Amt.</label>
                                <input v-model="summary.corpDueAmt" type="number" step="0.01"
                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right"
                                    readonly>
                            </div>

                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Pvt./PC Amt.</label>
                                <input v-model="summary.pvtPcAmt" type="number" step="0.01"
                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-green-200 text-right font-semibold"
                                    readonly>
                            </div>

                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Paid Amt.</label>
                                <input v-model="summary.paidAmt" type="number" step="0.01"
                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-yellow-100 text-right font-semibold">
                            </div>

                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Change Amt.</label>
                                <input v-model="summary.changeAmt" type="number" step="0.01"
                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-red-100 text-right"
                                    readonly>
                            </div>

                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Receiving Amt.</label>
                                <input v-model="summary.receivingAmt" type="number" step="0.01"
                                    class="w-24 px-2 py-1 border border-gray-300 rounded text-xs bg-red-100 text-right"
                                    readonly>
                            </div>

                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Delivery Date</label>
                                <div class="flex">
                                    <input v-model="summary.deliveryDate" type="text"
                                        class="w-32 px-2 py-1 border border-gray-300 rounded-l text-xs bg-yellow-100">
                                    <button
                                        class="px-2 py-1 bg-gray-200 border border-l-0 border-gray-300 rounded-r text-xs hover:bg-gray-300">
                                        📅
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Remarks</label>
                                <textarea v-model="summary.remarks" placeholder="Enter remarks (If any)"
                                    class="w-full px-2 py-1 border border-gray-300 rounded text-xs h-12 resize-none focus:border-blue-500 focus:outline-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Commission for PC -->
                    <div>
                        <div class="bg-teal-600 text-white px-4 py-2 text-sm font-semibold rounded-t">
                            COMMISSION FOR PC
                        </div>
                        <div class="border border-gray-300 border-t-0 rounded-b p-3 bg-white space-y-3">
                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Total</label>
                                <input v-model="commission.total" type="number" step="0.01"
                                    class="w-20 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right"
                                    readonly>
                            </div>

                            <div class="flex justify-between items-center">
                                <label class="text-xs font-medium text-gray-700">Phy./Syst. Amt</label>
                                <input v-model="commission.physystAmt" type="number" step="0.01"
                                    class="w-20 px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-right"
                                    readonly>
                            </div>

                            <div class="mt-4">
                                <input type="range" v-model="commission.slider" min="0" max="100"
                                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-300">
                    <button @click="closeModal"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md text-sm font-medium">
                        Cancel
                    </button>
                    <button @click="saveBill"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                        Save Bill
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom Slider Styling */
.slider::-webkit-slider-thumb {
    appearance: none;
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #10b981;
    cursor: pointer;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.slider::-moz-range-thumb {
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #10b981;
    cursor: pointer;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Input focus improvements */
input:focus,
select:focus,
textarea:focus {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

/* Table styling improvements */
table {
    border-collapse: separate;
    border-spacing: 0;
}

th,
td {
    border-bottom: 1px solid #e5e7eb;
}

th:first-child,
td:first-child {
    border-left: 1px solid #e5e7eb;
}

th:last-child,
td:last-child {
    border-right: 1px solid #e5e7eb;
}

/* Professional color scheme matching the image */
.bg-yellow-100 {
    background-color: #fef3c7 !important;
}

.bg-green-200 {
    background-color: #bbf7d0 !important;
}

.bg-red-100 {
    background-color: #fee2e2 !important;
}

.bg-gray-100 {
    background-color: #f3f4f6 !important;
}

/* Hover effects */
button:hover {
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

select:hover,
input:hover {
    border-color: #6b7280;
}
</style>