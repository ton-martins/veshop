<script setup>
const metrics = [
    { value: '99,9%', label: 'Estabilidade da operacao' },
    { value: '3x', label: 'Mais visibilidade gerencial' },
    { value: '48h', label: 'Implantacao assistida inicial' },
];

const testimonials = [
    {
        quote: 'A experiencia no Veshop deixou nossa operacao mais previsivel e profissional.',
        author: 'Mariana Souza',
        role: 'Diretora comercial',
    },
    {
        quote: 'Unificamos loja e servicos com clareza operacional real.',
        author: 'Carlos Menezes',
        role: 'Gestor de operacoes',
    },
    {
        quote: 'Fluxo rapido e dados confiaveis para decidir no dia a dia.',
        author: 'Rafael Lima',
        role: 'CEO',
    },
];

const initials = (name) => {
    return String(name ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
};
</script>

<template>
    <section id="testimonial" class="section proof-band landing-section-shell">
        <div class="proof-glow" aria-hidden="true"></div>
        <div class="container position-relative">
            <div class="row align-items-end justify-content-between g-3">
                <div class="col-lg-6">
                    <div class="title-sm">
                        <span>Prova social</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <h2 class="proof-title">
                        Empresas escolhem o Veshop para gestao com consistencia.
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
                        <span class="proof-avatar">{{ initials(item.author) }}</span>
                        <div class="proof-card-copy">
                            <i class="ri-chat-check-line proof-quote-icon"></i>
                            <p class="quote">"{{ item.quote }}"</p>
                            <ul class="proof-rating" aria-label="Avaliacao 5 de 5">
                                <li v-for="star in 5" :key="`${item.author}-${star}`">
                                    <i class="ri-star-s-fill"></i>
                                </li>
                            </ul>
                            <p class="author">{{ item.author }}</p>
                            <p class="role">{{ item.role }}</p>
                        </div>
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
        linear-gradient(180deg, #08242c 0%, #0d3942 100%);
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
    color: #effff3;
    font-size: clamp(1.52rem, 2.8vw, 2.15rem);
    line-height: 1.28;
}

.proof-metric {
    border-radius: 0.5rem;
    border: 0;
    background: #ffffff;
    padding: 0.65rem;
    height: 100%;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.proof-metric .value {
    margin: 0;
    color: #073341;
    font-size: 1.03rem;
    font-weight: 700;
}

.proof-metric .label {
    margin: 0.2rem 0 0;
    color: #8797a7;
    font-size: 0.72rem;
    line-height: 1.3;
}

.proof-card {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    border-radius: 0.5rem;
    border: 0;
    background: #ffffff;
    padding: 1rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    text-align: left;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.proof-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.35rem 0.8rem rgba(20, 24, 34, 0.12);
}

.proof-avatar {
    width: 50px;
    height: 50px;
    border-radius: 999px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #073341;
    color: #f5fdff;
    font-size: 0.84rem;
    font-weight: 700;
}

.proof-card-copy {
    min-width: 0;
}

.proof-quote-icon {
    color: #81d86f;
    font-size: 1rem;
}

.proof-card .quote {
    margin: 0.15rem 0 0;
    color: #8797a7;
    line-height: 1.5;
    font-size: 0.9rem;
}

.proof-rating {
    list-style: none;
    margin: 0.4rem 0 0;
    padding: 0;
    display: flex;
    gap: 0.1rem;
    color: #e4ab00;
    font-size: 0.82rem;
}

.proof-card .author {
    margin: 0.5rem 0 0;
    color: #073341;
    font-weight: 600;
}

.proof-card .role {
    margin: 0.05rem 0 0;
    color: #8797a7;
    font-size: 0.8rem;
}
</style>
