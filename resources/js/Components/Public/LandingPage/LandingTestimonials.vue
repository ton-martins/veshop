<script setup>
const logos = [
    '/landing/images/logo/amazon.svg',
    '/landing/images/logo/google.svg',
    '/landing/images/logo/lenovo.svg',
    '/landing/images/logo/paypal.svg',
    '/landing/images/logo/shopify.svg',
    '/landing/images/logo/spotify.svg',
];

const testimonials = [
    {
        avatar: '/landing/images/users/avatar-1.jpg',
        name: 'Mariana Souza',
        company: 'Loja de moda',
    },
    {
        avatar: '/landing/images/users/avatar-2.jpg',
        name: 'Carlos Menezes',
        company: 'Rede de mercados',
    },
    {
        avatar: '/landing/images/users/avatar-3.jpg',
        name: 'Rafael Lima',
        company: 'Autopeças',
    },
    {
        avatar: '/landing/images/users/avatar-5.jpg',
        name: 'Juliana Costa',
        company: 'Distribuidora',
    },
];

const testimonialText =
    '"Com o Veshop, centralizamos vendas, estoque e financeiro. A equipe ganhou velocidade e conseguimos decidir com base em dados."';
</script>

<template>
    <section class="section testimonial-section bg-light" id="testimonial">
        <div class="container">
            <div class="row align-items-center justify-content-center text-center">
                <div class="col-lg-7">
                    <div class="title-sm">
                        <span>Clientes</span>
                    </div>
                    <div class="section-title-border mt-3"></div>
                    <div class="client-title mt-4">
                        <h2 class="fw-semibold text-primary">O que nossos clientes dizem</h2>
                    </div>
                </div>
            </div>

            <div class="row align-items-center justify-content-center text-center g-4 mt-4">
                <div v-for="logo in logos" :key="logo" class="col-lg-2 col-6">
                    <a href="#!">
                        <img :src="logo" alt="" class="img-fluid brand-logo" />
                    </a>
                </div>
            </div>

            <div class="row align-items-center justify-content-center mt-5 pt-2">
                <div class="col-lg-12">
                    <div class="row g-4">
                        <div v-for="item in testimonials" :key="item.name" class="col-lg-6">
                            <div class="d-flex p-4 text-start shadow-sm testimonial-box h-100">
                                <img
                                    :src="item.avatar"
                                    :alt="item.name"
                                    class="me-3 rounded-circle testimonial-avatar"
                                    width="50"
                                    height="50"
                                />
                                <div class="icon-text-2 ms-3">
                                    <i class="ri-chat-check-line text-success"></i>
                                    <p class="text-muted m-0 fs-xl">{{ testimonialText }}</p>
                                    <ul class="text-warning my-2 d-flex ps-0">
                                        <li v-for="index in 5" :key="`${item.name}-${index}`"><i class="ri-star-s-fill"></i></li>
                                    </ul>
                                    <h5 class="fw-semibold mb-0 mt-2 text-primary">{{ item.name }}</h5>
                                    <small class="text-muted">{{ item.company }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.testimonial-box {
    background: #ffffff;
    align-items: flex-start;
}

.testimonial-avatar {
    width: 64px !important;
    height: 64px !important;
    min-width: 64px !important;
    max-width: 64px !important;
    max-height: 64px !important;
    display: block;
    object-fit: cover !important;
    object-position: center;
    border-radius: 9999px !important;
    overflow: hidden;
    flex: 0 0 64px;
    flex-shrink: 0;
}

@media (max-width: 767.98px) {
    .testimonial-box {
        padding: 1.1rem !important;
    }

    .testimonial-box .icon-text-2 {
        margin-left: 0.8rem !important;
    }

    .testimonial-box p {
        font-size: 0.95rem !important;
        line-height: 1.5;
    }
}

@media (max-width: 575.98px) {
    .testimonial-box {
        padding: 0.95rem !important;
    }

    .testimonial-avatar {
        width: 52px !important;
        height: 52px !important;
        min-width: 52px !important;
        max-width: 52px !important;
        max-height: 52px !important;
        flex-basis: 52px;
    }

    .testimonial-box p {
        font-size: 0.9rem !important;
    }
}

@media (max-width: 360px) {
    .client-title h2 {
        font-size: 1.35rem;
        line-height: 1.3;
    }

    .testimonial-box {
        flex-direction: column;
        gap: 0.55rem;
    }

    .testimonial-box img {
        margin-right: 0 !important;
    }

    .testimonial-avatar {
        width: 46px !important;
        height: 46px !important;
        min-width: 46px !important;
        max-width: 46px !important;
        max-height: 46px !important;
        flex-basis: 46px;
    }

    .testimonial-box .icon-text-2 {
        margin-left: 0 !important;
    }

    .testimonial-box p {
        font-size: 0.84rem !important;
        line-height: 1.4;
    }
}
</style>
