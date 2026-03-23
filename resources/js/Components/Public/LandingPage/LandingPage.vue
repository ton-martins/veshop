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
    --renol-primary: #073341;
    --renol-success: #81d86f;
    --renol-dark: #141822;
    --renol-light: #f5fdff;
    --renol-muted: #8797a7;
    --renol-surface: #f3f6f9;
    font-family: inherit;
    width: 100%;
    max-width: 100vw;
    overflow-x: hidden;
    overflow-x: clip;
    background: #ffffff;
}

.landing-page-root :deep(.landing-section-shell) {
    --next-section-bg: transparent;
    --corner-cut-size: 0;
    position: relative;
    overflow: visible;
    margin-bottom: 0;
    box-shadow: none;
}

.landing-page-root :deep(.landing-section-shell::before),
.landing-page-root :deep(.landing-section-shell::after) {
    display: none !important;
}

.landing-page-root :deep(.landing-section-shell > .container),
.landing-page-root :deep(.landing-section-shell > .container-fluid) {
    position: relative;
    z-index: 1;
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
    color: var(--renol-dark);
    letter-spacing: 1px;
    font-weight: 600;
    font-size: 17px;
}

.landing-page-root :deep(main section .section-title-border) {
    background: var(--renol-success);
    width: 50px;
    height: 2px;
}

.landing-page-root :deep(h1),
.landing-page-root :deep(h2),
.landing-page-root :deep(h3),
.landing-page-root :deep(h4),
.landing-page-root :deep(h5) {
    font-weight: 700 !important;
}

.landing-page-root :deep(.btn) {
    border: none !important;
    border-radius: 3px !important;
    padding: 10px 28px !important;
    font-size: 15px !important;
    font-weight: 400 !important;
    transition: all 0.5s !important;
}

.landing-page-root :deep(.btn:hover) {
    transform: translateY(-3px);
}

.landing-page-root :deep(.btn:focus),
.landing-page-root :deep(.btn:focus-visible) {
    border-color: transparent !important;
    box-shadow: 0 0 0 0.15rem rgba(7, 51, 65, 0.5) !important;
}

.landing-page-root :deep(.veshop-landing-btn) {
    border-radius: 3px !important;
    line-height: 1.35;
}

.landing-page-root :deep(.btn-primary),
.landing-page-root :deep(.hero-btn-primary),
.landing-page-root :deep(.main-btn .landing-cta-solid),
.landing-page-root :deep(.about-btn .landing-cta-solid),
.landing-page-root :deep(.cta-btn-contact) {
    background-color: var(--renol-primary) !important;
    border-color: var(--renol-primary) !important;
    color: #ffffff !important;
}

.landing-page-root :deep(.btn-success),
.landing-page-root :deep(.plan-card .btn-success),
.landing-page-root :deep(.cta-btn-request) {
    background-color: var(--renol-success) !important;
    border-color: var(--renol-success) !important;
    color: #ffffff !important;
}

.landing-page-root :deep(.btn-success:focus),
.landing-page-root :deep(.plan-card .btn-success:focus) {
    box-shadow: 0 0 0 0.15rem rgba(129, 216, 111, 0.5) !important;
}

.landing-page-root :deep(main section p),
.landing-page-root :deep(main section .text-muted) {
    color: var(--renol-muted) !important;
    font-size: 17px;
}

.landing-page-root :deep(main section h1),
.landing-page-root :deep(main section h2),
.landing-page-root :deep(main section h3),
.landing-page-root :deep(main section h4),
.landing-page-root :deep(main section h5),
.landing-page-root :deep(main section h6) {
    color: var(--renol-dark) !important;
}

.landing-page-root :deep(.hero-title),
.landing-page-root :deep(.ecosystem-title),
.landing-page-root :deep(.about-title h2),
.landing-page-root :deep(.service-title h2),
.landing-page-root :deep(.why-choose-title h2),
.landing-page-root :deep(.price-title h2),
.landing-page-root :deep(.proof-title),
.landing-page-root :deep(.plan-tab-card .text-dark) {
    color: var(--renol-dark) !important;
}

.landing-page-root :deep(.hero-title) {
    font-weight: 800 !important;
    color: #0a4152 !important;
}

