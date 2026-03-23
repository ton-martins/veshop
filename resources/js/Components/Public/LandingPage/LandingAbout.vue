<script setup>
import { computed } from 'vue';
import { useBranding } from '@/branding';

const { landingImages } = useBranding();
const aboutImageUrl = computed(() => landingImages.value.about || '/landing/images/about.png');
</script>

<template>
    <section class="section about-section landing-section-shell landing-section-light" id="about">
        <div class="container">
            <div class="row align-items-center justify-content-between g-4">
                <div class="col-lg-6">
                    <div class="photo-shell">
                        <div class="photo-shell-glow" aria-hidden="true"></div>
                        <div class="photo-shell-frame">
                            <img :src="aboutImageUrl" alt="Equipe utilizando o Veshop" class="photo-image" />
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="title-sm">
                        <span>Sobre o Veshop</span>
                    </div>
                    <div class="section-title-border mt-3 m-0"></div>
                    <div class="about-title mt-4">
                        <h2 class="fw-semibold text-primary lh-base">
                            ERP pronto para rotina real de comércio e serviços
                        </h2>
                        <p>
                            Centralize vendas, estoque, financeiro e fiscal em uma única operação,
                            com dados consistentes para decisão diária.
                        </p>
                        <div class="about-btn mt-4">
                            <a href="#services" class="btn fw-semibold landing-cta-solid">
                                Conhecer módulos <i class="ri-arrow-right-line"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.about-section {
    --next-section-bg: linear-gradient(180deg, #08242c 0%, #0b333c 100%);
    position: relative;
    background:
        radial-gradient(62% 70% at 12% 8%, rgba(129, 216, 111, 0.2) 0%, rgba(129, 216, 111, 0) 72%),
        radial-gradient(44% 48% at 84% 20%, rgba(52, 182, 130, 0.12) 0%, rgba(52, 182, 130, 0) 72%),
        linear-gradient(180deg, #f6fbf8 0%, #edf4f0 100%);
}

.about-section .title-sm span {
    color: #2f8b45;
}

.about-section .section-title-border {
    background: #5ac178;
}

.photo-shell {
    position: relative;
    isolation: isolate;
    padding: 14px;
}

.photo-shell::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 32px;
    background: linear-gradient(145deg, rgba(166, 244, 188, 0.56) 0%, rgba(34, 114, 89, 0.46) 48%, rgba(6, 47, 42, 0.72) 100%);
    z-index: -2;
}

.photo-shell::after {
    content: '';
    position: absolute;
    inset: 12px -10px -10px 12px;
    border-radius: 30px;
    border: 1px solid rgba(157, 227, 173, 0.35);
    background: linear-gradient(150deg, rgba(7, 43, 38, 0.38), rgba(9, 58, 49, 0.22));
    z-index: -3;
}

.photo-shell-glow {
    position: absolute;
    inset: -12% -8% auto;
    height: 72%;
    border-radius: 32px;
    background: radial-gradient(circle at center, rgba(129, 216, 111, 0.26) 0%, rgba(129, 216, 111, 0) 72%);
    z-index: -1;
}

.photo-shell-frame {
    position: relative;
    border-radius: 26px;
    padding: 0;
    border: 1px solid rgba(202, 255, 218, 0.56);
    background: transparent;
    box-shadow: 0 22px 36px -30px rgba(4, 24, 19, 0.52);
}

.photo-image {
    width: 100%;
    height: 420px;
    object-fit: cover;
    border-radius: 18px;
}

.about-title p {
    margin-top: 0.9rem;
    margin-bottom: 0;
    color: rgba(29, 57, 47, 0.84);
    line-height: 1.58;
}

.about-title h2 {
    color: #0f2b22 !important;
}

.about-btn .landing-cta-solid {
    border: 1px solid #9ce1a8 !important;
    background: #9ce1a8 !important;
    color: #072016 !important;
    border-radius: 10px;
    box-shadow: 0 18px 24px -20px rgba(16, 62, 39, 0.48);
}

.about-btn .landing-cta-solid:hover,
.about-btn .landing-cta-solid:focus {
    border-color: #7fcb90 !important;
    background: #7fcb90 !important;
    color: #062214 !important;
}

@media (max-width: 991.98px) {
    .photo-image {
        height: auto;
        max-height: 430px;
    }
}
</style>
