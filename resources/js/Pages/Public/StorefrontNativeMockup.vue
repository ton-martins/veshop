<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import {
    Bell,
    ChevronRight,
    CreditCard,
    Heart,
    Home,
    LayoutGrid,
    LogOut,
    MapPin,
    Menu,
    Minus,
    Package,
    Plus,
    Search,
    Settings,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Star,
    UserRound,
    Wallet,
} from 'lucide-vue-next';

const hexToRgb = (hex) => {
    const safe = String(hex ?? '').trim().replace('#', '');
    if (!/^[0-9a-fA-F]{6}$/.test(safe)) return { r: 255, g: 79, b: 31 };
    const value = Number.parseInt(safe, 16);
    return {
        r: (value >> 16) & 255,
        g: (value >> 8) & 255,
        b: value & 255,
    };
};

const withAlpha = (hex, alpha = 1) => {
    const rgb = hexToRgb(hex);
    const safeAlpha = Math.min(1, Math.max(0, alpha));
    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${safeAlpha})`;
};

const contrastColor = (hex) => {
    const { r, g, b } = hexToRgb(hex);
    const luminance = ((r * 299) + (g * 587) + (b * 114)) / 255000;
    return luminance > 0.63 ? '#0f172a' : '#ffffff';
};

const brandPresets = {
    ember: {
        label: 'Ember',
        name: 'Veloce Store',
        logo: 'VS',
        accent: '#ff4f1f',
        surface: '#fff8f4',
        text: '#111827',
    },
    ocean: {
        label: 'Ocean',
        name: 'Brisa Market',
        logo: 'BM',
        accent: '#0ea5e9',
        surface: '#f3fbff',
        text: '#0f172a',
    },
    forest: {
        label: 'Forest',
        name: 'Raiz Hub',
        logo: 'RH',
        accent: '#16a34a',
        surface: '#f5fff7',
        text: '#0f172a',
    },
};

const activeBrandPreset = ref('ember');
const brand = reactive({ ...brandPresets.ember });

watch(activeBrandPreset, (value) => {
    const preset = brandPresets[value] ?? brandPresets.ember;
    brand.label = preset.label;
    brand.name = preset.name;
    brand.logo = preset.logo;
    brand.accent = preset.accent;
    brand.surface = preset.surface;
    brand.text = preset.text;
}, { immediate: true });

const niche = ref('commerce');
const device = ref('mobile');
const activeScreen = ref('home');
const searchTerm = ref('');
const activeCategory = ref('all');
const selectedItemId = ref(null);
const detailQuantity = ref(1);
const paymentMethod = ref('pix');
const checkoutNote = ref('');

const commerceCategories = [
    { id: 'all', label: 'Todos' },
    { id: 'pizza', label: 'Pizzas' },
    { id: 'snacks', label: 'Lanches' },
    { id: 'market', label: 'Mercado' },
    { id: 'drinks', label: 'Bebidas' },
    { id: 'dessert', label: 'Sobremesas' },
];

const serviceCategories = [
    { id: 'all', label: 'Todos' },
    { id: 'hair', label: 'Cabelo' },
    { id: 'beauty', label: 'Beleza' },
    { id: 'health', label: 'Saude' },
    { id: 'homecare', label: 'Casa' },
    { id: 'consulting', label: 'Consultoria' },
];

const commerceCatalog = [
    {
        id: 101,
        category: 'pizza',
        title: 'Pizza Trufada',
        subtitle: 'Massa artesanal e burrata',
        description: 'Pizza grande com molho pomodoro, cogumelos grelhados e finalizacao com azeite trufado.',
        price: 68.9,
        oldPrice: 79.9,
        rating: 4.9,
        reviews: 1860,
        eta: '30-40 min',
        badge: 'Top da semana',
        image: 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=900&q=80',
        highlights: ['Entrega rapida', 'Ingredientes premium', 'Frete gratis acima de R$ 120'],
    },
    {
        id: 102,
        category: 'snacks',
        title: 'Burger Prime',
        subtitle: 'Pao brioche e blend 180g',
        description: 'Hamburguer artesanal com queijo cheddar, cebola caramelizada e molho da casa.',
        price: 39.9,
        oldPrice: 45.9,
        rating: 4.8,
        reviews: 1432,
        eta: '20-30 min',
        badge: 'Mais pedido',
        image: 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=900&q=80',
        highlights: ['Combo disponivel', 'Sem conservantes', 'Opcoes vegetarianas'],
    },
    {
        id: 103,
        category: 'drinks',
        title: 'Suco Fresh Detox',
        subtitle: 'Abacaxi, hortela e gengibre',
        description: 'Bebida natural de 500ml, feita na hora com ingredientes frescos.',
        price: 17.5,
        oldPrice: 21.9,
        rating: 4.7,
        reviews: 542,
        eta: '10-20 min',
        badge: 'Fit',
        image: 'https://images.unsplash.com/photo-1544145945-f90425340c7e?auto=format&fit=crop&w=900&q=80',
        highlights: ['Sem acucar', 'Rico em vitamina C', 'Opcao com agua de coco'],
    },
    {
        id: 104,
        category: 'dessert',
        title: 'Cheesecake Frutas Vermelhas',
        subtitle: 'Fatia generosa',
        description: 'Cheesecake cremoso com calda de frutas vermelhas e crosta de biscoito amanteigado.',
        price: 23.9,
        oldPrice: 28.9,
        rating: 4.9,
        reviews: 728,
        eta: '15-25 min',
        badge: 'Doce do dia',
        image: 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?auto=format&fit=crop&w=900&q=80',
        highlights: ['Receita autoral', 'Fresco diariamente', 'Porcao para compartilhar'],
    },
    {
        id: 105,
        category: 'market',
        title: 'Kit Cafe da Manha',
        subtitle: 'Pao, frios e suco',
        description: 'Kit completo para duas pessoas com itens selecionados para comecar o dia.',
        price: 52.9,
        oldPrice: 61.9,
        rating: 4.6,
        reviews: 410,
        eta: '35-50 min',
        badge: 'Economize 15%',
        image: 'https://images.unsplash.com/photo-1493770348161-369560ae357d?auto=format&fit=crop&w=900&q=80',
        highlights: ['Pronto para consumo', 'Itens premium', 'Recomendado para casal'],
    },
    {
        id: 106,
        category: 'pizza',
        title: 'Pizza Veggie',
        subtitle: 'Tomate confit e pesto',
        description: 'Pizza vegetariana com legumes assados, azeitonas pretas e pesto de manjericao.',
        price: 56.9,
        oldPrice: 66.9,
        rating: 4.8,
        reviews: 935,
        eta: '30-40 min',
        badge: 'Vegetariano',
        image: 'https://images.unsplash.com/photo-1604382355076-af4b0eb60143?auto=format&fit=crop&w=900&q=80',
        highlights: ['Sem carne', 'Queijo especial', 'Massa leve'],
    },
];
const serviceCatalog = [
    {
        id: 201,
        category: 'hair',
        title: 'Corte Premium',
        subtitle: 'Barbearia executiva',
        description: 'Corte personalizado, lavagem e finalizacao completa com consultoria de estilo.',
        price: 79.9,
        oldPrice: 95,
        rating: 4.9,
        reviews: 982,
        eta: '50 min',
        badge: 'Profissional senior',
        image: 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&w=900&q=80',
        highlights: ['Horario estendido', 'Atendimento individual', 'Avaliacao de visagismo'],
    },
    {
        id: 202,
        category: 'beauty',
        title: 'Design de Sobrancelha',
        subtitle: 'Tecnica fio a fio',
        description: 'Modelagem completa com mapeamento facial e finalizacao profissional.',
        price: 54.9,
        oldPrice: 64.9,
        rating: 4.8,
        reviews: 732,
        eta: '40 min',
        badge: 'Novo no app',
        image: 'https://images.unsplash.com/photo-1522336572468-97b06e8ef143?auto=format&fit=crop&w=900&q=80',
        highlights: ['Material esterilizado', 'Especialista certificada', 'Garantia de ajuste'],
    },
    {
        id: 203,
        category: 'homecare',
        title: 'Limpeza Residencial',
        subtitle: 'Equipe com 2 profissionais',
        description: 'Pacote de limpeza profunda para ambientes residenciais com insumos inclusos.',
        price: 189.9,
        oldPrice: 229.9,
        rating: 4.7,
        reviews: 521,
        eta: '3h',
        badge: 'Pacote popular',
        image: 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=900&q=80',
        highlights: ['Produtos inclusos', 'Seguro contra danos', 'Agendamento flexivel'],
    },
    {
        id: 204,
        category: 'health',
        title: 'Sessao Fisioterapia',
        subtitle: 'Reabilitacao motora',
        description: 'Atendimento domiciliar com protocolo individual e orientacoes de continuidade.',
        price: 149.9,
        oldPrice: 169.9,
        rating: 4.9,
        reviews: 344,
        eta: '60 min',
        badge: 'Atendimento em casa',
        image: 'https://images.unsplash.com/photo-1530026405186-ed1f139313f8?auto=format&fit=crop&w=900&q=80',
        highlights: ['Plano de evolucao', 'Profissional credenciado', 'Atendimento humanizado'],
    },
    {
        id: 205,
        category: 'consulting',
        title: 'Mentoria Financeira',
        subtitle: 'Planejamento pessoal',
        description: 'Sessao online para organizar metas, caixa e plano de investimentos.',
        price: 220,
        oldPrice: 260,
        rating: 4.8,
        reviews: 290,
        eta: '75 min',
        badge: '100% online',
        image: 'https://images.unsplash.com/photo-1554224154-22dec7ec8818?auto=format&fit=crop&w=900&q=80',
        highlights: ['Material de apoio', 'Plano de 90 dias', 'Revisao de metas'],
    },
    {
        id: 206,
        category: 'beauty',
        title: 'Maquiagem Social',
        subtitle: 'Eventos e ensaios',
        description: 'Produzimos a maquiagem conforme seu estilo com pele preparada e fixacao duradoura.',
        price: 129.9,
        oldPrice: 159.9,
        rating: 4.7,
        reviews: 468,
        eta: '80 min',
        badge: 'Sexta e sabado',
        image: 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=900&q=80',
        highlights: ['Kit premium', 'Teste de pele', 'Atendimento domiciliar opcional'],
    },
];

const screenOptions = [
    { key: 'login', label: 'Login' },
    { key: 'register', label: 'Cadastro' },
    { key: 'home', label: 'Home' },
    { key: 'listing', label: 'Catalogo' },
    { key: 'detail', label: 'Detalhe' },
    { key: 'favorites', label: 'Favoritos' },
    { key: 'cart', label: 'Carrinho' },
    { key: 'checkout', label: 'Checkout' },
    { key: 'orders', label: 'Pedidos' },
    { key: 'account', label: 'Conta' },
];

const navItems = [
    { key: 'home', label: 'Inicio', icon: Home },
    { key: 'listing', label: 'Catalogo', icon: LayoutGrid },
    { key: 'favorites', label: 'Favoritos', icon: Heart },
    { key: 'orders', label: 'Pedidos', icon: Package },
    { key: 'account', label: 'Conta', icon: UserRound },
];

const accountLinks = [
    { id: 'profile', label: 'Dados pessoais', description: 'Nome, telefone e email', icon: UserRound },
    { id: 'security', label: 'Seguranca', description: 'Senha e autenticacao', icon: ShieldCheck },
    { id: 'payments', label: 'Carteira e pagamento', description: 'Cartoes e saldo', icon: Wallet },
    { id: 'address', label: 'Enderecos', description: 'Casa e trabalho', icon: MapPin },
    { id: 'preferences', label: 'Preferencias', description: 'Notificacoes e temas', icon: Settings },
];

const initialOrders = {
    commerce: [
        { id: '#763019', label: 'Pedido entregue', status: 'Concluido', statusTone: 'success', total: 136.8, meta: '2 itens - Entregue hoje as 11:42' },
        { id: '#763011', label: 'Em preparo', status: 'Em andamento', statusTone: 'warning', total: 89.9, meta: '1 item - Atualizado ha 6 min' },
    ],
    services: [
        { id: '#892102', label: 'Sessao confirmada', status: 'Agendado', statusTone: 'info', total: 149.9, meta: 'Fisioterapia - Quinta 14:00' },
        { id: '#892091', label: 'Servico concluido', status: 'Concluido', statusTone: 'success', total: 79.9, meta: 'Corte premium - Ontem 18:30' },
    ],
};

const ordersState = reactive({
    commerce: [...initialOrders.commerce],
    services: [...initialOrders.services],
});

const cartState = reactive({
    commerce: { 101: 1, 103: 2 },
    services: { 201: 1 },
});

const favoritesState = reactive({
    commerce: [102, 104, 106],
    services: [202, 206],
});

const authForm = reactive({
    name: 'Alex Pereira',
    email: 'alex@veshop.app',
    phone: '(11) 98888-7777',
    password: '********',
    remember: true,
});

const setNiche = (value) => {
    if (value === niche.value) return;
    niche.value = value;
    searchTerm.value = '';
    activeCategory.value = 'all';
    activeScreen.value = 'home';
    detailQuantity.value = 1;
    paymentMethod.value = 'pix';
    checkoutNote.value = '';
};

const currentCatalog = computed(() => (niche.value === 'services' ? serviceCatalog : commerceCatalog));
const currentCategories = computed(() => (niche.value === 'services' ? serviceCategories : commerceCategories));
const normalizedSearch = computed(() => String(searchTerm.value ?? '').trim().toLowerCase());

const filteredItems = computed(() => currentCatalog.value.filter((item) => {
    const categoryMatch = activeCategory.value === 'all' || item.category === activeCategory.value;
    const searchSpace = `${item.title} ${item.subtitle} ${item.description}`.toLowerCase();
    const searchMatch = normalizedSearch.value === '' || searchSpace.includes(normalizedSearch.value);
    return categoryMatch && searchMatch;
}));

const featuredItems = computed(() => currentCatalog.value.slice(0, 3));

watch(currentCatalog, (list) => {
    if (!list.some((item) => item.id === selectedItemId.value)) {
        selectedItemId.value = list[0]?.id ?? null;
    }
}, { immediate: true });

const selectedItem = computed(() => (
    currentCatalog.value.find((item) => item.id === selectedItemId.value) ?? currentCatalog.value[0] ?? null
));

const favoriteIds = computed(() => favoritesState[niche.value] ?? []);
const favoriteItems = computed(() => currentCatalog.value.filter((item) => favoriteIds.value.includes(item.id)));
const isFavorite = (id) => favoriteIds.value.includes(id);

const toggleFavorite = (id) => {
    const current = Array.isArray(favoritesState[niche.value]) ? [...favoritesState[niche.value]] : [];
    const index = current.indexOf(id);
    if (index >= 0) current.splice(index, 1);
    else current.push(id);
    favoritesState[niche.value] = current;
};
const ensureCartBucket = () => {
    if (!cartState[niche.value] || typeof cartState[niche.value] !== 'object') {
        cartState[niche.value] = {};
    }
    return cartState[niche.value];
};

const addToCart = (item, amount = 1) => {
    if (!item) return;
    const bucket = ensureCartBucket();
    const id = Number(item.id);
    bucket[id] = Math.max(1, Number(bucket[id] ?? 0) + Number(amount));
};

const decreaseCart = (id) => {
    const bucket = ensureCartBucket();
    const currentQty = Number(bucket[id] ?? 0);
    if (currentQty <= 1) {
        delete bucket[id];
        return;
    }
    bucket[id] = currentQty - 1;
};

const increaseCart = (id) => {
    const bucket = ensureCartBucket();
    bucket[id] = Math.max(1, Number(bucket[id] ?? 0) + 1);
};

const clearCart = () => {
    cartState[niche.value] = {};
};

const cartEntries = computed(() => {
    const bucket = cartState[niche.value] ?? {};
    return Object.entries(bucket)
        .map(([rawId, rawQty]) => {
            const id = Number(rawId);
            const quantity = Number(rawQty);
            const item = currentCatalog.value.find((catalogItem) => Number(catalogItem.id) === id);
            if (!item || quantity <= 0) return null;
            return {
                ...item,
                quantity,
                lineTotal: item.price * quantity,
            };
        })
        .filter(Boolean);
});

const cartCount = computed(() => cartEntries.value.reduce((sum, item) => sum + item.quantity, 0));
const subtotal = computed(() => cartEntries.value.reduce((sum, item) => sum + item.lineTotal, 0));
const deliveryFee = computed(() => (niche.value === 'services' ? 0 : 9.9));
const platformFee = computed(() => (niche.value === 'services' ? 4.9 : 2.9));
const discount = computed(() => (subtotal.value >= 120 ? subtotal.value * 0.1 : 0));
const total = computed(() => Math.max(0, subtotal.value + deliveryFee.value + platformFee.value - discount.value));

const formatMoney = (value) => new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
}).format(Number(value ?? 0));

const setScreen = (screenKey) => {
    activeScreen.value = screenKey;
    if (screenKey === 'detail' && !selectedItem.value) {
        selectedItemId.value = currentCatalog.value[0]?.id ?? null;
    }
};

const selectItem = (id) => {
    selectedItemId.value = id;
    detailQuantity.value = 1;
    activeScreen.value = 'detail';
};

const goToCheckout = () => {
    if (!cartEntries.value.length) return;
    activeScreen.value = 'checkout';
};

const createOrderFromCart = () => {
    if (!cartEntries.value.length) return;
    const now = new Date();
    const orderId = `#${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}${String(now.getSeconds()).padStart(2, '0')}`;
    const totalItems = cartEntries.value.reduce((sum, item) => sum + item.quantity, 0);
    const descriptor = niche.value === 'services' ? 'Servico confirmado' : 'Pedido enviado';
    const newOrder = {
        id: orderId,
        label: descriptor,
        status: 'Em andamento',
        statusTone: 'warning',
        total: total.value,
        meta: `${totalItems} item(ns) - ${now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`,
    };
    ordersState[niche.value] = [newOrder, ...(ordersState[niche.value] ?? [])];
};