.landing-page-root :deep(.hero-title .hero-title-highlight) {
    color: #63bb6f !important;
}

.landing-page-root :deep(.hero-title .hero-title-line) {
    color: #0a4152 !important;
}

.landing-page-root :deep(.hero-subtitle),
.landing-page-root :deep(.ecosystem-text),
.landing-page-root :deep(.about-title p),
.landing-page-root :deep(.why-choose-title p),
.landing-page-root :deep(.proof-card .quote) {
    color: var(--renol-muted) !important;
}

.landing-page-root :deep(.landing-hero) {
    --next-section-bg: #ffffff !important;
    background:
        radial-gradient(65% 60% at 10% 6%, rgba(129, 216, 111, 0.15) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(45% 42% at 88% 20%, rgba(7, 51, 65, 0.08) 0%, rgba(7, 51, 65, 0) 72%),
        linear-gradient(180deg, #f5fdff 0%, #f3f6f9 100%) !important;
}

.landing-page-root :deep(.ecosystem-band),
.landing-page-root :deep(.services-section),
.landing-page-root :deep(.work-section),
.landing-page-root :deep(.price-section) {
    --next-section-bg: #ffffff !important;
    background: #ffffff !important;
}

.landing-page-root :deep(.about-section),
.landing-page-root :deep(.why-choose-section),
.landing-page-root :deep(.proof-band) {
    --next-section-bg: #f5fdff !important;
    background: #f5fdff !important;
}

.landing-page-root :deep(.ecosystem-band-glow),
.landing-page-root :deep(.services-glow),
.landing-page-root :deep(.price-glow),
.landing-page-root :deep(.proof-glow) {
    display: none !important;
}

.landing-page-root :deep(.hero-pill),
.landing-page-root :deep(.head-status),
.landing-page-root :deep(.item-badge.positive),
.landing-page-root :deep(.solution-icon i),
.landing-page-root :deep(.ecosystem-icon i),
.landing-page-root :deep(.plan-tab-icon) {
    background: rgba(129, 216, 111, 0.2) !important;
    border-color: rgba(129, 216, 111, 0.38) !important;
    color: var(--renol-primary) !important;
}

.landing-page-root :deep(.hero-btn-secondary.veshop-landing-btn) {
    border: 1px solid rgba(7, 51, 65, 0.28) !important;
    background: transparent !important;
    color: var(--renol-primary) !important;
}

.landing-page-root :deep(.hero-btn-secondary.veshop-landing-btn:hover),
.landing-page-root :deep(.hero-btn-secondary.veshop-landing-btn:focus-visible) {
    border-color: rgba(7, 51, 65, 0.55) !important;
    background: rgba(7, 51, 65, 0.05) !important;
    color: #073341 !important;
}

.landing-page-root :deep(.hero-highlight-card),
.landing-page-root :deep(.ecosystem-card),
.landing-page-root :deep(.solution-card),
.landing-page-root :deep(.proof-metric),
.landing-page-root :deep(.proof-card),
.landing-page-root :deep(.plan-tab-card),
.landing-page-root :deep(.plan-section-shell) {
    background: #ffffff !important;
    border: none !important;
    border-radius: 0.5rem !important;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.landing-page-root :deep(.hero-highlight-card) {
    background: transparent !important;
    box-shadow: none !important;
    border-color: rgba(7, 51, 65, 0.18) !important;
}

.landing-page-root :deep(.hero-highlight-icon) {
    background: rgba(129, 216, 111, 0.2) !important;
    color: var(--renol-primary) !important;
}

.landing-page-root :deep(.ecosystem-card:hover),
.landing-page-root :deep(.solution-card:hover),
.landing-page-root :deep(.proof-card:hover) {
    transform: none !important;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.landing-page-root :deep(.plan-tab-card:hover) {
    transform: translateY(-2px) !important;
    box-shadow: 0 0.6rem 1.1rem -0.85rem rgba(7, 51, 65, 0.38) !important;
}

.landing-page-root :deep(.plan-tab-card.is-active) {
    border: 1px solid rgba(7, 51, 65, 0.14) !important;
    box-shadow: 0 0.85rem 1.3rem -1rem rgba(7, 51, 65, 0.42) !important;
}

.landing-page-root :deep(.hero-floating-card) {
    background: linear-gradient(160deg, rgba(241, 253, 244, 0.94) 0%, rgba(229, 248, 235, 0.92) 56%, rgba(213, 240, 222, 0.9) 100%) !important;
    border: 1px solid rgba(129, 216, 111, 0.32) !important;
    box-shadow: 0 20px 34px -28px rgba(7, 51, 65, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.72) !important;
    backdrop-filter: blur(18px) saturate(140%);
    -webkit-backdrop-filter: blur(18px) saturate(140%);
}

.landing-page-root :deep(.floating-tabs) {
    background: rgba(255, 255, 255, 0.72) !important;
    border-color: rgba(129, 216, 111, 0.28) !important;
}

.landing-page-root :deep(.floating-feed-item) {
    background: transparent !important;
    border: 1px solid rgba(129, 216, 111, 0.28) !important;
    box-shadow: none !important;
}

.landing-page-root :deep(.head-title),
.landing-page-root :deep(.head-subtitle),
.landing-page-root :deep(.item-title),
.landing-page-root :deep(.item-meta),
.landing-page-root :deep(.floating-tab-btn) {
    color: #134331 !important;
}

.landing-page-root :deep(.hero-highlight-title) {
    color: var(--renol-dark) !important;
}

.landing-page-root :deep(.head-title) {
    font-weight: 700 !important;
}

.landing-page-root :deep(.head-subtitle),
.landing-page-root :deep(.item-meta) {
    font-weight: 500 !important;
    color: rgba(19, 67, 49, 0.74) !important;
}

.landing-page-root :deep(.floating-tab-btn),
.landing-page-root :deep(.item-title) {
    font-weight: 600 !important;
}

.landing-page-root :deep(.floating-tab-btn) {
    color: rgba(19, 67, 49, 0.84) !important;
}

.landing-page-root :deep(.hero-highlight-text) {
    color: var(--renol-muted) !important;
}

.landing-page-root :deep(.plan-card .small.text-muted),
.landing-page-root :deep(.plan-feature-description),
.landing-page-root :deep(.plan-tab-card .text-muted),
.landing-page-root :deep(.plan-section-shell .text-muted),
.landing-page-root :deep(.services-copy-muted),
.landing-page-root :deep(.proof-card .role),
.landing-page-root :deep(.proof-metric .label) {
    color: var(--renol-muted) !important;
}

.landing-page-root :deep(.floating-tab-btn.is-active) {
    background: rgba(129, 216, 111, 0.24) !important;
    color: #0e3a2b !important;
    box-shadow: inset 0 0 0 1px rgba(129, 216, 111, 0.36);
}

.landing-page-root :deep(.item-dot) {
    background: var(--renol-success) !important;
}

.landing-page-root :deep(.item-badge) {
    font-weight: 700 !important;
    letter-spacing: 0.01em;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.landing-page-root :deep(.item-badge.positive) {
    color: #0f4a31 !important;
    border-color: rgba(129, 216, 111, 0.46) !important;
    background: rgba(129, 216, 111, 0.2) !important;
}

.landing-page-root :deep(.item-badge.warning) {
    color: #7a5a03 !important;
    border-color: rgba(228, 171, 0, 0.38) !important;
    background: rgba(228, 171, 0, 0.16) !important;
}

.landing-page-root :deep(.item-badge.neutral) {
    color: #1b4f3b !important;
    border-color: rgba(129, 216, 111, 0.28) !important;
    background: rgba(129, 216, 111, 0.14) !important;
}

.landing-page-root :deep(.plan-card .text-primary),
.landing-page-root :deep(.proof-metric .value) {
    color: var(--renol-primary) !important;
}

.landing-page-root :deep(.plan-card .text-success) {
    color: var(--renol-success) !important;
}

.landing-page-root :deep(.plan-card .badge.text-bg-light),
.landing-page-root :deep(.plan-section-shell .badge.text-bg-light) {
    background: rgba(7, 51, 65, 0.08) !important;
    color: var(--renol-primary) !important;
    border-color: rgba(7, 51, 65, 0.16) !important;
}

.landing-page-root :deep(.plan-section-shell h3),
.landing-page-root :deep(.plan-section-shell .text-primary) {
    color: var(--renol-primary) !important;
}

.landing-page-root :deep(.solution-card) {
    background: #ffffff !important;
    border: none !important;
    border-radius: 0.5rem !important;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.landing-page-root :deep(.solution-card h3),
.landing-page-root :deep(.solution-card .solution-icon),
.landing-page-root :deep(.solution-card .solution-icon i),
.landing-page-root :deep(.solution-card li i) {
    color: var(--renol-success) !important;
}

.landing-page-root :deep(.solution-card h3),
.landing-page-root :deep(.solution-card li) {
    color: var(--renol-dark) !important;
}

.landing-page-root :deep(.solution-card p) {
    color: var(--renol-muted) !important;
}

.landing-page-root :deep(.solution-card .solution-icon i) {
    background: rgba(129, 216, 111, 0.16) !important;
    border: 1px solid rgba(129, 216, 111, 0.34) !important;
}

.landing-page-root :deep(.advantage-item) {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
}

.landing-page-root :deep(.advantage-item h6),
.landing-page-root :deep(.advantage-item i) {
    color: var(--renol-dark) !important;
}

.landing-page-root :deep(.advantage-item i) {
    color: var(--renol-success) !important;
}

.landing-page-root :deep(.photo-shell-frame) {
    background: transparent !important;
    border-color: rgba(7, 51, 65, 0.18) !important;
    box-shadow: none !important;
}

.landing-page-root :deep(.icon-text) {
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    padding: 0.35rem 0 !important;
}

.landing-page-root :deep(.plan-card) {
    width: 98%;
    margin: 0 auto;
    border: 1px solid rgba(7, 51, 65, 0.1) !important;
    border-top: 4px solid var(--renol-primary) !important;
    border-radius: 20px !important;
    background: #ffffff !important;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.landing-page-root :deep(.plan-card.border-success) {
    border-top-color: var(--renol-success) !important;
}

.landing-page-root :deep(.plan-card::before),
.landing-page-root :deep(.plan-card::after) {
    display: none !important;
}

.landing-page-root :deep(.plan-card .card-body) {
    padding: 1.28rem !important;
}

.landing-page-root :deep(.plan-card .card-body > p.mt-2.text-muted.mb-1) {
    display: inline-block !important;
    margin-top: 0.5rem !important;
    margin-bottom: 0.3rem !important;
    padding: 0.18rem 0.58rem !important;
    border: 1px solid rgba(7, 51, 65, 0.11) !important;
    border-radius: 7px !important;
    background: #ffffff !important;
    color: #5f7388 !important;
    font-size: 12px !important;
    line-height: 1.3 !important;
    font-weight: 600 !important;
}

.landing-page-root :deep(.plan-card .card-footer) {
    padding: 0 1.28rem 1.28rem !important;
}

.landing-page-root :deep(.plan-card h5),
.landing-page-root :deep(.plan-feature-title) {
    color: var(--renol-primary) !important;
}

.landing-page-root :deep(.plan-card .plan-price) {
    color: var(--renol-primary) !important;
}

.landing-page-root :deep(.plan-card .text-success.plan-price) {
    color: var(--renol-success) !important;
}

.landing-page-root :deep(.cta-premium) {
    --next-section-bg: #073341 !important;
    background:
        radial-gradient(58% 58% at 90% 10%, rgba(129, 216, 111, 0.22) 0%, rgba(129, 216, 111, 0) 72%),
        linear-gradient(180deg, #073341 0%, #032833 100%) !important;
}

.landing-page-root :deep(.cta-box) {
    background: rgba(245, 253, 255, 0.08) !important;
    border-color: rgba(245, 253, 255, 0.2) !important;
}

.landing-page-root :deep(.cta-eyebrow) {
    color: #bff6cc !important;
    text-shadow: 0 0 18px rgba(129, 216, 111, 0.34);
}

.landing-page-root :deep(.cta-title),
.landing-page-root :deep(.cta-subtitle) {
    color: var(--renol-light) !important;
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
