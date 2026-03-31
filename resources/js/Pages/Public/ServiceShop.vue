<script setup>
/* eslint-disable vue/prop-name-casing */
import StorefrontTemplateIndex6 from '@/Components/Public/Storefront/StorefrontTemplateIndex6.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    storefront: { type: Object, default: () => ({}) },
    store_availability: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    collaborators: { type: Array, default: () => [] },
    shop_auth: {
        type: Object,
        default: () => ({ authenticated: false, customer: null, email_verified: false, requires_email_verification: false }),
    },
    shop_account: { type: Object, default: () => ({ orders: [] }) },
    bookings: { type: Array, default: () => [] },
});

const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
</script>

<template>
    <Head :title="storeName" />

    <StorefrontTemplateIndex6
        mode="services"
        :contractor="contractor"
        :categories="categories"
        :services="services"
        :storefront="storefront"
        :store_availability="store_availability"
        :shop_auth="shop_auth"
        :shop_account="shop_account"
        :bookings="bookings"
        :collaborators="collaborators"
    />
</template>
