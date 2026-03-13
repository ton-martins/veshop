<script setup>
import { Head } from '@inertiajs/vue3';
import LandingCta from '@/Components/Public/LandingPage/LandingCta.vue';
import LandingFooter from '@/Components/Public/LandingPage/LandingFooter.vue';
import LandingHeader from '@/Components/Public/LandingPage/LandingHeader.vue';
import LandingHero from '@/Components/Public/LandingPage/LandingHero.vue';
import LandingInteractiveStudio from '@/Components/Public/LandingPage/LandingInteractiveStudio.vue';
import LandingModules from '@/Components/Public/LandingPage/LandingModules.vue';
import LandingNiches from '@/Components/Public/LandingPage/LandingNiches.vue';
import LandingStats from '@/Components/Public/LandingPage/LandingStats.vue';
import LandingWorkflow from '@/Components/Public/LandingPage/LandingWorkflow.vue';
import BRANDING, { BRAND_CSS_VARS } from '@/branding';

defineProps({
    canLogin: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Head :title="`${BRANDING.appName} | Gestão para varejo`" />

    <div class="relative min-h-screen overflow-x-hidden" :style="BRAND_CSS_VARS">
        <div class="veshop-shell absolute inset-0 -z-20"></div>
        <div class="veshop-grid absolute inset-0 -z-10 opacity-80"></div>

        <LandingHeader :can-login="canLogin" />

        <main class="pb-10">
            <LandingHero />
            <LandingStats />
            <LandingInteractiveStudio />
            <LandingModules />
            <LandingNiches />
            <LandingWorkflow />
            <LandingCta />
        </main>

        <LandingFooter />
    </div>
</template>
