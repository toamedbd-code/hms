<script setup>
import { statusChangeConfirmation, deleteConfirmation } from '@/responseMessage.js';
import $ from 'jquery';

$(function () {
    $('.statusChange').on('click', function (e) {
        e.preventDefault();
        const url = $(this).attr('href');
        statusChangeConfirmation(url);
    });
    $('.deleteButton').on('click', function (e) {
        e.preventDefault();
        const url = $(this).attr('href');
        deleteConfirmation(url);
    });
});
</script>

<template>
    <table class="w-full text-gray-700 border-collapse">
        <thead class="text-gray-700 bg-gray-100">
            <tr class="text-[12px]">
                <template v-for="header in $page.props.tableHeaders">
                    <th scope="col" class="px-6 py-3 border border-gray-300">{{ header }}</th>
                </template>
            </tr>
        </thead>
        <tbody class="text-[12px] 2xl:text-[14px]">
            <template v-for="(data, dataIndex) in $page.props.datas.data">
                <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors duration-200">
                    <template v-for="(dateField, dateFieldIndex) in $page.props.dataFields">
                        <td class="px-4 py-2 border border-gray-200" :class="dateField.class">
                            <p v-html="data[dateField.fieldName] ?? ''"></p>
                        </td>
                    </template>
                    
                    <td v-if="data.links" class="px-4 py-2 border border-gray-200">
                        <div class="flex justify-center w-full space-x-1">
                            <template v-for="(linkInfo, linkIndex) in data.links">
                                <button v-if="linkInfo.actionName"
                                    @click="$emit('action', linkInfo.actionName, linkInfo.actionId)"
                                    class="px-3 py-1 rounded hover:bg-green-500 transition-colors duration-200" :class="linkInfo.linkClass">
                                    <span v-html="linkInfo.linkLabel"></span>
                                </button>
                                <a v-else class="px-3 py-1 rounded hover:bg-green-500 transition-colors duration-200"
                                    :href="linkInfo.link" 
                                    :class="linkInfo.linkClass"
                                    :target="linkInfo.target || '_self'">
                                    <span v-html="linkInfo.linkLabel"></span>
                                </a>
                            </template>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</template>