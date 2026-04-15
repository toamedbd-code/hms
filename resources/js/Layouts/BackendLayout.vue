<script setup>
import { Head } from '@inertiajs/vue3';
import Sidebar from '@/Layouts/Partials/Sidebar.vue';
import Navbar from '@/Layouts/Partials/Navbar.vue';
import Breadcrumb from '@/Layouts/Partials/Breadcrumb.vue'; 


</script>

<template>
    <div>

        <Head :title="$page.props.pageTitle" />


        <div class="w-full app-shell-surface dark:bg-slate-950">
            <Navbar />

            <div class="flex w-full">
                <Sidebar />

                <div class="w-full h-[calc(100vh-48px)] mt-12 overflow-y-auto p-2 app-content-surface">
                    <Breadcrumb :breadcrumbs="$page.props.breadcrumbs" />
                    <main>
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

.app-content-surface {
    background: color-mix(in srgb, var(--app-theme-surface) 86%, #f8fafc);
}


body {
    background-color: color-mix(in srgb, var(--app-theme-surface) 88%, #ffffff) !important;
}
</style>