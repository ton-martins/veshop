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
        <LandingHeader :can-login="true" />

        <main class="legal-main">
            <section class="mx-auto w-full max-w-4xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header class="mb-6 border-b border-slate-200 pb-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">LGPD</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Política de Privacidade da Plataforma {{ brandName }}</h1>
                    <p class="mt-2 text-sm text-slate-600">Última atualização: {{ updatedAt }}</p>
                </header>

                <div class="space-y-5 text-sm leading-relaxed text-slate-700">
                    <section>
                        <h2 class="text-base font-semibold text-slate-900">1. Dados tratados</h2>
                        <p class="mt-1">
                            Tratamos dados cadastrais, operacionais e de acesso necessários para autenticação, execução das rotinas
                            do sistema e suporte técnico aos contratantes e usuários autorizados.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">2. Finalidades do tratamento</h2>
                        <p class="mt-1">
                            Os dados são utilizados para operação do serviço, segurança da informação, auditoria, melhoria da plataforma,
                            cumprimento de obrigações legais e comunicação relacionada ao uso do sistema.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">3. Compartilhamento</h2>
                        <p class="mt-1">
                            O compartilhamento ocorre apenas quando necessário à prestação do serviço, por exigência legal
                            ou com parceiros que atuem sob contrato e padrões de segurança compatíveis.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">4. Segurança e retenção</h2>
                        <p class="mt-1">
                            Adotamos controles técnicos e administrativos para proteger os dados. O período de retenção considera
                            a finalidade do tratamento, requisitos regulatórios e políticas internas de governança.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">5. Direitos dos titulares</h2>
                        <p class="mt-1">
                            O titular pode solicitar confirmação de tratamento, acesso, correção, anonimização, bloqueio,
                            eliminação e demais direitos previstos na LGPD, conforme aplicável ao contexto contratual.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">6. Contato do controlador</h2>
                        <p class="mt-1">
                            Solicitações relacionadas à privacidade devem ser encaminhadas pelos canais oficiais de atendimento
                            publicados no site institucional da {{ brandName }}.
                        </p>
                    </section>
                </div>
            </section>
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
}

.legal-main {
    min-height: 100vh;
    background: #f1f5f9;
    padding: 6.5rem 1rem 2.5rem;
}

@media (max-width: 991.98px) {
    .legal-main {
        padding-top: 5.5rem;
    }
}
</style>
