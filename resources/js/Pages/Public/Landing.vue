<script setup>
import LandingPage from '@/Components/Public/LandingPage/LandingPage.vue';
import { Head } from '@inertiajs/vue3';

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
</script>

<template>
    <Head title="Veshop | ERP para comércio e varejo" />
    <LandingPage :can-login="canLogin" :plan-sections="planSections" />
</template>
