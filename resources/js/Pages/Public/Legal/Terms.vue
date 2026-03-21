<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useBranding } from '@/branding';
import LandingHeader from '@/Components/Public/LandingPage/LandingHeader.vue';
import LandingFooter from '@/Components/Public/LandingPage/LandingFooter.vue';

const { brandName, publicFaviconHref, publicFaviconType } = useBranding();
const updatedAt = '21 de março de 2026';
const pageTitle = computed(() => `Termos de Uso | ${brandName.value}`);
</script>

<template>
    <Head :title="pageTitle">
        <link head-key="legal-favicon" rel="icon" :type="publicFaviconType" :href="publicFaviconHref" />
        <link head-key="legal-remixicon-preload" rel="preload" as="style" href="/landing/css/remixicon.css" />
        <link head-key="legal-bootstrap-preload" rel="preload" as="style" href="/landing/css/bootstrap.min.css" />
        <link head-key="legal-style-preload" rel="preload" as="style" href="/landing/css/style.min.css" />
        <link head-key="legal-remixicon" rel="stylesheet" href="/landing/css/remixicon.css" />
        <link head-key="legal-bootstrap" rel="stylesheet" href="/landing/css/bootstrap.min.css" />
        <link head-key="legal-style" rel="stylesheet" href="/landing/css/style.min.css" />
    </Head>

    <div class="landing-page-root legal-page-root">
        <LandingHeader :can-login="true" />

        <main class="legal-main">
            <section class="mx-auto w-full max-w-4xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header class="mb-6 border-b border-slate-200 pb-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documento institucional</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Termos de Uso da Plataforma {{ brandName }}</h1>
                    <p class="mt-2 text-sm text-slate-600">Última atualização: {{ updatedAt }}</p>
                </header>

                <div class="space-y-5 text-sm leading-relaxed text-slate-700">
                    <section>
                        <h2 class="text-base font-semibold text-slate-900">1. Aceite e finalidade</h2>
                        <p class="mt-1">
                            Ao utilizar a plataforma {{ brandName }}, o usuário declara ciência e concordância com estes termos.
                            O sistema é destinado à gestão comercial e operacional de contratantes cadastrados.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">2. Cadastro e responsabilidades</h2>
                        <p class="mt-1">
                            O contratante é responsável pela veracidade dos dados informados, pelo uso adequado das credenciais de acesso
                            e pela gestão das permissões dos usuários vinculados ao seu ambiente.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">3. Uso permitido</h2>
                        <p class="mt-1">
                            É proibido utilizar a plataforma para atividades ilícitas, tentativa de acesso indevido, engenharia reversa,
                            distribuição de conteúdo malicioso ou qualquer ação que comprometa segurança e disponibilidade do serviço.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">4. Planos, limites e suspensão</h2>
                        <p class="mt-1">
                            Funcionalidades, limites de uso e capacidade de armazenamento variam conforme o plano contratado.
                            O descumprimento destes termos pode resultar em suspensão temporária ou encerramento da conta.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">5. Propriedade intelectual</h2>
                        <p class="mt-1">
                            O software, identidade visual, documentação e componentes da plataforma {{ brandName }} são protegidos por
                            direitos de propriedade intelectual e não podem ser reproduzidos sem autorização.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-base font-semibold text-slate-900">6. Contato</h2>
                        <p class="mt-1">
                            Para dúvidas sobre estes termos, utilize os canais oficiais de atendimento disponíveis no site institucional.
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
