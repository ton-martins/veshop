<script setup>
import { Link } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';

const props = defineProps({
    mode: { type: String, default: 'desktop' },
    collapsed: { type: Boolean, default: false },
    menuGroups: { type: Array, default: () => [] },
    collapsedLinks: { type: Array, default: () => [] },
    menuArrowIcons: { type: Object, required: true },
    resolveMenuHref: { type: Function, required: true },
    isLinkActive: { type: Function, required: true },
    isGroupActive: { type: Function, required: true },
    isGroupExpanded: { type: Function, required: true },
    toggleGroup: { type: Function, required: true },
});

const emit = defineEmits(['navigate']);

const isMobileMode = () => props.mode === 'mobile';

const notifyNavigate = () => {
    if (isMobileMode()) {
        emit('navigate');
    }
};
</script>

<template>
    <nav
        class="veshop-sidebar-nav"
        :class="[
            mode === 'mobile' ? 'veshop-sidebar-nav--mobile' : '',
            mode === 'desktop' && collapsed ? 'is-collapsed' : '',
        ]"
    >
        <template v-if="mode === 'desktop' && collapsed">
            <Link
                v-for="link in collapsedLinks"
                :key="link.key"
                :href="resolveMenuHref(link)"
                class="veshop-menu-collapsed-link"
                :class="isLinkActive(link) ? 'is-active' : ''"
                :title="link.label"
                :aria-label="link.label"
            >
                <Icon :icon="link.iconToken" class="veshop-menu-collapsed-icon" />
            </Link>
        </template>

        <template v-else>
            <div v-for="group in menuGroups" :key="group.key" class="veshop-menu-group">
                <button
                    type="button"
                    class="veshop-menu-trigger"
                    :class="{
                        'is-active': isGroupActive(group),
                        'is-expanded': isGroupExpanded(group.key),
                    }"
                    @click="toggleGroup(group.key)"
                >
                    <span class="veshop-menu-trigger-main">
                        <Icon :icon="group.iconToken" class="veshop-menu-icon" />
                        <span class="truncate">{{ group.label }}</span>
                    </span>
                    <Icon
                        :icon="
                            isGroupExpanded(group.key) ? menuArrowIcons.down : menuArrowIcons.right
                        "
                        class="veshop-menu-arrow"
                    />
                </button>
                <transition name="fade">
                    <ul v-show="isGroupExpanded(group.key)" class="veshop-submenu">
                        <li v-for="link in group.links" :key="link.key" class="veshop-submenu-item">
                            <Link
                                :href="resolveMenuHref(link)"
                                class="veshop-submenu-link"
                                :class="isLinkActive(link) ? 'is-active' : ''"
                                @click="notifyNavigate"
                            >
                                <span class="truncate">{{ link.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </transition>
            </div>
        </template>
    </nav>
</template>
