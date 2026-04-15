<script setup>
import { router } from '@inertiajs/vue3'
import { statusChangeConfirmation, deleteConfirmation } from '@/responseMessage.js';

const props = defineProps({
    isProcessing: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['action']);
let lastClickToken = '';
let lastClickAt = 0;
const CLICK_DEDUP_MS = 300;
let isNavigating = false;
const NAV_LOCK_MS = 1000;

const extractLabelText = (label) =>
    String(label ?? '').replace(/<[^>]*>/g, '').trim();

const inferActionName = (linkInfo) => {
    const label = extractLabelText(
        linkInfo?.linkLabel ?? linkInfo?.label ?? linkInfo?.link_label
    );

    if (!label) return null;
    if (label.includes('Partial Paid')) return 'commission-pay-partial';
    if (label.includes('Paid')) return 'commission-pay-full';

    return null;
};

const getActionName = (linkInfo) =>
    linkInfo?.actionName ?? linkInfo?.action_name ?? inferActionName(linkInfo);

const getActionId = (linkInfo) =>
    linkInfo?.actionId ?? linkInfo?.action_id ?? null;

const handleActionClick = (actionName, actionId) => {
    console.log('BaseTable action click', actionName, actionId);
    emit('action', actionName, actionId);
};

const hasLinkClass = (linkInfo, className) =>
    String(linkInfo?.linkClass ?? '')
        .split(/\s+/)
        .includes(className);

const isDeleteLink = (linkInfo) => {
    const label = extractLabelText(
        linkInfo?.linkLabel ?? linkInfo?.label ?? linkInfo?.link_label
    ).toLowerCase();

    return hasLinkClass(linkInfo, 'deleteButton') || label.includes('delete');
};

const isStatusChangeLink = (linkInfo) =>
    hasLinkClass(linkInfo, 'statusChange') ||
    String(linkInfo?.link ?? '').includes('/status/');

const handleLinkClick = (linkInfo, event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const actionName = getActionName(linkInfo) ?? inferActionName(linkInfo);
    const targetToken = actionName
        ? `action:${actionName}:${getActionId(linkInfo) ?? ''}`
        : `link:${linkInfo?.link ?? ''}`;
    const now = Date.now();

    if (targetToken === lastClickToken && now - lastClickAt < CLICK_DEDUP_MS) {
        return;
    }

    lastClickToken = targetToken;
    lastClickAt = now;

    if (actionName) {
        handleActionClick(actionName, getActionId(linkInfo));
        return;
    }

    if (isDeleteLink(linkInfo)) {
        if (!linkInfo?.link) {
            return;
        }

        deleteConfirmation(linkInfo.link);
        return;
    }

    if (isStatusChangeLink(linkInfo)) {
        statusChangeConfirmation(linkInfo?.link);
        return;
    }
    if (isNavigating) {
        return;
    }
    isNavigating = true;
    visitLink(linkInfo?.link, linkInfo?.target);
    setTimeout(() => {
        isNavigating = false;
    }, NAV_LOCK_MS);
};

const visitLink = (link, target) => {
    if (!link) return;
    if (target === '_blank') {
        window.open(link, '_blank', 'noopener');
        return;
    }
    router.visit(link);
};
</script>

<template>
<table class="w-full text-gray-700 border-collapse">
<thead class="text-gray-700 bg-gray-100">
<tr class="text-[12px]">
<template v-for="(header, index) in $page.props.tableHeaders">
<th class="px-6 py-3 border border-gray-300" :class="$page.props.dataFields?.[index]?.class">
{{ header }}
</th>
</template>
</tr>
</thead>

<tbody class="text-[12px] 2xl:text-[14px]">
<template v-for="data in $page.props.datas.data">
<tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">

<!-- DATA FIELDS -->
<template v-for="dateField in $page.props.dataFields">
<td class="px-4 py-2 border border-gray-200" :class="dateField.class">
<p v-html="data[dateField.fieldName] ?? ''"></p>
</td>
</template>

<!-- ACTION BUTTONS -->
<td v-if="data.links" class="px-4 py-2 border border-gray-200">
<div class="flex justify-center w-full space-x-1">

<template v-for="linkInfo in data.links">

<button
type="button"
@click.stop.prevent="handleLinkClick(linkInfo, $event)"
class="px-3 py-1 rounded transition cursor-pointer hover:opacity-90"
:class="linkInfo.linkClass"
:style="linkInfo.linkStyle ?? linkInfo.style ?? null"
:disabled="props.isProcessing && !!getActionName(linkInfo)"
:data-action="getActionName(linkInfo)"
:data-action-id="getActionId(linkInfo)"
>
<span v-html="linkInfo.linkLabel"></span>
</button>

</template>

</div>
</td>

</tr>
</template>
</tbody>
</table>
</template>