const finalizeOrder = () => {
    if (!cartEntries.value.length) return;
    createOrderFromCart();
    clearCart();
    activeScreen.value = 'orders';
};

const activeOrders = computed(() => ordersState[niche.value] ?? []);
const isDesktop = computed(() => device.value === 'desktop');
const isTablet = computed(() => device.value === 'tablet');
const isAuthScreen = computed(() => activeScreen.value === 'login' || activeScreen.value === 'register');

const deviceSizeMap = {
    mobile: { width: 390, height: 844, radius: 36 },
    tablet: { width: 834, height: 1112, radius: 28 },
    desktop: { width: 1340, height: 900, radius: 24 },
};

const viewportStyle = computed(() => {
    const metrics = deviceSizeMap[device.value] ?? deviceSizeMap.mobile;
    return {
        width: `${metrics.width}px`,
        minHeight: `${metrics.height}px`,
        borderRadius: `${metrics.radius}px`,
    };
});

const heroImage = computed(() => {
    if (activeScreen.value === 'detail' && selectedItem.value?.image) return selectedItem.value.image;
    if (activeScreen.value === 'favorites' && favoriteItems.value[0]?.image) return favoriteItems.value[0].image;
    if (activeScreen.value === 'cart' && cartEntries.value[0]?.image) return cartEntries.value[0].image;
    return featuredItems.value[0]?.image ?? currentCatalog.value[0]?.image ?? '';
});

