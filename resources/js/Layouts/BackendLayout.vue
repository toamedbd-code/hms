<script setup>
import { Head } from '@inertiajs/vue3';
import Sidebar from '@/Layouts/Partials/Sidebar.vue';
import Navbar from '@/Layouts/Partials/Navbar.vue';
import Breadcrumb from '@/Layouts/Partials/Breadcrumb.vue'; 


</script>

<template>
    <div>

        <Head :title="$page.props.pageTitle || $page.props.webSetting?.company_short_name || $page.props.webSetting?.company_name || 'HMS'" />


        <div class="app-shell app-shell-surface dark:bg-slate-950">
            <Sidebar />

            <div class="app-main-column">
                <Navbar />

                <div class="app-content-surface min-h-0 min-w-0 flex-1 overflow-auto p-2 sm:p-4">
                    <Breadcrumb :breadcrumbs="$page.props.breadcrumbs" />
                    <main class="min-w-0">
                        <slot />
                    </main>
                </div>
            </div>
        </div>

    </div>
</template>

<style>
/* width */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

/* Track */
::-webkit-scrollbar-track {
    background: color-mix(in srgb, var(--app-theme-soft) 26%, #e2e8f0);
    border-radius: 8px;
}

/* Handle */
::-webkit-scrollbar-thumb {
    background: color-mix(in srgb, var(--app-theme-primary) 40%, #94a3b8);
    border-radius: 8px;
}

::-webkit-scrollbar-thumb:hover {
    background: color-mix(in srgb, var(--app-theme-primary) 56%, #64748b);
}

.app-shell-surface {
    background:
        radial-gradient(circle at top right, color-mix(in srgb, var(--app-theme-soft) 55%, white) 0%, transparent 58%),
        linear-gradient(160deg, color-mix(in srgb, var(--app-theme-soft) 52%, #e2e8f0) 0%, color-mix(in srgb, var(--app-theme-surface) 78%, #f8fafc) 100%);
}

.app-shell {
    display: flex;
    position: relative;
    width: 100%;
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
}

.app-main-column {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
}

.app-content-surface {
    background: color-mix(in srgb, var(--app-theme-surface) 86%, #f8fafc);
    scrollbar-width: auto;
    -ms-overflow-style: auto;
}

html,
body {
    height: 100%;
    overflow: hidden;
}


body {
    background-color: color-mix(in srgb, var(--app-theme-surface) 88%, #ffffff) !important;
}
</style>