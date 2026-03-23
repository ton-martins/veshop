<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useBranding } from '@/branding';
import LandingHeader from '@/Components/Public/LandingPage/LandingHeader.vue';
import LandingFooter from '@/Components/Public/LandingPage/LandingFooter.vue';

const { brandName, publicFaviconHref, publicFaviconType } = useBranding();
const updatedAt = '21 de março de 2026';
const pageTitle = computed(() => `Política de Privacidade | ${brandName.value}`);
</script>

<template>
    <Head :title="pageTitle">
        <link head-key="privacy-favicon" rel="icon" :type="publicFaviconType" :href="publicFaviconHref" />
        <link head-key="privacy-remixicon-preload" rel="preload" as="style" href="/landing/css/remixicon.css" />
        <link head-key="privacy-bootstrap-preload" rel="preload" as="style" href="/landing/css/bootstrap.min.css" />
        <link head-key="privacy-style-preload" rel="preload" as="style" href="/landing/css/style.min.css" />
        <link head-key="privacy-remixicon" rel="stylesheet" href="/landing/css/remixicon.css" />
        <link head-key="privacy-bootstrap" rel="stylesheet" href="/landing/css/bootstrap.min.css" />
        <link head-key="privacy-style" rel="stylesheet" href="/landing/css/style.min.css" />
    </Head>

    <div class="landing-page-root legal-page-root">
        <LandingHeader :can-login="true" :force-landing-links="true" />

        <main class="legal-main">
            <article class="legal-card">
                <header class="legal-header">
                    <p class="legal-eyebrow">LGPD</p>
                    <h1 class="legal-title">Política de Privacidade da Plataforma {{ brandName }}</h1>
                    <p class="legal-updated">Última atualização: {{ updatedAt }}</p>
                </header>

                <div class="legal-content">
                    <section>
                        <h2>1. Dados tratados</h2>
                        <p>
                            Tratamos dados cadastrais, operacionais e de acesso necessários para autenticação, execução das rotinas
                            do sistema e suporte técnico aos contratantes e usuários autorizados.
                        </p>
                    </section>

                    <section>
                        <h2>2. Finalidades do tratamento</h2>
                        <p>
                            Os dados são utilizados para operação do serviço, segurança da informação, auditoria, melhoria da plataforma,
                            cumprimento de obrigações legais e comunicação relacionada ao uso do sistema.
                        </p>
                    </section>

                    <section>
                        <h2>3. Compartilhamento</h2>
                        <p>
                            O compartilhamento ocorre apenas quando necessário à prestação do serviço, por exigência legal
                            ou com parceiros que atuem sob contrato e padrões de segurança compatíveis.
                        </p>
                    </section>

                    <section>
                        <h2>4. Segurança e retenção</h2>
                        <p>
                            Adotamos controles técnicos e administrativos para proteger os dados. O período de retenção considera
                            a finalidade do tratamento, requisitos regulatórios e políticas internas de governança.
                        </p>
                    </section>

                    <section>
                        <h2>5. Direitos dos titulares</h2>
                        <p>
                            O titular pode solicitar confirmação de tratamento, acesso, correção, anonimização, bloqueio,
                            eliminação e demais direitos previstos na LGPD, conforme aplicável ao contexto contratual.
                        </p>
                    </section>

                    <section>
                        <h2>6. Contato do controlador</h2>
                        <p>
                            Solicitações relacionadas à privacidade devem ser encaminhadas pelos canais oficiais de atendimento
                            publicados no site institucional da {{ brandName }}.
                        </p>
                    </section>
                </div>
            </article>
        </main>

        <LandingFooter />
    </div>
</template>

<style scoped>
.legal-page-root {
    width: 100%;
    max-width: 100vw;
    overflow-x: hidden;
    overflow-x: clip;
    background:
        radial-gradient(60% 40% at 85% 0%, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(55% 45% at 8% 28%, rgba(52, 182, 130, 0.16) 0%, rgba(52, 182, 130, 0) 72%),
        linear-gradient(180deg, #04161b 0%, #08242c 35%, #0a2f37 70%, #0d3942 100%);
}

.legal-main {
    min-height: 100vh;
    padding: 6.5rem 1rem 2.5rem;
}

.legal-card {
    width: min(920px, 100%);
    margin: 0 auto;
    border-radius: 24px;
    border: 1px solid rgba(214, 255, 223, 0.22);
    background: rgba(214, 255, 223, 0.08);
    box-shadow: 0 26px 44px -34px rgba(2, 12, 10, 0.92);
    padding: 1.6rem;
}

.legal-header {
    border-bottom: 1px solid rgba(214, 255, 223, 0.2);
    padding-bottom: 0.95rem;
}

.legal-eyebrow {
    margin: 0;
    font-size: 0.76rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #bff6cc;
    font-weight: 700;
}

.legal-title {
    margin: 0.45rem 0 0;
    color: #effff3;
    font-size: clamp(1.4rem, 3vw, 2rem);
    line-height: 1.25;
    font-weight: 700 !important;
}

.legal-updated {
    margin: 0.45rem 0 0;
    color: rgba(232, 255, 239, 0.76);
    font-size: 0.9rem;
}

.legal-content {
    margin-top: 1rem;
    display: grid;
    gap: 1rem;
}

.legal-content h2 {
    margin: 0;
    color: #effff3;
    font-size: 1.02rem;
    font-weight: 700 !important;
}

.legal-content p {
    margin: 0.45rem 0 0;
    color: rgba(232, 255, 239, 0.82);
    font-size: 0.95rem;
    line-height: 1.6;
}

@media (max-width: 991.98px) {
    .legal-main {
        padding-top: 5.5rem;
    }
}
</style>
