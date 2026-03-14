<script setup>
import LandingAbout from '@/Components/Public/LandingPage/LandingAbout.vue';
import LandingCta from '@/Components/Public/LandingPage/LandingCta.vue';
import LandingFaq from '@/Components/Public/LandingPage/LandingFaq.vue';
import LandingFooter from '@/Components/Public/LandingPage/LandingFooter.vue';
import LandingHeader from '@/Components/Public/LandingPage/LandingHeader.vue';
import LandingHero from '@/Components/Public/LandingPage/LandingHero.vue';
import LandingPricing from '@/Components/Public/LandingPage/LandingPricing.vue';
import LandingServices from '@/Components/Public/LandingPage/LandingServices.vue';
import LandingTestimonials from '@/Components/Public/LandingPage/LandingTestimonials.vue';
import LandingWhyChoose from '@/Components/Public/LandingPage/LandingWhyChoose.vue';
import LandingWork from '@/Components/Public/LandingPage/LandingWork.vue';
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
    <Head>
        <link head-key="landing-favicon" rel="icon" type="image/x-icon" href="/brand/favicon-veshop.ico" />
        <link head-key="landing-favicon-32" rel="icon" type="image/png" sizes="32x32" href="/brand/favicon-veshop-32.png" />
        <link head-key="landing-favicon-16" rel="icon" type="image/png" sizes="16x16" href="/brand/favicon-veshop-16.png" />
        <link head-key="landing-remixicon-preload" rel="preload" as="style" href="/landing/css/remixicon.css" />
        <link head-key="landing-bootstrap-preload" rel="preload" as="style" href="/landing/css/bootstrap.min.css" />
        <link head-key="landing-style-preload" rel="preload" as="style" href="/landing/css/style.min.css" />
        <link head-key="landing-remixicon" rel="stylesheet" href="/landing/css/remixicon.css" />
        <link head-key="landing-bootstrap" rel="stylesheet" href="/landing/css/bootstrap.min.css" />
        <link head-key="landing-style" rel="stylesheet" href="/landing/css/style.min.css" />
    </Head>

    <div class="landing-page-root">
        <LandingHeader :can-login="canLogin" />
        <main>
            <LandingHero />
            <LandingAbout />
            <LandingServices />
            <LandingWhyChoose />
            <LandingWork />
            <LandingPricing :plan-sections="planSections" />
            <LandingFaq />
            <LandingTestimonials />
            <LandingCta />
        </main>
        <LandingFooter />
    </div>
</template>

<style scoped>
.landing-page-root {
    overflow-x: hidden;
}
</style>
