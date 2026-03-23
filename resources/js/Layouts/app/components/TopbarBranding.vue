<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    homeHref: { type: String, default: '/home' },
    systemIconUrl: { type: String, default: '' },
    systemBrandName: { type: String, default: 'Veshop' },
    systemContextLabel: { type: String, default: '' },
});
</script>

<template>
    <header
        class="flex items-center border-b border-white/60 bg-white/80 px-4 py-4 shadow-sm backdrop-blur md:hidden"
    >
        <Link :href="homeHref" class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 items-center justify-center rounded-md bg-slate-900 text-sm font-semibold text-white"
            >
                <img :src="systemIconUrl" :alt="systemBrandName" class="h-6 w-6 object-contain" />
            </div>
            <div class="flex flex-col leading-tight">
                <span class="text-[13px] font-semibold text-slate-900">{{ systemBrandName }}</span>
                <span
                    class="mt-1 inline-flex w-fit items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                >
                    {{ systemContextLabel }}
                </span>
            </div>
        </Link>
    </header>
</template>
