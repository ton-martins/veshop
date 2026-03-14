<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
});

const isMenuOpen = ref(false);
const navbarRef = ref(null);

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
                    <a class="navbar-caption fs-4 text-primary ls-1 fw-bold" href="#home">
                        <i class="ri-registered-fill text-success fs-3 me-1"></i>VESHOP
                    </a>
                </div>

                <button
                    class="navbar-toggler d-lg-none"
                    type="button"
                    aria-controls="navbarCollapseMobile"
                    :aria-expanded="isMenuOpen"
                    aria-label="Alternar navegaÃ§Ã£o"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <span class="fw-bold fs-4"><i class="ri-menu-5-line"></i></span>
                </button>

                <div id="navbarCollapseDesktop" class="landing-navbar-desktop d-none d-lg-flex">
                    <ul class="navbar-nav mx-auto" id="navbar-navlist">
                        <li class="nav-item">
                            <a class="nav-link" href="#home">InÃ­cio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#about">Sobre</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#services">MÃ³dulos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#price">Planos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#testimonial">Clientes</a>
                        </li>
                    </ul>

                    <ul class="navbar-nav nav-btn">
                        <li class="nav-item" v-if="$page.props.auth.user">
                            <Link class="nav-link" :href="route('home')">Início</Link>
                        </li>
                        <li class="nav-item" v-else-if="canLogin">
                            <Link class="nav-link" :href="route('login')">
                                Entrar
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div id="navbarCollapseMobile" class="landing-navbar-mobile d-lg-none" :class="{ 'is-open': isMenuOpen }">
                <div class="container">
                    <ul class="navbar-nav" id="navbar-navlist-mobile">
                        <li class="nav-item">
                            <a class="nav-link" href="#home" @click="closeMenu">InÃ­cio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#about" @click="closeMenu">Sobre</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#services" @click="closeMenu">MÃ³dulos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#price" @click="closeMenu">Planos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#testimonial" @click="closeMenu">Clientes</a>
                        </li>
                    </ul>

                    <ul class="navbar-nav nav-btn">
                        <li class="nav-item" v-if="$page.props.auth.user">
                            <Link class="nav-link" :href="route('home')" @click="closeMenu">Início</Link>
                        </li>
                        <li class="nav-item" v-else-if="canLogin">
                            <Link class="nav-link" :href="route('login')" @click="closeMenu">
                                Entrar
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
</template>

<style scoped>
#navbar {
    z-index: 1200;
}

.landing-navbar-shell {
    display: flex;
    align-items: center;
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
    border-top: 1px solid rgba(148, 163, 184, 0.3);
    background: rgba(245, 253, 255, 0.98);
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
}

@media (max-width: 991.98px) {
    #navbar {
        background-color: #f5fdff;
    }
}

@media (min-width: 992px) {
    .landing-navbar-mobile {
        display: none !important;
    }
}
</style>
