<script setup>
import { useBranding } from '@/branding';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const year = new Date().getFullYear();
const { brandName, systemIconUrl } = useBranding();
const page = usePage();

const contactNumber = computed(() => String(page.props.publicContact?.phone ?? '').trim());
const contactEmail = computed(() => String(page.props.publicContact?.email ?? '').trim());

const contactNumberLabel = computed(() => contactNumber.value || '- 123-4567-890');
const contactEmailLabel = computed(() => contactEmail.value || '- contato@veshop.com.br');

const contactEmailHref = computed(() => (contactEmail.value ? `mailto:${contactEmail.value}` : '#!'));
</script>

<template>
    <footer class="section footer-part-3 py-5 footer-part">
        <div class="container">
            <div class="row my-5 justify-content-between footer-main-row">
                <div class="col-lg-3 align-self-start">
                    <div class="logo">
                        <a class="fs-4 text-light ls-1 fw-bold d-inline-flex align-items-center" href="#home">
                            <span class="veshop-system-logo-wrap me-2">
                                <img
                                    :src="systemIconUrl"
                                    :alt="`Icone ${brandName}`"
                                    class="veshop-system-logo"
                                />
                            </span>
                            {{ brandName }}
                        </a>
                    </div>
                    <div class="d-flex mt-4">
                        <i class="ri-phone-fill text-light"></i>
                        <span class="text-white-50 ms-3 fs-xl">{{ contactNumberLabel }}</span>
                    </div>
                    <div class="d-flex mt-3">
                        <i class="ri-mail-send-fill text-light"></i>
                        <a :href="contactEmailHref" class="text-white-50 ms-3 fs-xl">{{ contactEmailLabel }}</a>
                    </div>
                    <div class="footer-icon-3 ms-lg-auto me-lg-5">
                        <ul class="d-flex mt-4">
                            <li>
                                <a href="#!" class="text-light me-2"><i class="ri-facebook-fill p-2 border rounded-circle text-white-50"></i></a>
                            </li>
                            <li>
                                <a href="#!" class="text-light mx-2"><i class="ri-instagram-fill p-2 border rounded-circle text-white-50"></i></a>
                            </li>
                            <li>
                                <a href="#!" class="text-light mx-2"><i class="ri-twitter-fill p-2 border rounded-circle text-white-50"></i></a>
                            </li>
                            <li>
                                <a href="#!" class="text-light mx-2"><i class="ri-github-fill p-2 border rounded-circle text-white-50"></i></a>
                            </li>
                            <li>
                                <a href="#!" class="text-light mx-2"><i class="ri-google-fill p-2 border rounded-circle text-white-50"></i></a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-4 col-12">
                    <h5 class="text-light">Navegação:</h5>
                    <ul class="list-unstyled mt-4">
                        <li><a href="#about" class="text-white-50">Sobre</a></li>
                        <li><a href="#services" class="text-white-50">Módulos</a></li>
                        <li><a href="#price" class="text-white-50">Planos</a></li>
                        <li><a href="#testimonial" class="text-white-50">Clientes</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4 col-12">
                    <h5 class="text-light fs-5">Institucional:</h5>
                    <ul class="list-unstyled mt-4">
                        <li><a href="/termos-de-uso" class="text-white-50">Termos de uso</a></li>
                        <li><a href="/politica-de-privacidade" class="text-white-50">Política de privacidade</a></li>
                        <li><a href="#!" class="text-white-50">Base de conhecimento</a></li>
                        <li><a href="#!" class="text-white-50">Atualizações</a></li>
                        <li><a href="#!" class="text-white-50">Central de ajuda</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-12 col-12">
                    <h5 class="text-light">Receba novidades do {{ brandName }}:</h5>
                    <div class="form-button mt-4">
                        <form action="" class="d-flex align-items-center">
                            <input type="email" class="form-control" placeholder="Digite seu e-mail" />
                            <a href="#!" class="me-2"><i class="ri-send-plane-2-line"></i></a>
                        </form>
                    </div>
                    <div class="d-flex mt-4">
                        <i class="ri-twitter-fill fs-5 me-3 text-success"></i>
                        <p class="text-white-50">Conteúdos práticos sobre ERP, gestão comercial e desempenho operacional.</p>
                    </div>
                    <a href="#!" class="text-light">Conhecer mais</a>
                </div>
            </div>
        </div>
    </footer>
    <div class="footer-copyright py-3 bg-dark">
        <div class="copy-info text-center text-white-50">
            &copy; {{ year }} {{ brandName }}. Todos os direitos reservados.
        </div>
    </div>
</template>

<style scoped>
.veshop-system-logo {
    width: 22px;
    height: 22px;
    object-fit: contain;
    vertical-align: middle;
}

.veshop-system-logo-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #073341;
    border: 1px solid rgba(7, 51, 65, 0.72);
    box-shadow: 0 14px 26px -20px rgba(2, 17, 22, 0.82);
    vertical-align: middle;
}

.footer-main-row > [class*='col-'] {
    min-width: 0;
}

.footer-main-row .d-flex a {
    overflow-wrap: anywhere;
}

@media (max-width: 767.98px) {
    .footer-main-row {
        margin-top: 1.6rem !important;
        margin-bottom: 1.6rem !important;
        row-gap: 1.3rem;
    }

    .footer-main-row .col-12 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

@media (max-width: 575.98px) {
    .footer-part {
        padding-top: 1.5rem !important;
        padding-bottom: 1.5rem !important;
    }

    .footer-main-row {
        margin-top: 1.15rem !important;
        margin-bottom: 1.15rem !important;
        row-gap: 1rem;
    }

    .footer-part .form-button input {
        padding: 11px 10px;
    }

    .footer-part .form-button a {
        padding: 6px 15px;
    }

    .copy-info {
        font-size: 0.82rem;
        line-height: 1.35;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}

@media (max-width: 360px) {
    .footer-main-row {
        margin-top: 1rem !important;
        margin-bottom: 1rem !important;
    }

    .footer-part .form-button input {
        padding-right: 3rem;
    }
}
</style>