const screenMeta = {
    home: { title: 'Descubra o melhor do app', subtitle: 'Navegacao nativa com foco em mobile-first' },
    listing: { title: 'Catalogo completo', subtitle: 'Filtros, busca e selecao rapida' },
    detail: { title: 'Detalhes do item', subtitle: 'Descricao completa e configuracao de compra' },
    favorites: { title: 'Seus favoritos', subtitle: 'Itens salvos para pedido rapido' },
    cart: { title: 'Seu carrinho', subtitle: 'Resumo com valores e quantidade' },
    checkout: { title: 'Checkout', subtitle: 'Endereco, pagamento e confirmacao' },
    orders: { title: 'Historico de pedidos', subtitle: 'Acompanhamento em tempo real' },
    account: { title: 'Minha conta', subtitle: 'Dados, preferencias e seguranca' },
};

const currentScreenMeta = computed(() => screenMeta[activeScreen.value] ?? screenMeta.home);
const cartLabel = computed(() => (niche.value === 'services' ? 'Agendar' : 'Adicionar'));
const sectionTitle = computed(() => (niche.value === 'services' ? 'Profissionais e servicos' : 'Lojas e produtos populares'));
const searchPlaceholder = computed(() => (
    niche.value === 'services'
        ? 'Buscar profissional, servico ou categoria'
        : 'Buscar prato, produto ou categoria'
));

const statusClass = (tone) => ({
    'tone-success': tone === 'success',
    'tone-warning': tone === 'warning',
    'tone-info': tone === 'info',
});

