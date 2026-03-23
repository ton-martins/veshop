<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section id="contacts" class="section cta-premium landing-section-shell">
        <div class="cta-premium-glow" aria-hidden="true"></div>
        <div class="container position-relative">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <article class="cta-box text-center">
                        <p class="cta-eyebrow">Pronto para elevar o padrão?</p>
                        <h2 class="cta-title">Leve a gestão da sua empresa para outro nível.</h2>
                        <p class="cta-subtitle">
                            Fluxo enxuto e arquitetura modular para você crescer.
                        </p>
                        <div class="cta-actions">
                            <Link :href="route('login')" class="btn btn-success cta-btn cta-btn-request veshop-landing-btn">
                                Entrar no Veshop
                            </Link>
                            <a href="#price" class="btn btn-primary cta-btn cta-btn-contact veshop-landing-btn">
                                Revisar planos
                            </a>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.cta-premium {
    --next-section-bg: #04161b;
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(65% 65% at 90% 10%, rgba(129, 216, 111, 0.24) 0%, rgba(129, 216, 111, 0) 72%),
        linear-gradient(180deg, #08242c 0%, #0d3942 100%);
}

.cta-premium-glow {
    position: absolute;
    width: clamp(380px, 48vw, 760px);
    height: clamp(380px, 48vw, 760px);
    border-radius: 999px;
    right: -24%;
    top: -52%;
    background: radial-gradient(circle at center, rgba(129, 216, 111, 0.26) 0%, rgba(129, 216, 111, 0) 76%);
    pointer-events: none;
}

.cta-box {
    border-radius: 26px;
    border: 1px solid rgba(214, 255, 223, 0.24);
    background: linear-gradient(180deg, rgba(214, 255, 223, 0.12) 0%, rgba(214, 255, 223, 0.06) 100%);
    box-shadow: 0 28px 42px -36px rgba(2, 12, 10, 0.9);
    padding: 1.55rem;
}

.cta-eyebrow {
    margin: 0;
    color: #b9f5c8;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.cta-title {
    margin: 0.9rem 0 0;
    color: #effff3;
    font-size: clamp(1.44rem, 2.6vw, 2rem);
    line-height: 1.3;
}

.cta-subtitle {
    margin: 0.7rem 0 0;
    color: rgba(234, 255, 241, 0.8);
}

.cta-actions {
    margin-top: 1.1rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.65rem;
}

.cta-btn {
    border-radius: 10px;
    font-weight: 700;
    padding: 0.62rem 1rem;
}

.cta-btn-request {
    border: none;
    background: #81d86f;
    color: #ffffff;
}

.cta-btn-request:hover {
    color: #ffffff;
    background: #81d86f;
    border-color: #81d86f;
}

.cta-btn-contact {
    border: none;
    background: #073341;
    color: #ffffff;
}

.cta-btn-contact:hover {
    color: #ffffff;
    background: #073341;
    border-color: #073341;
}

@media (max-width: 575.98px) {
    .cta-btn {
        width: 100%;
    }
}
</style>
