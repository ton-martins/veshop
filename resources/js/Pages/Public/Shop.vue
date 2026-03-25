<script setup>
/* eslint-disable vue/prop-name-casing */
import NativeStorefrontApp from '@/Components/Public/Storefront/NativeStorefrontApp.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    contractor: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    storefront: { type: Object, default: () => ({}) },
    store_availability: { type: Object, default: () => ({}) },
    payment_methods: { type: Array, default: () => [] },
    shipping_config: { type: Object, default: () => ({}) },
    shop_auth: { type: Object, default: () => ({ authenticated: false, customer: null }) },
    shop_account: { type: Object, default: () => ({ orders: [] }) },
});

const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
</script>

<template>
    <Head :title="storeName" />

    <NativeStorefrontApp
        mode="commerce"
        :contractor="contractor"
        :categories="categories"
        :products="products"
        :storefront="storefront"
        :store_availability="store_availability"
        :payment_methods="payment_methods"
        :shipping_config="shipping_config"
        :shop_auth="shop_auth"
        :shop_account="shop_account"
    />
</template>