const themeVars = computed(() => ({
    '--sfm-accent': brand.accent,
    '--sfm-accent-soft': withAlpha(brand.accent, 0.14),
    '--sfm-accent-fade': withAlpha(brand.accent, 0.3),
    '--sfm-accent-contrast': contrastColor(brand.accent),
    '--sfm-surface': brand.surface,
    '--sfm-text': brand.text,
    '--sfm-text-dim': withAlpha(brand.text, 0.66),
    '--sfm-border': withAlpha('#0f172a', 0.1),
    '--sfm-hero': heroImage.value ? `url('${heroImage.value}')` : 'none',
}));
</script>
<template>
    <Head title="Mockup Loja Nativa Completa" />

    <div class="sfm-page" :style="themeVars">
        <aside class="sfm-controls">
            <div class="sfm-controls__header">
                <h1>Mockup Loja Nativa</h1>
                <p>Fluxo completo para validacao de layout antes da implementacao real.</p>
            </div>

            <div class="sfm-group">
                <p class="sfm-label">Nicho</p>
                <div class="sfm-segmented two-col">
                    <button type="button" :class="{ active: niche === 'commerce' }" @click="setNiche('commerce')">
                        Comercio
                    </button>
                    <button type="button" :class="{ active: niche === 'services' }" @click="setNiche('services')">
                        Servicos
                    </button>
                </div>
            </div>

            <div class="sfm-group">
                <p class="sfm-label">Tema / identidade</p>
                <div class="sfm-chip-grid">
                    <button
                        v-for="(preset, key) in brandPresets"
                        :key="key"
                        type="button"
                        class="sfm-chip"
                        :class="{ active: activeBrandPreset === key }"
                        @click="activeBrandPreset = key"
                    >
                        <span class="sfm-chip__dot" :style="{ background: preset.accent }" />
                        {{ preset.label }}
                    </button>
                </div>
            </div>

            <div class="sfm-group">
                <p class="sfm-label">Viewport</p>
                <div class="sfm-segmented">
                    <button type="button" :class="{ active: device === 'mobile' }" @click="device = 'mobile'">Mobile</button>
                    <button type="button" :class="{ active: device === 'tablet' }" @click="device = 'tablet'">Tablet</button>
                    <button type="button" :class="{ active: device === 'desktop' }" @click="device = 'desktop'">Desktop</button>
                </div>
            </div>

            <div class="sfm-group">
                <p class="sfm-label">Telas do mockup</p>
                <div class="sfm-screen-grid">
                    <button
                        v-for="screen in screenOptions"
                        :key="screen.key"
                        type="button"
                        class="sfm-screen-btn"
                        :class="{ active: activeScreen === screen.key }"
                        @click="setScreen(screen.key)"
                    >
                        {{ screen.label }}
                    </button>
                </div>
            </div>

            <div class="sfm-summary-grid">
                <article>
                    <span class="value">{{ currentCatalog.length }}</span>
                    <span class="label">{{ niche === 'services' ? 'Servicos no catalogo' : 'Produtos no catalogo' }}</span>
                </article>
                <article>
                    <span class="value">{{ currentCategories.length - 1 }}</span>
                    <span class="label">Categorias ativas</span>
                </article>
                <article>
                    <span class="value">{{ cartCount }}</span>
                    <span class="label">Itens no carrinho</span>
                </article>
                <article>
                    <span class="value">{{ activeOrders.length }}</span>
                    <span class="label">Pedidos no historico</span>
                </article>
            </div>
        </aside>

        <section class="sfm-preview">
            <div class="sfm-device" :class="[`is-${device}`]" :style="viewportStyle">
                <div v-if="!isDesktop" class="sfm-status-bar">
                    <span>9:41</span>
                    <span>{{ isTablet ? 'Wi-Fi LTE' : '5G' }}</span>
                </div>

                <div class="sfm-app">
                    <template v-if="isAuthScreen">
                        <div class="sfm-auth">
                            <div class="sfm-auth__hero">
                                <div class="sfm-brand-badge">{{ brand.logo }}</div>
                                <h2>{{ activeScreen === 'login' ? 'Welcome Back' : 'Criar Conta' }}</h2>
                                <p>
                                    {{
                                        activeScreen === 'login'
                                            ? 'Acesse sua conta para continuar no app'
                                            : 'Cadastre-se para navegar pela nova experiencia da loja'
                                    }}
                                </p>
                            </div>

                            <div class="sfm-auth__panel">
                                <label v-if="activeScreen === 'register'" class="sfm-field">
                                    <span>Nome completo</span>
                                    <input v-model="authForm.name" type="text" placeholder="Seu nome completo">
                                </label>

                                <label v-else class="sfm-field">
                                    <span>Email</span>
                                    <input v-model="authForm.email" type="email" placeholder="voce@email.com">
                                </label>

                                <label v-if="activeScreen === 'register'" class="sfm-field">
                                    <span>Telefone</span>
                                    <input v-model="authForm.phone" type="text" placeholder="(11) 99999-8888">
                                </label>

                                <label class="sfm-field">
                                    <span>Senha</span>
                                    <input v-model="authForm.password" type="password" placeholder="********">
                                </label>

                                <label v-if="activeScreen === 'login'" class="sfm-check">
                                    <input v-model="authForm.remember" type="checkbox">
                                    <span>Manter conectado</span>
                                </label>

                                <button type="button" class="sfm-primary-btn" @click="setScreen('home')">
                                    {{ activeScreen === 'login' ? 'Entrar na loja' : 'Criar conta e continuar' }}
                                </button>

                                <button type="button" class="sfm-secondary-btn">Continuar com Google</button>

                                <button
                                    type="button"
                                    class="sfm-link-btn"
                                    @click="setScreen(activeScreen === 'login' ? 'register' : 'login')"
                                >
                                    {{ activeScreen === 'login' ? 'Nao tem conta? Cadastre-se' : 'Ja possui conta? Entrar' }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="sfm-store-shell" :class="{ desktop: isDesktop }">
                            <aside v-if="isDesktop" class="sfm-desktop-left">
                                <div class="sfm-side-brand">
                                    <div class="sfm-side-brand__logo">{{ brand.logo }}</div>
                                    <div>
                                        <strong>{{ brand.name }}</strong>
                                        <small>{{ niche === 'services' ? 'Loja de servicos' : 'Loja de comercio' }}</small>
                                    </div>
                                </div>

                                <nav class="sfm-side-nav">
                                    <button
                                        v-for="item in navItems"
                                        :key="item.key"
                                        type="button"
                                        :class="{ active: activeScreen === item.key }"
                                        @click="setScreen(item.key)"
                                    >
                                        <component :is="item.icon" :size="18" />
                                        {{ item.label }}
                                    </button>
                                </nav>

                                <button type="button" class="sfm-logout-btn">
                                    <LogOut :size="16" />
                                    Sair
                                </button>
                            </aside>

                            <div class="sfm-main">
                                <header class="sfm-header" :class="{ desktop: isDesktop }">
                                    <template v-if="!isDesktop">
                                        <div class="sfm-header__hero">
                                            <div class="sfm-header__top">
                                                <button type="button" class="icon-btn">
                                                    <Menu :size="19" />
                                                </button>
                                                <button type="button" class="icon-btn" @click="setScreen('cart')">
                                                    <ShoppingCart :size="19" />
                                                    <span v-if="cartCount > 0" class="badge-dot">{{ cartCount }}</span>
                                                </button>
                                            </div>

                                            <div class="sfm-header__title">
                                                <small>{{ brand.name }}</small>
                                                <h2>{{ currentScreenMeta.title }}</h2>
                                                <p>{{ currentScreenMeta.subtitle }}</p>
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div class="sfm-desktop-topbar">
                                            <div class="sfm-desktop-search">
                                                <Search :size="17" />
                                                <input v-model="searchTerm" type="text" :placeholder="searchPlaceholder">
                                            </div>

                                            <div class="sfm-desktop-actions">
                                                <button type="button" class="icon-btn">
                                                    <Bell :size="18" />
                                                </button>
                                                <button type="button" class="sfm-profile-pill">
                                                    <span class="avatar">{{ brand.logo }}</span>
                                                    <span>Alex</span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </header>

                                <div class="sfm-content" :class="{ desktop: isDesktop }">
                                    <div class="sfm-panel" :class="{ desktop: isDesktop }">
                                        <section v-if="activeScreen === 'home'" class="sfm-screen">
                                            <div class="sfm-search-inline">
                                                <Search :size="17" />
                                                <input v-model="searchTerm" type="text" :placeholder="searchPlaceholder">
                                            </div>

                                            <div class="sfm-chip-row">
                                                <button
                                                    v-for="category in currentCategories"
                                                    :key="category.id"
                                                    type="button"
                                                    :class="{ active: activeCategory === category.id }"
                                                    @click="activeCategory = category.id"
                                                >
                                                    {{ category.label }}
                                                </button>
                                            </div>

                                            <h3 class="sfm-section-title">Destaques</h3>
                                            <div class="sfm-highlight-scroll">
                                                <article
                                                    v-for="item in featuredItems"
                                                    :key="`feature-${item.id}`"
                                                    class="sfm-highlight-card"
                                                    @click="selectItem(item.id)"
                                                >
                                                    <img :src="item.image" :alt="item.title">
                                                    <span class="tag">{{ item.badge }}</span>
                                                    <div class="overlay">
                                                        <strong>{{ item.title }}</strong>
                                                        <small>{{ item.subtitle }}</small>
                                                        <div class="meta">
                                                            <span>{{ formatMoney(item.price) }}</span>
                                                            <span>{{ item.eta }}</span>
                                                        </div>
                                                    </div>
                                                </article>
                                            </div>

                                            <h3 class="sfm-section-title">{{ sectionTitle }}</h3>
                                            <div class="sfm-list-stack">
                                                <article v-for="item in filteredItems.slice(0, 5)" :key="`home-${item.id}`" class="sfm-list-row">
                                                    <img :src="item.image" :alt="item.title" @click="selectItem(item.id)">
                                                    <div class="info" @click="selectItem(item.id)">
                                                        <strong>{{ item.title }}</strong>
                                                        <p>{{ item.subtitle }}</p>
                                                        <div class="rating">
                                                            <Star :size="13" />
                                                            {{ item.rating.toFixed(1) }}
                                                            <small>({{ item.reviews }})</small>
                                                        </div>
                                                    </div>
                                                    <div class="actions">
                                                        <span class="price">{{ formatMoney(item.price) }}</span>
                                                        <button type="button" class="icon-btn" @click="toggleFavorite(item.id)">
                                                            <Heart :size="16" :class="{ fill: isFavorite(item.id) }" />
                                                        </button>
                                                    </div>
                                                </article>
                                            </div>
                                        </section>

                                        <section v-else-if="activeScreen === 'listing'" class="sfm-screen">
                                            <div class="sfm-search-inline">
                                                <Search :size="17" />
                                                <input v-model="searchTerm" type="text" :placeholder="searchPlaceholder">
                                            </div>

                                            <div class="sfm-chip-row">
                                                <button
                                                    v-for="category in currentCategories"
                                                    :key="`list-${category.id}`"
                                                    type="button"
                                                    :class="{ active: activeCategory === category.id }"
                                                    @click="activeCategory = category.id"
                                                >
                                                    {{ category.label }}
                                                </button>
                                            </div>

                                            <div class="sfm-catalog-list">
                                                <article v-for="item in filteredItems" :key="`catalog-${item.id}`" class="sfm-catalog-row">
                                                    <img :src="item.image" :alt="item.title" @click="selectItem(item.id)">
                                                    <div class="content">
                                                        <strong>{{ item.title }}</strong>
                                                        <p>{{ item.description }}</p>
                                                        <div class="foot">
                                                            <span class="new">{{ formatMoney(item.price) }}</span>
                                                            <span class="old">{{ formatMoney(item.oldPrice) }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="side">
                                                        <button type="button" class="icon-btn" @click="toggleFavorite(item.id)">
                                                            <Heart :size="16" :class="{ fill: isFavorite(item.id) }" />
                                                        </button>
                                                        <button type="button" class="sfm-mini-add" @click="addToCart(item)">
                                                            <Plus :size="15" />
                                                            {{ cartLabel }}
                                                        </button>
                                                    </div>
                                                </article>
                                            </div>

                                            <div v-if="!filteredItems.length" class="sfm-empty">
                                                Nenhum item encontrado com os filtros atuais.
                                            </div>
                                        </section>

                                        <section v-else-if="activeScreen === 'detail'" class="sfm-screen">
                                            <article v-if="selectedItem" class="sfm-detail-card">
                                                <img :src="selectedItem.image" :alt="selectedItem.title">
                                                <div class="body">
                                                    <div class="top">
                                                        <div>
                                                            <strong>{{ selectedItem.title }}</strong>
                                                            <p>{{ selectedItem.subtitle }}</p>
                                                        </div>
                                                        <button type="button" class="icon-btn" @click="toggleFavorite(selectedItem.id)">
                                                            <Heart :size="17" :class="{ fill: isFavorite(selectedItem.id) }" />
                                                        </button>
                                                    </div>

                                                    <p class="description">{{ selectedItem.description }}</p>

                                                    <ul class="sfm-points">
                                                        <li v-for="point in selectedItem.highlights" :key="point">{{ point }}</li>
                                                    </ul>

                                                    <div class="sfm-detail-meta">
                                                        <span>{{ formatMoney(selectedItem.price) }}</span>
                                                        <small>de {{ formatMoney(selectedItem.oldPrice) }}</small>
                                                        <small>{{ selectedItem.eta }}</small>
                                                    </div>

                                                    <div class="sfm-stepper">
                                                        <button type="button" @click="detailQuantity = Math.max(1, detailQuantity - 1)"><Minus :size="14" /></button>
                                                        <strong>{{ detailQuantity }}</strong>
                                                        <button type="button" @click="detailQuantity = detailQuantity + 1"><Plus :size="14" /></button>
                                                    </div>

                                                    <button type="button" class="sfm-primary-btn" @click="addToCart(selectedItem, detailQuantity)">
                                                        {{ cartLabel }} - {{ formatMoney(selectedItem.price * detailQuantity) }}
                                                    </button>
                                                </div>
                                            </article>
                                        </section>

                                        <section v-else-if="activeScreen === 'favorites'" class="sfm-screen">
                                            <div v-if="favoriteItems.length" class="sfm-list-stack">
                                                <article v-for="item in favoriteItems" :key="`fav-${item.id}`" class="sfm-list-row">
                                                    <img :src="item.image" :alt="item.title" @click="selectItem(item.id)">
                                                    <div class="info" @click="selectItem(item.id)">
                                                        <strong>{{ item.title }}</strong>
                                                        <p>{{ item.subtitle }}</p>
                                                        <div class="rating">
                                                            <Star :size="13" />
                                                            {{ item.rating.toFixed(1) }}
                                                            <small>({{ item.reviews }})</small>
                                                        </div>
                                                    </div>
                                                    <div class="actions">
                                                        <button type="button" class="icon-btn" @click="toggleFavorite(item.id)">
                                                            <Heart :size="16" class="fill" />
                                                        </button>
                                                        <button type="button" class="sfm-mini-add" @click="addToCart(item)">
                                                            <Plus :size="14" />
                                                            {{ cartLabel }}
                                                        </button>
                                                    </div>
                                                </article>
                                            </div>
                                            <div v-else class="sfm-empty">Nenhum favorito salvo.</div>
                                        </section>

                                        <section v-else-if="activeScreen === 'cart'" class="sfm-screen">
                                            <div v-if="cartEntries.length" class="sfm-cart">
                                                <article v-for="entry in cartEntries" :key="`cart-${entry.id}`" class="sfm-cart-row">
                                                    <img :src="entry.image" :alt="entry.title">
                                                    <div class="content">
                                                        <strong>{{ entry.title }}</strong>
                                                        <p>{{ formatMoney(entry.price) }}</p>
                                                        <div class="qty">
                                                            <button type="button" @click="decreaseCart(entry.id)"><Minus :size="13" /></button>
                                                            <span>{{ entry.quantity }}</span>
                                                            <button type="button" @click="increaseCart(entry.id)"><Plus :size="13" /></button>
                                                        </div>
                                                    </div>
                                                    <strong class="line-total">{{ formatMoney(entry.lineTotal) }}</strong>
                                                </article>

                                                <div class="sfm-bill">
                                                    <div><span>Subtotal</span><strong>{{ formatMoney(subtotal) }}</strong></div>
                                                    <div><span>Entrega</span><strong>{{ formatMoney(deliveryFee) }}</strong></div>
                                                    <div><span>Taxa plataforma</span><strong>{{ formatMoney(platformFee) }}</strong></div>
                                                    <div><span>Desconto</span><strong>- {{ formatMoney(discount) }}</strong></div>
                                                    <div class="total"><span>Total</span><strong>{{ formatMoney(total) }}</strong></div>
                                                </div>

                                                <button type="button" class="sfm-primary-btn" @click="goToCheckout">Ir para checkout</button>
                                            </div>
                                            <div v-else class="sfm-empty">Carrinho vazio no momento.</div>
                                        </section>
                                        <section v-else-if="activeScreen === 'checkout'" class="sfm-screen">
                                            <article class="sfm-checkout-card">
                                                <h3>Entrega</h3>
                                                <p><MapPin :size="14" /> Rua Mockup, 123 - Centro, Sao Paulo</p>
                                                <small>Complemento: Ap 41</small>
                                            </article>

                                            <article class="sfm-checkout-card">
                                                <h3>Pagamento</h3>
                                                <div class="sfm-payment-grid">
                                                    <button type="button" :class="{ active: paymentMethod === 'pix' }" @click="paymentMethod = 'pix'">
                                                        <Wallet :size="15" /> Pix
                                                    </button>
                                                    <button type="button" :class="{ active: paymentMethod === 'card' }" @click="paymentMethod = 'card'">
                                                        <CreditCard :size="15" /> Cartao
                                                    </button>
                                                    <button type="button" :class="{ active: paymentMethod === 'cash' }" @click="paymentMethod = 'cash'">
                                                        <ShoppingBag :size="15" /> Dinheiro
                                                    </button>
                                                </div>
                                            </article>

                                            <article class="sfm-checkout-card">
                                                <h3>Observacoes</h3>
                                                <textarea
                                                    v-model="checkoutNote"
                                                    rows="3"
                                                    placeholder="Ex.: sem cebola, tocar interfone..."
                                                ></textarea>
                                            </article>

                                            <article class="sfm-bill">
                                                <div><span>Subtotal</span><strong>{{ formatMoney(subtotal) }}</strong></div>
                                                <div><span>Entrega</span><strong>{{ formatMoney(deliveryFee) }}</strong></div>
                                                <div><span>Taxa plataforma</span><strong>{{ formatMoney(platformFee) }}</strong></div>
                                                <div><span>Desconto</span><strong>- {{ formatMoney(discount) }}</strong></div>
                                                <div class="total"><span>Total</span><strong>{{ formatMoney(total) }}</strong></div>
                                            </article>

                                            <button type="button" class="sfm-primary-btn" @click="finalizeOrder">Finalizar pedido</button>
                                        </section>

                                        <section v-else-if="activeScreen === 'orders'" class="sfm-screen">
                                            <div class="sfm-orders">
                                                <article v-for="order in activeOrders" :key="order.id" class="sfm-order-card">
                                                    <div class="top">
                                                        <strong>{{ order.id }}</strong>
                                                        <span class="tone" :class="statusClass(order.statusTone)">{{ order.status }}</span>
                                                    </div>
                                                    <p>{{ order.label }}</p>
                                                    <small>{{ order.meta }}</small>
                                                    <div class="bottom">
                                                        <strong>{{ formatMoney(order.total) }}</strong>
                                                        <button type="button">Ver detalhes <ChevronRight :size="14" /></button>
                                                    </div>
                                                </article>
                                            </div>
                                        </section>

                                        <section v-else-if="activeScreen === 'account'" class="sfm-screen">
                                            <article class="sfm-account-profile">
                                                <div class="avatar">{{ brand.logo }}</div>
                                                <div>
                                                    <strong>Alex Pereira</strong>
                                                    <p>alex@veshop.app</p>
                                                    <small>Plano cliente premium</small>
                                                </div>
                                            </article>

                                            <div class="sfm-account-list">
                                                <button v-for="link in accountLinks" :key="link.id" type="button" class="sfm-account-item">
                                                    <component :is="link.icon" :size="18" />
                                                    <div class="body">
                                                        <strong>{{ link.label }}</strong>
                                                        <small>{{ link.description }}</small>
                                                    </div>
                                                    <ChevronRight :size="16" />
                                                </button>
                                            </div>
                                        </section>
                                    </div>
                                </div>

                                <nav v-if="!isDesktop" class="sfm-bottom-nav">
                                    <button
                                        v-for="item in navItems"
                                        :key="`bottom-${item.key}`"
                                        type="button"
                                        :class="{ active: activeScreen === item.key }"
                                        @click="setScreen(item.key)"
                                    >
                                        <component :is="item.icon" :size="18" />
                                        <span>{{ item.label }}</span>
                                    </button>
                                </nav>

                                <button v-if="!isDesktop" type="button" class="sfm-fab" @click="setScreen('cart')">
                                    <ShoppingCart :size="18" />
                                    <span>{{ cartCount }}</span>
                                </button>
                            </div>

                            <aside v-if="isDesktop" class="sfm-desktop-right">
                                <div class="sfm-side-card">
                                    <h4>Resumo do carrinho</h4>
                                    <p>{{ cartCount }} item(ns) ativos</p>
                                    <strong>{{ formatMoney(total) }}</strong>
                                    <button type="button" class="sfm-primary-btn" @click="setScreen('cart')">Abrir carrinho</button>
                                </div>

                                <div class="sfm-side-card">
                                    <h4>Atalhos de fluxo</h4>
                                    <button type="button" @click="setScreen('login')">Voltar para login</button>
                                    <button type="button" @click="setScreen('checkout')">Ir para checkout</button>
                                    <button type="button" @click="setScreen('orders')">Abrir pedidos</button>
                                </div>

                                <div class="sfm-side-card">
                                    <h4>Checklist do layout</h4>
                                    <ul>
                                        <li>Mobile-first com navegacao app-style</li>
                                        <li>Desktop com sidebar e area principal</li>
                                        <li>Tema adaptavel para identidade da marca</li>
                                        <li>Fluxo completo: auth, catalogo, carrinho e conta</li>
                                    </ul>
                                </div>
                            </aside>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </div>
</template>
<style scoped>
.sfm-page {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 360px minmax(0, 1fr);
    gap: 20px;
    padding: 20px;
    background:
        radial-gradient(circle at 8% 8%, var(--sfm-accent-soft) 0%, transparent 40%),
        radial-gradient(circle at 92% 24%, var(--sfm-accent-fade) 0%, transparent 35%),
        #f3f4f6;
    color: var(--sfm-text);
    font-family: 'Manrope', 'Segoe UI', sans-serif;
}

.sfm-controls {
    background: #fff;
    border: 1px solid var(--sfm-border);
    border-radius: 24px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    overflow: auto;
}

.sfm-controls__header h1 { margin: 0; font-size: 1.24rem; }
.sfm-controls__header p { margin: 8px 0 0; color: var(--sfm-text-dim); line-height: 1.4; }
.sfm-group { display: flex; flex-direction: column; gap: 10px; }
.sfm-label { margin: 0; font-size: .82rem; text-transform: uppercase; letter-spacing: .06em; color: var(--sfm-text-dim); font-weight: 700; }

.sfm-segmented {
    background: #f8fafc;
    border: 1px solid var(--sfm-border);
    border-radius: 14px;
    padding: 4px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 4px;
}

.sfm-segmented.two-col { grid-template-columns: repeat(2, minmax(0, 1fr)); }

.sfm-segmented button,
.sfm-screen-btn,
.sfm-chip {
    border: 1px solid transparent;
    background: transparent;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
}

.sfm-segmented button {
    padding: 9px 10px;
    color: var(--sfm-text-dim);
    font-size: .84rem;
}

.sfm-segmented button.active {
    background: var(--sfm-accent);
    color: var(--sfm-accent-contrast);
}

.sfm-chip-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.sfm-chip {
    border-color: var(--sfm-border);
    background: #fff;
    border-radius: 999px;
    padding: 8px 10px;
    font-size: .78rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.sfm-chip.active { border-color: var(--sfm-accent); box-shadow: inset 0 0 0 1px var(--sfm-accent); }
.sfm-chip__dot { width: 10px; height: 10px; border-radius: 999px; }

.sfm-screen-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.sfm-screen-btn {
    border-color: var(--sfm-border);
    border-radius: 12px;
    background: #fff;
    padding: 8px 10px;
    font-size: .78rem;
    color: var(--sfm-text-dim);
}
.sfm-screen-btn.active { border-color: var(--sfm-accent); color: var(--sfm-accent); background: var(--sfm-accent-soft); }

.sfm-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.sfm-summary-grid article {
    border: 1px solid var(--sfm-border);
    border-radius: 14px;
    padding: 12px;
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.sfm-summary-grid .value { font-size: 1.1rem; font-weight: 800; }
.sfm-summary-grid .label { font-size: .74rem; color: var(--sfm-text-dim); line-height: 1.3; }

.sfm-preview { display: grid; place-items: center; min-width: 0; overflow: auto; }
.sfm-device {
    background: #fff;
    border: 2px solid rgba(15, 23, 42, .22);
    box-shadow: 0 30px 60px rgba(2, 6, 23, .23);
    overflow: hidden;
    max-width: 100%;
}

.sfm-status-bar {
    height: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 16px;
    font-size: .72rem;
    font-weight: 700;
    color: #0f172a;
    border-bottom: 1px solid var(--sfm-border);
}

.sfm-app { min-height: calc(100% - 30px); background: var(--sfm-surface); }
.sfm-auth { min-height: 100%; display: grid; grid-template-rows: auto 1fr; }

.sfm-auth__hero {
    padding: 28px 22px 20px;
    background: linear-gradient(160deg, var(--sfm-accent) 0%, color-mix(in srgb, var(--sfm-accent) 75%, #111827 25%) 100%);
    color: #fff;
}

.sfm-brand-badge {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    border: 2px solid rgba(255,255,255,.45);
    display: grid;
    place-items: center;
    font-weight: 800;
    margin-bottom: 16px;
}

.sfm-auth__hero h2 { margin: 0; font-size: 1.45rem; }
.sfm-auth__hero p { margin: 8px 0 0; line-height: 1.45; color: rgba(255,255,255,.86); }

.sfm-auth__panel {
    margin: -8px 18px 18px;
    border-radius: 22px;
    background: #fff;
    border: 1px solid var(--sfm-border);
    box-shadow: 0 18px 35px rgba(15, 23, 42, .08);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sfm-field { display: flex; flex-direction: column; gap: 6px; }
.sfm-field span { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--sfm-text-dim); font-weight: 700; }
.sfm-field input,
.sfm-checkout-card textarea {
    border: 1px solid var(--sfm-border);
    border-radius: 12px;
    padding: 12px;
    font-size: .92rem;
    color: var(--sfm-text);
    outline: none;
    width: 100%;
    background: #fff;
}

.sfm-check { display: inline-flex; align-items: center; gap: 8px; font-size: .86rem; color: var(--sfm-text-dim); }
.sfm-check input { accent-color: var(--sfm-accent); }

.sfm-primary-btn,
.sfm-secondary-btn {
    border: 0;
    border-radius: 14px;
    padding: 12px 14px;
    cursor: pointer;
    font-weight: 800;
    font-size: .92rem;
}
.sfm-primary-btn { background: var(--sfm-accent); color: var(--sfm-accent-contrast); }
.sfm-secondary-btn { background: #fff; border: 1px solid var(--sfm-border); color: var(--sfm-text); }
.sfm-link-btn { border: 0; background: transparent; color: var(--sfm-accent); font-weight: 700; cursor: pointer; padding: 4px 0 0; }

.sfm-store-shell { min-height: 100%; display: grid; grid-template-columns: minmax(0,1fr); }
.sfm-main { min-width: 0; position: relative; }
.sfm-header__hero {
    min-height: 225px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background-image: linear-gradient(180deg, rgba(15,23,42,.16) 0%, rgba(15,23,42,.76) 100%), var(--sfm-hero);
    background-size: cover;
    background-position: center;
}

.sfm-header__top { display: flex; justify-content: space-between; }
.sfm-header__title small { color: rgba(255,255,255,.84); font-size: .78rem; }
.sfm-header__title h2 { margin: 6px 0; color: #fff; font-size: 1.34rem; }
.sfm-header__title p { margin: 0; color: rgba(255,255,255,.86); line-height: 1.35; font-size: .88rem; }

.icon-btn {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    border: 0;
    background: rgba(255,255,255,.95);
    display: grid;
    place-items: center;
    cursor: pointer;
    color: #111827;
    position: relative;
}

.badge-dot {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--sfm-accent);
    color: var(--sfm-accent-contrast);
    font-size: .62rem;
    display: grid;
    place-items: center;
    font-weight: 700;
}

.sfm-content {
    margin-top: -20px;
    border-radius: 24px 24px 0 0;
    background: var(--sfm-surface);
    min-height: calc(100% - 205px);
    border-top: 1px solid var(--sfm-border);
}

.sfm-panel { padding: 18px; display: flex; flex-direction: column; gap: 14px; }
.sfm-screen { display: flex; flex-direction: column; gap: 14px; }
.sfm-search-inline,
.sfm-desktop-search {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid var(--sfm-border);
    background: #fff;
    border-radius: 14px;
    padding: 10px 12px;
}

.sfm-search-inline input,
.sfm-desktop-search input {
    border: 0;
    outline: none;
    width: 100%;
    font-size: .9rem;
    color: var(--sfm-text);
    background: transparent;
}

.sfm-chip-row { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 2px; }
.sfm-chip-row button {
    border: 1px solid var(--sfm-border);
    background: #fff;
    color: var(--sfm-text-dim);
    border-radius: 999px;
    padding: 8px 12px;
    font-size: .76rem;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
}
.sfm-chip-row button.active { border-color: var(--sfm-accent); background: var(--sfm-accent-soft); color: var(--sfm-accent); }
.sfm-section-title { margin: 2px 0 0; font-size: 1.04rem; }

.sfm-highlight-scroll {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(210px, 1fr);
    gap: 10px;
    overflow-x: auto;
}

.sfm-highlight-card { position: relative; min-height: 170px; border-radius: 18px; overflow: hidden; border: 1px solid var(--sfm-border); cursor: pointer; }
.sfm-highlight-card img { width: 100%; height: 100%; object-fit: cover; }
.sfm-highlight-card .tag { position: absolute; top: 10px; left: 10px; padding: 4px 8px; border-radius: 999px; background: rgba(255,255,255,.96); font-size: .67rem; font-weight: 800; }
.sfm-highlight-card .overlay { position: absolute; inset: auto 0 0; padding: 12px; display: flex; flex-direction: column; gap: 4px; color: #fff; background: linear-gradient(180deg, transparent, rgba(15,23,42,.84)); }
.sfm-highlight-card .overlay small { color: rgba(255,255,255,.86); }
.sfm-highlight-card .meta { display: flex; justify-content: space-between; font-size: .72rem; }

.sfm-list-stack,
.sfm-catalog-list,
.sfm-orders,
.sfm-account-list { display: flex; flex-direction: column; gap: 10px; }

.sfm-list-row,
.sfm-catalog-row,
.sfm-cart-row {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr) auto;
    gap: 10px;
    border: 1px solid var(--sfm-border);
    border-radius: 16px;
    background: #fff;
    padding: 9px;
}

.sfm-list-row img,
.sfm-catalog-row img,
.sfm-cart-row img { width: 76px; height: 76px; border-radius: 12px; object-fit: cover; }

.sfm-list-row .info,
.sfm-catalog-row .content,
.sfm-cart-row .content { display: flex; flex-direction: column; justify-content: center; gap: 4px; min-width: 0; }
.sfm-list-row .info p,
.sfm-catalog-row .content p,
.sfm-cart-row .content p { margin: 0; color: var(--sfm-text-dim); font-size: .79rem; line-height: 1.34; }
.sfm-list-row .actions,
.sfm-catalog-row .side { display: flex; flex-direction: column; justify-content: space-between; align-items: flex-end; }

.sfm-list-row .price { font-weight: 800; font-size: .86rem; }
.sfm-list-row .rating { display: inline-flex; align-items: center; gap: 3px; color: #f59e0b; font-size: .78rem; font-weight: 700; }
.sfm-list-row .rating small { color: var(--sfm-text-dim); font-size: .72rem; font-weight: 500; }

.sfm-catalog-row { grid-template-columns: 88px minmax(0, 1fr) auto; }
.sfm-catalog-row img { width: 88px; height: 88px; }
.sfm-catalog-row .foot { display: inline-flex; align-items: center; gap: 6px; }
.sfm-catalog-row .new { font-weight: 800; }
.sfm-catalog-row .old { color: var(--sfm-text-dim); text-decoration: line-through; font-size: .78rem; }

.sfm-mini-add { border: 1px solid var(--sfm-accent); color: var(--sfm-accent); background: #fff; border-radius: 10px; padding: 7px 10px; font-size: .75rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; }

.sfm-empty { border: 1px dashed var(--sfm-border); border-radius: 16px; padding: 16px; text-align: center; color: var(--sfm-text-dim); background: #fff; }

.sfm-detail-card { border-radius: 18px; border: 1px solid var(--sfm-border); background: #fff; overflow: hidden; }
.sfm-detail-card img { width: 100%; height: 220px; object-fit: cover; }
.sfm-detail-card .body { padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.sfm-detail-card .top { display: flex; justify-content: space-between; gap: 10px; }
.sfm-detail-card .description { margin: 0; color: var(--sfm-text-dim); line-height: 1.42; }
.sfm-points { margin: 0; padding-left: 16px; display: grid; gap: 4px; color: var(--sfm-text-dim); font-size: .82rem; }
.sfm-detail-meta { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.sfm-detail-meta span { font-size: 1.2rem; font-weight: 800; }
.sfm-detail-meta small { color: var(--sfm-text-dim); }

.sfm-stepper { display: inline-flex; align-items: center; gap: 10px; width: fit-content; border: 1px solid var(--sfm-border); border-radius: 999px; padding: 5px; }
.sfm-stepper button { width: 28px; height: 28px; border: 0; border-radius: 999px; background: var(--sfm-accent-soft); color: var(--sfm-accent); display: grid; place-items: center; cursor: pointer; }

.fill { fill: currentColor; color: var(--sfm-accent); }

.sfm-cart { display: flex; flex-direction: column; gap: 12px; }
.sfm-cart-row .qty { display: inline-flex; align-items: center; gap: 6px; }
.sfm-cart-row .qty button { width: 24px; height: 24px; border: 1px solid var(--sfm-border); border-radius: 999px; background: #fff; display: grid; place-items: center; cursor: pointer; }

.sfm-bill,
.sfm-checkout-card,
.sfm-order-card,
.sfm-account-profile,
.sfm-account-item,
.sfm-side-card {
    border: 1px solid var(--sfm-border);
    border-radius: 16px;
    background: #fff;
}

.sfm-bill { padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.sfm-bill > div { display: flex; justify-content: space-between; align-items: center; font-size: .84rem; }
.sfm-bill .total { border-top: 1px solid var(--sfm-border); padding-top: 8px; margin-top: 4px; font-size: .94rem; }

.sfm-checkout-card { padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.sfm-checkout-card h3,
.sfm-side-card h4 { margin: 0; }
.sfm-checkout-card p,
.sfm-checkout-card small,
.sfm-side-card p { margin: 0; color: var(--sfm-text-dim); }

.sfm-payment-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.sfm-payment-grid button { border: 1px solid var(--sfm-border); background: #fff; border-radius: 12px; padding: 10px 8px; font-size: .78rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 5px; cursor: pointer; }
.sfm-payment-grid button.active { border-color: var(--sfm-accent); color: var(--sfm-accent); background: var(--sfm-accent-soft); }

.sfm-order-card { padding: 12px; display: flex; flex-direction: column; gap: 7px; }
.sfm-order-card .top,
.sfm-order-card .bottom { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.sfm-order-card p,
.sfm-order-card small { margin: 0; color: var(--sfm-text-dim); }
.sfm-order-card .bottom button { border: 0; border-radius: 999px; background: var(--sfm-accent-soft); color: var(--sfm-accent); font-size: .74rem; font-weight: 700; padding: 5px 9px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; }

.tone { padding: 4px 8px; border-radius: 999px; font-size: .7rem; font-weight: 800; }
.tone-success { background: rgba(34, 197, 94, .14); color: #15803d; }
.tone-warning { background: rgba(245, 158, 11, .17); color: #b45309; }
.tone-info { background: rgba(14, 165, 233, .16); color: #0369a1; }

.sfm-account-profile { padding: 14px; display: grid; grid-template-columns: 54px minmax(0,1fr); gap: 10px; align-items: center; }
.sfm-account-profile .avatar,
.sfm-profile-pill .avatar,
.sfm-side-brand__logo { display: grid; place-items: center; background: var(--sfm-accent-soft); color: var(--sfm-accent); font-weight: 800; }
.sfm-account-profile .avatar { width: 54px; height: 54px; border-radius: 16px; }
.sfm-account-profile p,
.sfm-account-profile small { margin: 4px 0 0; color: var(--sfm-text-dim); }

.sfm-account-item {
    padding: 10px 12px;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    text-align: left;
    cursor: pointer;
}

.sfm-account-item .body { display: flex; flex-direction: column; gap: 2px; }
.sfm-account-item small { color: var(--sfm-text-dim); }

.sfm-bottom-nav {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    border-top: 1px solid var(--sfm-border);
    background: #fff;
    padding: 8px 12px 12px;
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    z-index: 20;
}

.sfm-bottom-nav button { border: 0; background: transparent; display: grid; place-items: center; gap: 4px; color: var(--sfm-text-dim); cursor: pointer; font-size: .68rem; }
.sfm-bottom-nav button.active { color: var(--sfm-accent); font-weight: 800; }

.sfm-fab {
    position: absolute;
    right: 16px;
    bottom: 72px;
    border: 0;
    border-radius: 999px;
    padding: 10px 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--sfm-accent);
    color: var(--sfm-accent-contrast);
    font-weight: 800;
    box-shadow: 0 12px 24px rgba(15, 23, 42, .22);
    cursor: pointer;
}

.sfm-store-shell.desktop { grid-template-columns: 220px minmax(0, 1fr) 300px; }
.sfm-desktop-left { border-right: 1px solid var(--sfm-border); background: #fff; padding: 16px; display: flex; flex-direction: column; gap: 16px; }
.sfm-side-brand { display: grid; grid-template-columns: 44px minmax(0, 1fr); gap: 10px; align-items: center; }
.sfm-side-brand__logo { width: 44px; height: 44px; border-radius: 12px; }
.sfm-side-brand small { color: var(--sfm-text-dim); font-size: .72rem; }

.sfm-side-nav { display: flex; flex-direction: column; gap: 6px; }
.sfm-side-nav button { border: 1px solid transparent; border-radius: 12px; background: transparent; text-align: left; padding: 10px 11px; display: inline-flex; align-items: center; gap: 8px; color: var(--sfm-text-dim); cursor: pointer; font-weight: 700; }
.sfm-side-nav button.active { border-color: var(--sfm-accent); color: var(--sfm-accent); background: var(--sfm-accent-soft); }

.sfm-logout-btn { margin-top: auto; border: 1px solid var(--sfm-border); border-radius: 12px; background: #fff; padding: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; color: var(--sfm-text-dim); font-weight: 700; }

.sfm-header.desktop { border-bottom: 1px solid var(--sfm-border); background: #fff; }
.sfm-desktop-topbar { padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.sfm-desktop-search { flex: 1; max-width: 620px; }
.sfm-desktop-actions { display: inline-flex; align-items: center; gap: 8px; }
.sfm-profile-pill { border: 1px solid var(--sfm-border); border-radius: 999px; background: #fff; padding: 3px 10px 3px 3px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
.sfm-profile-pill .avatar { width: 28px; height: 28px; border-radius: 999px; font-size: .75rem; }

.sfm-content.desktop { margin-top: 0; min-height: calc(100% - 59px); border-radius: 0; }
.sfm-panel.desktop { padding: 18px; }

.sfm-desktop-right { border-left: 1px solid var(--sfm-border); background: #f8fafc; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.sfm-side-card { padding: 13px; display: flex; flex-direction: column; gap: 8px; }
.sfm-side-card strong { font-size: 1.16rem; }
.sfm-side-card button { border: 1px solid var(--sfm-border); border-radius: 10px; background: #fff; padding: 9px 10px; font-size: .82rem; font-weight: 700; cursor: pointer; color: var(--sfm-text-dim); }
.sfm-side-card ul { margin: 0; padding-left: 16px; display: grid; gap: 6px; color: var(--sfm-text-dim); font-size: .8rem; }

@media (max-width: 1520px) {
    .sfm-page { grid-template-columns: 320px minmax(0, 1fr); }
}

@media (max-width: 1260px) {
    .sfm-page { grid-template-columns: minmax(0, 1fr); }
    .sfm-controls { max-height: 420px; }
}

@media (max-width: 920px) {
    .sfm-page { padding: 12px; gap: 12px; }
    .sfm-controls { border-radius: 16px; padding: 14px; }
    .sfm-screen-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
</style>
