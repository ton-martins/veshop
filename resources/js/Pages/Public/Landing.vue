<script setup>
import LandingPage from '@/Components/Public/LandingPage/LandingPage.vue';
import { useBranding } from '@/branding';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    planSections: {
        type: Array,
        default: () => [],
    },
});

const { brandName, tagline } = useBranding();
const pageTitle = computed(() => `${brandName.value} | ${tagline.value}`);
</script>

<template>
    <Head :title="pageTitle" />
    <LandingPage :can-login="canLogin" :plan-sections="planSections" />
</template>

