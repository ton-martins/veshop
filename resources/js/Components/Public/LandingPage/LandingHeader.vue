<script setup>
import { Link } from '@inertiajs/vue3';
import { useBranding } from '@/branding';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    forceLandingLinks: {
        type: Boolean,
        default: false,
    },
});

const isMenuOpen = ref(false);
const navbarRef = ref(null);
const { brandName, systemIconUrl } = useBranding();

const closeMenu = () => {
    isMenuOpen.value = false;
};

const sectionHref = (sectionId) => {
    const hash = `#${sectionId}`;

    if (!props.forceLandingLinks) {
        return hash;
    }

    return `${route('landing')}${hash}`;
};

const updateStickyState = () => {
    const navbar = navbarRef.value;
    if (!navbar) return;

    if (window.scrollY >= 50) {
        navbar.classList.add('nav-sticky');
    } else {
        navbar.classList.remove('nav-sticky');
    }
};

onMounted(() => {
    window.addEventListener('scroll', updateStickyState, { passive: true });
    updateStickyState();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateStickyState);
});
</script>

<template>
    <header>
        <nav
            id="navbar"
            ref="navbarRef"
            class="navbar navbar-expand-lg fixed-top navbar-custom navbar-light sticky sticky-light"
        >
            <div class="container landing-navbar-shell">
                <div class="navbar-brand logo">
                    <a class="navbar-caption fs-4 ls-1 fw-bold" :href="sectionHref('home')">
                        <span class="veshop-system-logo-wrap me-2">
                            <img
                                :src="systemIconUrl"
                                :alt="`Ícone ${brandName}`"
                                class="veshop-system-logo"
                            />
                        </span>
                        {{ brandName }}
                    </a>
                </div>

                <button
                    class="navbar-toggler d-lg-none"
                    type="button"
                    aria-controls="navbarCollapseMobile"
                    :aria-expanded="isMenuOpen"
                    aria-label="Alternar navegação"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <span class="fw-bold fs-4"><i class="ri-menu-5-line"></i></span>
                </button>

                <div id="navbarCollapseDesktop" class="landing-navbar-desktop d-none d-lg-flex">
                    <ul class="navbar-nav mx-auto" id="navbar-navlist">
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('home')">Início</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('about')">Sobre</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('services')">Módulos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('price')">Planos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('contacts')">Contato</a>
                        </li>
                    </ul>

                    <ul class="navbar-nav nav-btn">
                        <template v-if="$page.props.auth.user">
                            <li class="nav-item">
                                <Link class="nav-link" :href="route('home')">Voltar ao portal</Link>
                            </li>
                            <li class="nav-item">
                                <Link class="nav-link" :href="route('logout')" method="post" as="button">Sair</Link>
                            </li>
                        </template>
                        <template v-else>
                            <li class="nav-item" v-if="canLogin">
                                <Link class="nav-link nav-link-primary-cta" :href="route('login')">
                                    Entrar
                                </Link>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <div id="navbarCollapseMobile" class="landing-navbar-mobile d-lg-none" :class="{ 'is-open': isMenuOpen }">
                <div class="container">
                    <ul class="navbar-nav" id="navbar-navlist-mobile">
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('home')" @click="closeMenu">Início</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('about')" @click="closeMenu">Sobre</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('services')" @click="closeMenu">Módulos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('price')" @click="closeMenu">Planos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="sectionHref('contacts')" @click="closeMenu">Contato</a>
                        </li>
                    </ul>

                    <ul class="navbar-nav nav-btn">
                        <template v-if="$page.props.auth.user">
                            <li class="nav-item">
                                <Link class="nav-link" :href="route('home')" @click="closeMenu">Voltar ao portal</Link>
                            </li>
                            <li class="nav-item">
                                <Link
                                    class="nav-link"
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    @click="closeMenu"
                                >
                                    Sair
                                </Link>
                            </li>
                        </template>
                        <template v-else>
                            <li class="nav-item" v-if="canLogin">
                                <Link class="nav-link nav-link-primary-cta" :href="route('login')" @click="closeMenu">
                                    Entrar
                                </Link>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
</template>
