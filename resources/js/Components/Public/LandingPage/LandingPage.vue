<script setup>
import LandingAbout from '@/Components/Public/LandingPage/LandingAbout.vue';
import LandingCta from '@/Components/Public/LandingPage/LandingCta.vue';
import LandingEcosystem from '@/Components/Public/LandingPage/LandingEcosystem.vue';
import LandingFooter from '@/Components/Public/LandingPage/LandingFooter.vue';
import LandingHeader from '@/Components/Public/LandingPage/LandingHeader.vue';
import LandingHome from '@/Components/Public/LandingPage/LandingHome.vue';
import LandingPricing from '@/Components/Public/LandingPage/LandingPricing.vue';
import LandingServices from '@/Components/Public/LandingPage/LandingServices.vue';
import LandingTestimonials from '@/Components/Public/LandingPage/LandingTestimonials.vue';
import LandingWhyChoose from '@/Components/Public/LandingPage/LandingWhyChoose.vue';
import LandingWork from '@/Components/Public/LandingPage/LandingWork.vue';
import { useBranding } from '@/branding';
import { Head } from '@inertiajs/vue3';

const { publicFaviconHref, publicFaviconType } = useBranding();

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
            <LandingEcosystem />
            <LandingAbout />
            <LandingServices />
            <LandingWhyChoose />
            <LandingWork />
            <LandingPricing :plan-sections="planSections" />
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
    background:
        radial-gradient(60% 40% at 85% 0%, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(55% 45% at 8% 28%, rgba(52, 182, 130, 0.16) 0%, rgba(52, 182, 130, 0) 72%),
        linear-gradient(180deg, #04161b 0%, #08242c 35%, #0a2f37 70%, #0d3942 100%);
}

.landing-page-root :deep(.landing-section-shell) {
    --next-section-bg: transparent;
    --corner-cut-size: clamp(56px, 7vw, 110px);
    position: relative;
    overflow: hidden;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    margin-bottom: 0;
    box-shadow: 0 28px 42px -42px rgba(2, 16, 12, 0.95);
}

.landing-page-root :deep(.landing-section-shell::before),
.landing-page-root :deep(.landing-section-shell::after) {
    content: '';
    position: absolute;
    bottom: 0;
    width: var(--corner-cut-size);
    height: var(--corner-cut-size);
    background: var(--next-section-bg);
    pointer-events: none;
    z-index: 2;
}

.landing-page-root :deep(.landing-section-shell::before) {
    left: 0;
    border-top-right-radius: var(--corner-cut-size);
}

.landing-page-root :deep(.landing-section-shell::after) {
    right: 0;
    border-top-left-radius: var(--corner-cut-size);
}

.landing-page-root :deep(.landing-section-light) {
    box-shadow: 0 28px 44px -44px rgba(4, 19, 15, 0.72);
}

.landing-page-root :deep(.landing-section-shell > .container),
.landing-page-root :deep(.landing-section-shell > .container-fluid) {
    position: relative;
    z-index: 1;
}

.landing-page-root :deep(.landing-section-light .text-primary),
.landing-page-root :deep(.landing-section-light h2),
.landing-page-root :deep(.landing-section-light h3),
.landing-page-root :deep(.landing-section-light h4) {
    color: #0f2b22 !important;
}

.landing-page-root :deep(.landing-section-light .title-sm span) {
    color: #2f8b45 !important;
}

.landing-page-root :deep(.landing-section-light .section-title-border) {
    background: #5ac178 !important;
}

.landing-page-root :deep(.landing-section-light p),
.landing-page-root :deep(.landing-section-light .text-muted) {
    color: rgba(28, 58, 47, 0.8) !important;
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

.landing-page-root :deep(main section .title-sm span) {
    color: #bff6cc;
}

.landing-page-root :deep(main section .section-title-border) {
    background: #9ce1a8;
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
