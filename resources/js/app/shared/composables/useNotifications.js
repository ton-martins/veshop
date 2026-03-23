import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useNotifications() {
    const page = usePage();

    const notifications = computed(() => page.props.notifications ?? { unread_count: 0, items: [] });
    const unreadCount = computed(() => Number(notifications.value.unread_count ?? 0));
    const items = computed(() => notifications.value.items ?? []);

    return {
        notifications,
        unreadCount,
        items,
    };
}
