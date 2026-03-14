<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section class="cta-section py-5 z-1 cta-shell" id="contacts">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-lg-7">
                    <div class="cta-title text-center">
                        <h2 class="fw-semibold text-white lh-base">Leve a gestão do seu comércio para outro nível</h2>
                        <p>Fale com nosso time e veja o Veshop funcionando na sua realidade.</p>
                    </div>
                    <div class="cta-btn">
                        <Link :href="route('login')" class="btn btn-primary cta-action-btn">
                            Entrar no painel
                            <i class="ri-arrow-right-up-line"></i>
                        </Link>
                        <a href="#contacts" class="btn btn-success cta-action-btn">
                            Agendar demonstração
                            <i class="ri-play-circle-line"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="cta-glow cta-glow-right" aria-hidden="true"></div>
        <div class="cta-glow cta-glow-left" aria-hidden="true"></div>
    </section>
</template>

<style scoped>
.cta-shell {
    position: relative;
    overflow: hidden;
}

.cta-shell .container {
    position: relative;
    z-index: 2;
}

.cta-btn {
    margin-top: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
}

.cta-action-btn {
    border-radius: 10px;
    padding: 12px 18px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.cta-glow {
    position: absolute;
    top: 0;
    width: clamp(200px, 24vw, 340px);
    height: clamp(200px, 24vw, 340px);
    border-radius: 9999px;
    pointer-events: none;
    z-index: 1;
}

.cta-glow-right {
    right: 0;
    transform: translate(35%, -42%);
    background: radial-gradient(circle at center, rgba(126, 211, 103, 0.22) 0%, rgba(126, 211, 103, 0) 76%);
}

.cta-glow-left {
    left: 0;
    transform: translate(-35%, -42%);
    background: radial-gradient(circle at center, rgba(126, 211, 103, 0.18) 0%, rgba(126, 211, 103, 0) 76%);
}

@media (max-width: 767.98px) {
    .cta-glow {
        width: clamp(150px, 36vw, 220px);
        height: clamp(150px, 36vw, 220px);
    }

    .cta-glow-right {
        transform: translate(45%, -44%);
    }

    .cta-glow-left {
        transform: translate(-45%, -44%);
    }
}

@media (max-width: 575.98px) {
    .cta-action-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
