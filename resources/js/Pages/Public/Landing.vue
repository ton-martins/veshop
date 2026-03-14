<script setup>
import LandingPage from '@/Components/Public/LandingPage/LandingPage.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <Head title="Veshop | ERP para comércio e varejo" />
    <LandingPage :can-login="canLogin" />
</template>