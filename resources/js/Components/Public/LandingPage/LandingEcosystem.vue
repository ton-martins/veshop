<script setup>
const modules = [
    {
        icon: 'ri-cpu-line',
        title: 'Núcleo ERP',
        text: 'Cadastros, fiscal e financeiro em base única.',
    },
    {
        icon: 'ri-links-line',
        title: 'Integrações',
        text: 'Conecte canais de venda e pagamentos sem fricção.',
    },
    {
        icon: 'ri-store-3-line',
        title: 'Comércio e serviços',
        text: 'Fluxos de loja, catálogo e agenda por nicho.',
    },
    {
        icon: 'ri-shield-check-line',
        title: 'Governança',
        text: 'Permissões e trilhas por contratante.',
    },
];
</script>

<template>
    <section id="ecosistema" class="ecosystem-band section landing-section-shell">
        <div class="ecosystem-band-glow" aria-hidden="true"></div>
        <div class="container position-relative">
            <div class="row align-items-center g-4">
                <div class="col-lg-5">
                    <div class="title-sm">
                        <span>Plataforma</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <h2 class="ecosystem-title">
                        Arquitetura robusta para operação de alto padrão.
                    </h2>
                    <p class="ecosystem-text">
                        Plataforma modular com base sólida para evolução contínua.
                    </p>
                </div>

                <div class="col-lg-7">
                    <div class="row g-3">
                        <div v-for="module in modules" :key="module.title" class="col-md-6">
                            <article class="ecosystem-card h-100">
                                <span class="ecosystem-icon">
                                    <i :class="module.icon"></i>
                                </span>
                                <h3>{{ module.title }}</h3>
                                <p>{{ module.text }}</p>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.ecosystem-band {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0b333c 100%);
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(68% 78% at 50% -5%, rgba(129, 216, 111, 0.18) 0%, rgba(129, 216, 111, 0) 72%),
        linear-gradient(180deg, #08242c 0%, #0b333c 100%);
}

.ecosystem-band-glow {
    position: absolute;
    width: clamp(420px, 55vw, 860px);
    height: clamp(420px, 55vw, 860px);
    border-radius: 999px;
    right: -22%;
    top: -38%;
    background: radial-gradient(circle at center, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 76%);
    pointer-events: none;
}

.ecosystem-title {
    margin-top: 1rem;
    margin-bottom: 0;
    color: #effff3;
    font-size: clamp(1.6rem, 2.8vw, 2.2rem);
    line-height: 1.23;
}

.ecosystem-text {
    margin-top: 0.9rem;
    margin-bottom: 0;
    color: rgba(229, 255, 237, 0.78);
    line-height: 1.58;
}

.ecosystem-card {
    border-radius: 20px;
    border: 1px solid rgba(214, 255, 223, 0.2);
    background: rgba(214, 255, 223, 0.08);
    padding: 1rem;
    box-shadow: 0 18px 34px -30px rgba(2, 12, 10, 0.9);
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.ecosystem-card:hover {
    transform: none;
    border-color: rgba(214, 255, 223, 0.2);
    box-shadow: 0 18px 34px -30px rgba(2, 12, 10, 0.9);
}

.ecosystem-icon {
    width: auto;
    height: auto;
    border-radius: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: inherit;
}

.ecosystem-icon i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
    border-radius: 10px;
    background: rgba(129, 216, 111, 0.2);
    color: #073341;
    font-size: 1.1rem;
    line-height: 1;
}

.ecosystem-card h3 {
    margin-top: 0.62rem;
    margin-bottom: 0;
    color: #effff3;
    font-size: 1rem;
    font-weight: 700;
}

.ecosystem-card p {
    margin-top: 0.42rem;
    margin-bottom: 0;
    color: rgba(229, 255, 237, 0.78);
    font-size: 0.86rem;
    line-height: 1.48;
}
</style>
