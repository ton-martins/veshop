<script setup>
import LandingAbout from '@/Components/Public/LandingPage/LandingAbout.vue';
import LandingCta from '@/Components/Public/LandingPage/LandingCta.vue';
import LandingFaq from '@/Components/Public/LandingPage/LandingFaq.vue';
import LandingFooter from '@/Components/Public/LandingPage/LandingFooter.vue';
import LandingHome from '@/Components/Public/LandingPage/LandingHome.vue';
import LandingHeader from '@/Components/Public/LandingPage/LandingHeader.vue';
import LandingPricing from '@/Components/Public/LandingPage/LandingPricing.vue';
import LandingServices from '@/Components/Public/LandingPage/LandingServices.vue';
import LandingTestimonials from '@/Components/Public/LandingPage/LandingTestimonials.vue';
import LandingWhyChoose from '@/Components/Public/LandingPage/LandingWhyChoose.vue';
import LandingWork from '@/Components/Public/LandingPage/LandingWork.vue';
import { Head } from '@inertiajs/vue3';
import { useBranding } from '@/branding';

const {
    publicFaviconHref,
    publicFaviconType,
} = useBranding();

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
        <link head-key="landing-favicon" rel="icon" :type="publicFaviconType" :href="publicFaviconHref" />
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
            <LandingHome />
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
    width: 100%;
    max-width: 100vw;
    overflow-x: hidden;
    overflow-x: clip;
}

.landing-page-root :deep(main),
.landing-page-root :deep(section),
.landing-page-root :deep(.container),
.landing-page-root :deep(.row > *) {
    min-width: 0;
}

.landing-page-root :deep(img),
.landing-page-root :deep(svg),
.landing-page-root :deep(video),
.landing-page-root :deep(canvas) {
    max-width: 100%;
    height: auto;
}

@media (max-width: 575px) {
    .landing-page-root :deep(.container) {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}

@media (max-width: 360px) {
    .landing-page-root :deep(.container) {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}
</style>
