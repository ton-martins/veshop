<script setup>
import { Link } from '@inertiajs/vue3';
import { useBranding } from '@/branding';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
});

const isMenuOpen = ref(false);
const navbarRef = ref(null);
const { brandName, systemIconUrl } = useBranding();

const closeMenu = () => {
    isMenuOpen.value = false;
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
                    <a class="navbar-caption fs-4 ls-1 fw-bold" href="#home">
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
                            <a class="nav-link" href="#home">Início</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#about">Sobre</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#services">Módulos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#price">Planos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#contacts">Contato</a>
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
                            <a class="nav-link" href="#home" @click="closeMenu">Início</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#about" @click="closeMenu">Sobre</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#services" @click="closeMenu">Módulos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#price" @click="closeMenu">Planos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#contacts" @click="closeMenu">Contato</a>
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

<style scoped>
#navbar {
    z-index: 1200;
    background: transparent;
    backdrop-filter: none;
    border-bottom: 1px solid transparent;
}

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
    border: 1px solid rgba(178, 246, 193, 0.42);
    box-shadow: 0 14px 26px -20px rgba(2, 17, 22, 0.82);
    vertical-align: middle;
}

.navbar-caption {
    color: #f2fff6;
}

.landing-navbar-shell {
    display: flex;
    align-items: center;
    min-height: 74px;
}

.landing-navbar-desktop {
    flex: 1;
    align-items: center;
    justify-content: space-between;
}

#navbar-navlist {
    display: flex;
    flex-direction: row;
}

.landing-navbar-mobile {
    display: none;
    border-top: 1px solid rgba(214, 255, 223, 0.18);
    background: rgba(6, 25, 30, 0.98);
}

.landing-navbar-mobile.is-open {
    display: block;
}

.landing-navbar-mobile .navbar-nav {
    margin: 0.5rem 0 0;
}

.landing-navbar-mobile .nav-btn {
    margin-bottom: 0.6rem;
}

#navbar .navbar-nav .nav-link {
    visibility: visible;
    opacity: 1;
    color: rgba(239, 255, 244, 0.86);
    font-weight: 600;
}

#navbar .navbar-nav .nav-link:hover,
#navbar .navbar-nav .nav-link:focus {
    color: #ffffff;
}

.nav-btn {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.nav-link-primary-cta {
    border: none !important;
    background: transparent !important;
    color: rgba(239, 255, 244, 0.86) !important;
    padding: 20px !important;
    font-weight: 600;
}

.nav-link-primary-cta:hover,
.nav-link-primary-cta:focus {
    color: #ffffff !important;
}

.navbar-toggler {
    color: #eaffef;
}

#navbar.nav-sticky {
    background: rgba(5, 20, 24, 0.9);
    backdrop-filter: blur(10px);
    border-bottom-color: rgba(214, 255, 223, 0.24);
}

@media (max-width: 991.98px) {
    #navbar {
        background-color: transparent !important;
        border-bottom-color: transparent;
    }

    #navbar .navbar-caption {
        color: #effff3 !important;
    }

    #navbar.nav-sticky {
        background-color: rgba(6, 25, 30, 0.98) !important;
    }

    #navbar.nav-sticky .logo a {
        color: #effff3 !important;
    }

    #navbar.nav-sticky .navbar-toggler {
        color: #effff3 !important;
    }

    #navbar .landing-navbar-mobile .nav-link,
    #navbar.nav-sticky .landing-navbar-mobile .nav-link {
        color: #effff3 !important;
    }

    #navbar .landing-navbar-mobile .nav-link:hover,
    #navbar .landing-navbar-mobile .nav-link:focus,
    #navbar.nav-sticky .landing-navbar-mobile .nav-link:hover,
    #navbar.nav-sticky .landing-navbar-mobile .nav-link:focus {
        color: #ffffff !important;
    }
}

@media (min-width: 992px) {
    .landing-navbar-mobile {
        display: none !important;
    }
}
</style>
