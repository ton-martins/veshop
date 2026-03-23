<script setup>
import { Link } from '@inertiajs/vue3';

const rotatingWords = [
    'controle operacional',
    'previsibilidade financeira',
    'produtividade da equipe',
    'clareza na tomada de decisão',
];

const loopingWords = [...rotatingWords, rotatingWords[0]];
</script>

<template>
    <section id="home" class="landing-hero landing-section-shell">
        <div class="container position-relative">
            <div class="row align-items-center g-4 g-xl-5">
                <div class="col-lg-6">
                    <div class="hero-rotating" aria-hidden="true">
                        <span class="hero-rotating-static">Sua operação vai ter mais</span>
                        <span class="hero-rotating-words">
                            <span v-for="(word, index) in loopingWords" :key="`${word}-${index}`" class="hero-rotating-word">
                                {{ word }}
                            </span>
                        </span>
                    </div>

                    <h1 class="hero-title">
                        Gestão de <span class="hero-title-highlight">ponta</span>,
                        <span class="hero-title-line">sem ruído na operação.</span>
                    </h1>

                    <p class="hero-subtitle">
                        O Veshop unifica vendas, serviços e financeiro em um fluxo único para equipes que querem
                        escala com controle.
                    </p>

                    <div class="hero-actions">
                        <Link :href="route('login')" class="btn hero-btn hero-btn-primary veshop-landing-btn">
                            Entrar no Veshop
                            <i class="ri-arrow-right-up-line"></i>
                        </Link>
                        <a href="#price" class="btn hero-btn hero-btn-secondary veshop-landing-btn">
                            Ver planos
                            <i class="ri-price-tag-3-line"></i>
                        </a>
                    </div>

                    <div class="hero-proof">
                        <p class="hero-proof-label">Sistema bem avaliado por gestores de operação</p>
                        <div class="hero-proof-stars" role="img" aria-label="Cinco estrelas">
                            <i v-for="n in 5" :key="n" class="ri-star-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <article class="hero-showcase" aria-label="Mockup da plataforma Veshop em notebook e celular">
                        <div class="showcase-desktop">
                            <img
                                src="/landing/images/mockups/mockup.png"
                                alt="Mockup do sistema Veshop"
                                loading="eager"
                                decoding="async"
                            />
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.landing-hero {
    position: relative;
    overflow: hidden;
    padding: 158px 0 108px;
    background:
        radial-gradient(68% 68% at 10% 5%, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(52% 50% at 88% 8%, rgba(7, 51, 65, 0.14) 0%, rgba(7, 51, 65, 0) 72%),
        linear-gradient(180deg, #f6fbf8 0%, #eff5f2 100%);
}

.hero-rotating {
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    min-height: 28px;
    margin-bottom: 0.88rem;
    color: #5f7388;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.hero-rotating-static {
    white-space: nowrap;
}

.hero-rotating-words {
    position: relative;
    height: 28px;
    overflow: hidden;
}

.hero-rotating-word {
    display: block;
    height: 28px;
    line-height: 28px;
    white-space: nowrap;
    color: #2ea773;
    font-weight: 700;
    animation: rotatingWords 10s infinite;
}

@keyframes rotatingWords {
    0%,
    12% {
        transform: translateY(0);
    }
    18%,
    30% {
        transform: translateY(-100%);
    }
    36%,
    48% {
        transform: translateY(-200%);
    }
    54%,
    66% {
        transform: translateY(-300%);
    }
    72%,
    84% {
        transform: translateY(-400%);
    }
    100% {
        transform: translateY(-400%);
    }
}

.hero-title {
    margin-bottom: 0;
    color: #0a4152;
    font-size: clamp(2rem, 4.2vw, 3.32rem);
    line-height: 1.06;
    letter-spacing: -0.02em;
    font-weight: 800;
}

.hero-title .hero-title-line {
    display: block;
    color: inherit;
}

.hero-title .hero-title-highlight {
    color: #72cd72;
}

.hero-subtitle {
    margin-top: 1rem;
    margin-bottom: 0;
    color: #5f7388;
    font-size: 0.98rem;
    line-height: 1.6;
    max-width: 560px;
}

.hero-actions {
    margin-top: 1.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.68rem;
}

.hero-btn {
    border-radius: 10px;
    font-weight: 700;
    padding: 0.72rem 1.04rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.hero-btn-primary {
    background: #073341;
    border: 1px solid #073341;
    color: #ffffff;
    box-shadow: 0 18px 34px -24px rgba(7, 51, 65, 0.56);
}

.hero-btn-primary:hover {
    background: #0a4255;
    border-color: #0a4255;
    color: #ffffff;
}

.hero-btn-secondary {
    border: 1px solid rgba(7, 51, 65, 0.28);
    background: transparent;
    color: #073341;
}

.hero-btn-secondary:hover {
    border-color: rgba(7, 51, 65, 0.56);
    background: rgba(7, 51, 65, 0.06);
    color: #073341;
}

.hero-proof {
    margin-top: 1.12rem;
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.62rem;
}

.hero-proof-label {
    margin: 0;
    color: #5f7388;
    font-size: 0.8rem;
    font-weight: 600;
}

.hero-proof-stars {
    display: inline-flex;
    align-items: center;
    gap: 0.18rem;
    color: #f9ac2f;
    font-size: 0.8rem;
}

.hero-showcase {
    position: relative;
    margin: 0;
    padding: 0;
}

.showcase-desktop {
    position: relative;
    background: transparent;
    box-shadow: none;
    border-radius: 0;
    overflow: visible;
    z-index: 1;
}

.showcase-desktop img {
    width: 100%;
    display: block;
    height: auto;
    background: transparent;
    box-shadow: none;
    mix-blend-mode: normal;
}

@media (max-width: 991.98px) {
    .landing-hero {
        padding: 136px 0 88px;
    }

    .hero-showcase {
        max-width: 660px;
    }
}

@media (max-width: 575.98px) {
    .hero-title {
        font-size: clamp(1.68rem, 8.1vw, 2.18rem);
    }

    .hero-subtitle {
        font-size: 0.9rem;
    }

    .hero-btn {
        width: 100%;
        justify-content: center;
    }

    .hero-proof {
        gap: 0.44rem;
    }

    .hero-proof-label {
        font-size: 0.74rem;
    }

    .hero-showcase {
        max-width: 100%;
    }

    .showcase-desktop {
        border-radius: 0;
    }
}
</style>
