<script setup>
const metrics = [
    { value: '99,9%', label: 'Estabilidade da operação' },
    { value: '3x', label: 'Mais visibilidade gerencial' },
    { value: '48h', label: 'Implantação assistida inicial' },
];

const testimonials = [
    {
        quote: 'A experiência no Veshop deixou nossa operação mais previsível e profissional.',
        author: 'Mariana Souza',
        role: 'Diretora comercial',
    },
    {
        quote: 'Unificamos loja e serviços com clareza operacional real.',
        author: 'Carlos Menezes',
        role: 'Gestor de operações',
    },
    {
        quote: 'Fluxo rápido e dados confiáveis para decidir no dia a dia.',
        author: 'Rafael Lima',
        role: 'CEO',
    },
];
</script>

<template>
    <section id="testimonial" class="section proof-band landing-section-shell landing-section-light">
        <div class="proof-glow" aria-hidden="true"></div>
        <div class="container position-relative">
            <div class="row align-items-end justify-content-between g-3">
                <div class="col-lg-6">
                    <div class="title-sm">
                        <span>Prova social</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <h2 class="proof-title">
                        Empresas escolhem o Veshop para gestão com consistência.
                    </h2>
                </div>
                <div class="col-lg-5">
                    <div class="row g-2">
                        <div v-for="metric in metrics" :key="metric.label" class="col-4">
                            <article class="proof-metric">
                                <p class="value">{{ metric.value }}</p>
                                <p class="label">{{ metric.label }}</p>
                            </article>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4 g-3">
                <div v-for="item in testimonials" :key="item.author" class="col-lg-4">
                    <article class="proof-card h-100">
                        <p class="quote">“{{ item.quote }}”</p>
                        <p class="author">{{ item.author }}</p>
                        <p class="role">{{ item.role }}</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.proof-band {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0d3942 100%);
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(74% 72% at 90% 5%, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 70%),
        linear-gradient(180deg, #f7fbf8 0%, #eef5f1 100%);
}

.proof-glow {
    position: absolute;
    width: clamp(380px, 50vw, 760px);
    height: clamp(380px, 50vw, 760px);
    border-radius: 999px;
    left: -18%;
    bottom: -48%;
    background: radial-gradient(circle at center, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 76%);
    pointer-events: none;
}

.proof-title {
    margin-top: 1rem;
    margin-bottom: 0;
    color: #0e2c22;
    font-size: clamp(1.52rem, 2.8vw, 2.15rem);
    line-height: 1.28;
}

.proof-metric {
    border-radius: 14px;
    border: 1px solid rgba(47, 100, 73, 0.2);
    background: rgba(255, 255, 255, 0.9);
    padding: 0.65rem;
    height: 100%;
    box-shadow: 0 14px 24px -20px rgba(14, 50, 35, 0.32);
}

.proof-metric .value {
    margin: 0;
    color: #1f5a3e;
    font-size: 1.03rem;
    font-weight: 700;
}

.proof-metric .label {
    margin: 0.2rem 0 0;
    color: rgba(25, 58, 46, 0.78);
    font-size: 0.72rem;
    line-height: 1.3;
}

.proof-card {
    border-radius: 20px;
    border: 1px solid rgba(47, 100, 73, 0.2);
    background: rgba(255, 255, 255, 0.92);
    padding: 0.9rem;
    box-shadow: 0 18px 28px -24px rgba(14, 50, 35, 0.36);
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.proof-card:hover {
    transform: translateY(-4px);
    border-color: rgba(47, 100, 73, 0.3);
    box-shadow: 0 22px 32px -24px rgba(14, 50, 35, 0.44);
}

.proof-card .quote {
    margin: 0;
    color: rgba(26, 56, 46, 0.9);
    line-height: 1.5;
}

.proof-card .author {
    margin: 0.8rem 0 0;
    color: #113328;
    font-weight: 700;
}

.proof-card .role {
    margin: 0.1rem 0 0;
    color: rgba(23, 53, 42, 0.74);
    font-size: 0.8rem;
}
</style>
