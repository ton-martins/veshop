<script setup>
import { useBranding } from '@/branding';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    showAside: {
        type: Boolean,
        default: false,
    },
});

const highlights = [
    'Vendas, estoque e caixa sincronizados em tempo real.',
    'Rotinas fiscais e financeiras com trilha de auditoria.',
    'Experiência de app para operação diária do varejo.',
];

const currentYear = new Date().getFullYear();
const { brandName, tagline, systemIconUrl, publicFaviconHref, publicFaviconType } = useBranding();
</script>

<template>
    <Head>
        <meta charset="UTF-8" />
        <link v-if="publicFaviconHref" rel="icon" :href="publicFaviconHref" :type="publicFaviconType" />
        <link head-key="landing-remixicon-preload" rel="preload" as="style" href="/landing/css/remixicon.css" />
        <link head-key="landing-bootstrap-preload" rel="preload" as="style" href="/landing/css/bootstrap.min.css" />
        <link head-key="landing-style-preload" rel="preload" as="style" href="/landing/css/style.min.css" />
        <link head-key="landing-remixicon" rel="stylesheet" href="/landing/css/remixicon.css" />
        <link head-key="landing-bootstrap" rel="stylesheet" href="/landing/css/bootstrap.min.css" />
        <link head-key="landing-style" rel="stylesheet" href="/landing/css/style.min.css" />
    </Head>

    <section class="veshop-auth-shell">
        <div class="veshop-auth-bg" aria-hidden="true">
            <div class="veshop-auth-gradient-base"></div>
            <div class="veshop-auth-gradient-overlay"></div>
            <div class="veshop-auth-orb veshop-auth-orb-left"></div>
            <div class="veshop-auth-orb veshop-auth-orb-right"></div>
            <div class="veshop-auth-orb veshop-auth-orb-top"></div>
        </div>

        <div class="position-relative container veshop-auth-content">
            <div
                class="row veshop-auth-row min-vh-100 align-items-center align-items-lg-stretch justify-content-center g-4 py-4"
            >
                <div v-if="props.showAside" class="col-lg-6 d-none d-lg-flex">
                    <aside class="veshop-auth-aside veshop-auth-equal-height w-100 p-4 p-lg-5">
                        <p class="mb-2 text-uppercase ls-2 fw-semibold text-success">Ecossistema {{ brandName }}</p>
                        <h1 class="veshop-auth-title mb-3">
                            Plataforma de gestão para comércios e serviços.
                        </h1>
                        <p class="veshop-auth-copy mb-0">
                            Centralize pedidos, estoque, financeiro e fiscal em um único ambiente para decisões mais rápidas.
                        </p>

                        <div class="mt-4 d-grid gap-3">
                            <article v-for="highlight in highlights" :key="highlight" class="veshop-auth-chip">
                                <span class="dot" aria-hidden="true"></span>
                                <p>{{ highlight }}</p>
                            </article>
                        </div>

                        <p class="mb-0 mt-4 small text-light opacity-75">
                            &copy; {{ currentYear }} {{ brandName }}. Todos os direitos reservados.
                        </p>
                    </aside>
                </div>

                <div
                    class="d-lg-flex"
                    :class="props.showAside ? 'col-lg-5 col-md-8' : 'col-xl-5 col-lg-6 col-md-8'"
                >
                    <section class="veshop-auth-card veshop-auth-equal-height w-100 p-4 p-lg-5">
                        <slot name="brand">
                            <Link href="/" class="d-inline-flex align-items-center gap-3 text-decoration-none">
                                <span class="veshop-auth-logo">
                                    <img
                                        :src="systemIconUrl"
                                        :alt="`${brandName} icone`"
                                        class="veshop-auth-logo-img"
                                    />
                                </span>
                                <span>
                                    <span class="d-block fs-5 fw-bold text-primary ls-1">{{ brandName }}</span>
                                    <span class="veshop-auth-subbrand d-block text-uppercase ls-2">{{ tagline }}</span>
                                </span>
                            </Link>
                        </slot>

                        <div class="mt-4">
                            <slot />
                        </div>

                        <p class="mb-0 mt-4 text-center d-lg-none small text-muted">
                            <slot name="footer">
                                &copy; {{ currentYear }} {{ brandName }}. Todos os direitos reservados.
                            </slot>
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.veshop-auth-shell {
    position: relative;
    min-height: 100vh;
    padding-top: 0;
    overflow: hidden;
}

