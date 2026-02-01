<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { useDark, useToggle } from '@vueuse/core';
import eventBus from '@/eventBus.js';

const isDark = useDark();
const toggleDark = useToggle(isDark);
const sideBarFlag = ref(false);
const isDropdownVisible = ref(false);

const toggleSidebar = () => {
    sideBarFlag.value = !sideBarFlag.value;
    eventBus.emit('sidebarToggled', sideBarFlag.value);
};

const logout = () => {
    window.open(route("backend.auth.logout"), "_self");
};

const toggleDropdown = () => {
    isDropdownVisible.value = !isDropdownVisible.value;
};
</script>

<template>
    <div class="relative w-full">
        <div :class="['absolute', 'w-full', { 'md:pl-[70px]': sideBarFlag, 'pl-[240px]': !sideBarFlag }]">
            <div class="flex px-4 items-center justify-between w-full border-b border-gray-200 bg-gray-100 py-3 h-[50px]">
                <div>
                    <button type="button" @click="toggleSidebar"
                        class="p-2 rounded text-gray-500 hover:text-blue-600 hover:bg-gray-100 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                        </svg>
                    </button>
                </div>
                <div>
                    <ul class="flex items-center space-x-2">
                        <li>
                            <button type="button" @click="toggleDark()"
                                class="p-2 rounded text-gray-500 hover:text-blue-600 hover:bg-gray-100 transition-colors duration-200"
                                :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
                                <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                                </svg>
                            </button>
                        </li>
                        
                        <li class="relative">
                            <a href="#"
                                class="p-2 rounded text-gray-500 hover:text-blue-600 hover:bg-gray-100 transition-colors duration-200 block relative">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </a>
                        </li>

                        <li class="relative">
                            <div class="cursor-pointer p-2 rounded text-gray-500 hover:text-blue-600 hover:bg-gray-100 transition-colors duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                </svg>
                            </div>
                        </li>

                        <li>
                            <div class="relative">
                                <div class="flex items-center text-gray-500 hover:text-blue-600 transition-colors duration-200 p-2 rounded cursor-pointer">
                                    <Dropdown align="right">
                                    <template #trigger>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </template>

                                    <template #content>
                                        <DropdownLink>
                                            Profile
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                                </div>
                            </div>
                        </li>

                        
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>