.veshop-auth-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}

.veshop-auth-gradient-base {
    position: absolute;
    inset: 0;
    z-index: 0;
    background:
        radial-gradient(circle at top, rgba(129, 216, 111, 0.24) 0%, transparent 60%),
        radial-gradient(120% 120% at 85% 10%, rgba(129, 216, 111, 0.2) 0%, transparent 65%),
        linear-gradient(180deg, #f8fef9 0%, #eef9f1 55%, #e7f5eb 100%);
}

.veshop-auth-gradient-overlay {
    position: absolute;
    inset: 0;
    z-index: 1;
    opacity: 0.72;
    background:
        radial-gradient(120% 120% at 90% 12%, rgba(129, 216, 111, 0.18), transparent),
        radial-gradient(90% 95% at 18% 78%, rgba(7, 51, 65, 0.07), transparent 70%);
}

.veshop-auth-orb {
    position: absolute;
    border-radius: 999px;
    filter: blur(58px);
}

.veshop-auth-orb-left {
    left: -120px;
    top: -54px;
    z-index: 2;
    width: 360px;
    height: 360px;
    background: rgba(129, 216, 111, 0.22);
}

.veshop-auth-orb-right {
    right: -120px;
    bottom: -130px;
    z-index: 2;
    width: 420px;
    height: 420px;
    background: rgba(129, 216, 111, 0.18);
}

.veshop-auth-orb-top {
    right: 14%;
    top: -120px;
    z-index: 2;
    width: 260px;
    height: 260px;
    background: rgba(7, 51, 65, 0.08);
}

.veshop-auth-content {
    z-index: 3;
}

.veshop-auth-row {
    padding-block: 1rem;
}

.veshop-auth-equal-height {
    min-height: 560px;
}

.veshop-auth-aside {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border: 1px solid rgba(129, 216, 111, 0.28);
    border-radius: 20px;
    background: linear-gradient(160deg, rgba(2, 29, 37, 0.9), rgba(7, 51, 65, 0.9));
    box-shadow: 0 30px 70px -36px rgba(7, 51, 65, 0.9);
}

.veshop-auth-title {
    color: #f5fdff;
    font-size: clamp(1.65rem, 2.5vw, 2.1rem);
    line-height: 1.24;
    font-weight: 700 !important;
}

.veshop-auth-copy {
    color: rgba(245, 253, 255, 0.8);
    font-size: 0.92rem;
    line-height: 1.58;
}

.veshop-auth-chip {
    border: 1px solid rgba(129, 216, 111, 0.28);
    border-radius: 12px;
    background: rgba(129, 216, 111, 0.12);
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
}

.veshop-auth-chip .dot {
    width: 8px;
    height: 8px;
    margin-top: 7px;
    border-radius: 999px;
    background: #81d86f;
    flex-shrink: 0;
}

.veshop-auth-chip p {
    margin: 0;
    color: #f5fdff;
    font-size: 13px;
    line-height: 1.4;
}

.veshop-auth-card {
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(7, 51, 65, 0.15);
    border-radius: 20px;
    background: rgba(245, 253, 255, 0.96);
    box-shadow: 0 30px 70px -45px rgba(7, 51, 65, 0.7);
}

.veshop-auth-logo {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    border: 1px solid rgba(7, 51, 65, 0.72);
    background: #073341;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 20px 35px -24px rgba(2, 17, 22, 0.85);
}

.veshop-auth-logo-img {
    width: 27px;
    height: 27px;
    object-fit: contain;
}

.veshop-auth-subbrand {
    font-size: 10px;
    color: #073341;
    font-weight: 600;
}

@media (min-width: 992px) {
    .veshop-auth-equal-height {
        min-height: 585px;
    }
}

@media (max-width: 991.98px) {
    .veshop-auth-shell {
        padding-top: 0;
    }

    .veshop-auth-equal-height {
        min-height: auto;
    }
}
</style>
