<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    Calendar,
    CalendarClock,
    CalendarDays,
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    Clock3,
    UserRound,
    Filter,
    Search,
    Plus,
    Pencil,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps({
    appointments: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    clients: {
        type: Array,
        default: () => [],
    },
    services: {
        type: Array,
        default: () => [],
    },
    orders: {
        type: Array,
        default: () => [],
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    timezone: {
        type: String,
        default: 'America/Sao_Paulo',
    },
});

const pad2 = (value) => String(value).padStart(2, '0');
const parseDateTime = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};
const parseDate = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    const parsed = new Date(`${raw}T00:00`);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};
const toIsoDate = (date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
const toDateTimeLocal = (date) => `${toIsoDate(date)}T${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
const addDays = (date, days) => {
    const next = new Date(date.getTime());
    next.setDate(next.getDate() + days);
    return next;
};
const startOfWeek = (date) => {
    const next = new Date(date.getTime());
    next.setHours(0, 0, 0, 0);
    const weekday = next.getDay();
    const diff = weekday === 0 ? -6 : 1 - weekday;
    next.setDate(next.getDate() + diff);
    return next;
};
const addMinutes = (datetimeLocal, minutes) => {
    const parsed = parseDateTime(datetimeLocal);
    if (!parsed) return '';
    parsed.setSeconds(0, 0);
    parsed.setMinutes(parsed.getMinutes() + minutes);
    return toDateTimeLocal(parsed);
};
const resolveNowAtTimezone = (timezone) => {
    try {
        const formatter = new Intl.DateTimeFormat('sv-SE', {
            timeZone: timezone || 'America/Sao_Paulo',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        });
        const parts = formatter.formatToParts(new Date());
        const values = {};
        parts.forEach((part) => {
            if (part.type !== 'literal') {
                values[part.type] = part.value;
            }
        });
        const parsed = new Date(`${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}:${values.second}`);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed;
        }
    } catch {
        // fallback below
    }

    return new Date();
};

const nowTicker = ref(Date.now());
let nowTickerTimer = null;
const hourRangeStorageKey = 'veshop_services_schedule_hour_range';
const startHour = ref('07');
const endHour = ref('20');

onMounted(() => {
    if (typeof window === 'undefined') return;

    const storedRangeRaw = window.localStorage.getItem(hourRangeStorageKey);
    if (storedRangeRaw) {
        try {
            const parsed = JSON.parse(storedRangeRaw);
            const storedStart = Number.parseInt(String(parsed?.start ?? ''), 10);
            const storedEnd = Number.parseInt(String(parsed?.end ?? ''), 10);
            if (!Number.isNaN(storedStart)) {
                startHour.value = pad2(Math.min(23, Math.max(0, storedStart)));
            }
            if (!Number.isNaN(storedEnd)) {
                endHour.value = pad2(Math.min(23, Math.max(0, storedEnd)));
            }
        } catch {
            // ignore invalid storage content
        }
    }

    nowTickerTimer = window.setInterval(() => {
        nowTicker.value = Date.now();
    }, 30000);
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined' || nowTickerTimer === null) return;
    window.clearInterval(nowTickerTimer);
    nowTickerTimer = null;
});

watch(
    [startHour, endHour],
    ([nextStartHour, nextEndHour]) => {
        const parsedStart = Number.parseInt(String(nextStartHour ?? ''), 10);
        const parsedEnd = Number.parseInt(String(nextEndHour ?? ''), 10);
        const safeStart = Number.isNaN(parsedStart) ? 7 : Math.min(23, Math.max(0, parsedStart));
        const safeEnd = Number.isNaN(parsedEnd) ? 20 : Math.min(23, Math.max(0, parsedEnd));

        if (safeEnd < safeStart) {
            endHour.value = pad2(safeStart);
            return;
        }

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(hourRangeStorageKey, JSON.stringify({ start: safeStart, end: safeEnd }));
        }
    },
    { immediate: true },
);

const nowAtTimezone = computed(() => {
    void nowTicker.value;
    return resolveNowAtTimezone(props.timezone);
});

const minimumStartDateTime = computed(() => toDateTimeLocal(nowAtTimezone.value));
const minimumStartDate = computed(() => toIsoDate(nowAtTimezone.value));

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
});

const layout = ref(props.filters?.layout ?? 'month');
const referenceDate = ref(props.filters?.reference_date ?? toIsoDate(new Date()));

watch(
    () => props.filters,
    (next) => {
        filterForm.search = next?.search ?? '';
        filterForm.status = next?.status ?? '';
        layout.value = next?.layout ?? 'month';
        referenceDate.value = next?.reference_date ?? toIsoDate(new Date());
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.services.schedule'),
        {
            search: filterForm.search || undefined,
            status: filterForm.status || undefined,
            layout: layout.value,
            reference_date: referenceDate.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFilters();
};

const rows = computed(() => props.appointments?.data ?? []);
const hasServices = computed(() => Array.isArray(props.services) && props.services.length > 0);
const viewOptions = [
    { value: 'day', label: 'Dia', icon: CalendarDays },
    { value: 'week', label: 'Semana', icon: CalendarRange },
    { value: 'month', label: 'Mês', icon: Calendar },
];

const calendarBaseDate = computed(() => parseDate(referenceDate.value) ?? new Date());
const layoutLabel = computed(() => viewOptions.find((item) => item.value === layout.value)?.label ?? layout.value);

const calendarTitle = computed(() => {
    const base = calendarBaseDate.value;

    if (layout.value === 'day') {
        return new Intl.DateTimeFormat('pt-BR', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            timeZone: props.timezone,
        }).format(base);
    }

    if (layout.value === 'week') {
        const start = startOfWeek(base);
        const end = addDays(start, 6);
        const startLabel = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', timeZone: props.timezone }).format(start);
        const endLabel = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric', timeZone: props.timezone }).format(end);
        return `${startLabel} - ${endLabel}`;
    }

    return new Intl.DateTimeFormat('pt-BR', {
        month: 'long',
        year: 'numeric',
        timeZone: props.timezone,
    }).format(base);
});

const calendarEvents = computed(() => {
    return rows.value
        .map((item) => {
            const start = parseDateTime(item.starts_at);
            const end = parseDateTime(item.ends_at);
            if (!start || !end) {
                return null;
            }

            return {
                ...item,
                start,
                end,
                date: toIsoDate(start),
                start_label: `${pad2(start.getHours())}:${pad2(start.getMinutes())}`,
                end_label: `${pad2(end.getHours())}:${pad2(end.getMinutes())}`,
            };
        })
        .filter(Boolean)
        .sort((a, b) => a.start.getTime() - b.start.getTime());
});

const eventsByDate = computed(() => {
    const buckets = new Map();
    calendarEvents.value.forEach((item) => {
        const current = buckets.get(item.date) ?? [];
        current.push(item);
        buckets.set(item.date, current);
    });
    return buckets;
});

const weekDays = computed(() => {
    const start = startOfWeek(calendarBaseDate.value);
    return Array.from({ length: 7 }, (_, index) => addDays(start, index));
});

const monthGridDays = computed(() => {
    const base = new Date(calendarBaseDate.value.getTime());
    base.setDate(1);
    const start = startOfWeek(base);
    return Array.from({ length: 42 }, (_, index) => addDays(start, index));
});

const hourOptions = computed(() =>
    Array.from({ length: 24 }, (_, hour) => ({
        value: pad2(hour),
        label: `${pad2(hour)}:00`,
    })),
);
const normalizedStartHour = computed(() => {
    const parsed = Number.parseInt(String(startHour.value ?? '07'), 10);
    if (Number.isNaN(parsed)) return 7;
    return Math.min(23, Math.max(0, parsed));
});
const normalizedEndHour = computed(() => {
    const parsed = Number.parseInt(String(endHour.value ?? '20'), 10);
    if (Number.isNaN(parsed)) return 20;
    return Math.min(23, Math.max(0, parsed));
});
const daySlots = computed(() => {
    const start = normalizedStartHour.value;
    const end = Math.max(start, normalizedEndHour.value);
    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
});
const isCurrentMonthDate = (date) =>
    date.getFullYear() === calendarBaseDate.value.getFullYear()
    && date.getMonth() === calendarBaseDate.value.getMonth();
const isTodayDate = (date) => toIsoDate(date) === minimumStartDate.value;
const isPastDate = (date) => {
    const parsed = typeof date === 'string' ? parseDate(date) : parseDate(toIsoDate(date));
    const today = parseDate(minimumStartDate.value);
    if (!parsed || !today) return false;
    return parsed.getTime() < today.getTime();
};
const canCreateAtDate = (date) => hasServices.value && !isPastDate(date);
const canCreateAtHour = (date, hour) => {
    if (!canCreateAtDate(date)) return false;

    const isoDate = typeof date === 'string' ? date : toIsoDate(date);
    const slot = parseDateTime(`${isoDate}T${pad2(hour)}:00`);
    const minimum = parseDateTime(minimumStartDateTime.value);

    if (!slot || !minimum) return false;

    return slot.getTime() >= minimum.getTime();
};

const eventsForDate = (date) => {
    const key = typeof date === 'string' ? date : toIsoDate(date);
    return eventsByDate.value.get(key) ?? [];
};
const eventsForSlot = (date, hour = null) => {
    const events = eventsForDate(date);
    if (hour === null) return events;

    return events.filter((item) => item.start.getHours() === hour);
};

const shiftPeriod = (direction) => {
    const base = calendarBaseDate.value;

    if (layout.value === 'day') {
        referenceDate.value = toIsoDate(addDays(base, direction));
        applyFilters();
        return;
    }

    if (layout.value === 'week') {
        referenceDate.value = toIsoDate(addDays(base, direction * 7));
        applyFilters();
        return;
    }

    const next = new Date(base.getTime());
    next.setMonth(next.getMonth() + direction);
    referenceDate.value = toIsoDate(next);
    applyFilters();
};

const setLayout = (value) => {
    if (layout.value === value) return;
    layout.value = value;
    applyFilters();
};

const goToday = () => {
    referenceDate.value = minimumStartDate.value;
    applyFilters();
};

const statsCards = computed(() => [
    { key: 'today', label: 'Visitas hoje', value: String(props.stats?.today ?? 0), icon: CalendarClock, tone: 'text-slate-700' },
    { key: 'next', label: 'Próximas 24h', value: String(props.stats?.next_24h ?? 0), icon: Clock3, tone: 'text-slate-700' },
    { key: 'teams', label: 'Responsáveis ativos', value: String(props.stats?.teams ?? 0), icon: UserRound, tone: 'text-slate-700' },
]);

const clientOptions = computed(() => ([
    { value: '', label: 'Sem cliente' },
    ...(props.clients ?? []).map((client) => ({ value: client.id, label: client.name })),
]));

const serviceOptions = computed(() => ([
    ...(props.services ?? []).map((service) => ({ value: service.id, label: service.name })),
]));

const orderOptions = computed(() => ([
    { value: '', label: 'Sem OS vinculada' },
    ...(props.orders ?? []).map((order) => ({ value: order.id, label: order.label })),
]));

const statusFilterOptions = computed(() => ([
    { value: '', label: 'Todos os status' },
    ...(props.statusOptions ?? []),
]));
const selectedSlotDate = ref('');
const selectedSlotHour = ref(null);
const showSlotModal = ref(false);
const selectedSlotDateLabel = computed(() => {
    const parsed = parseDate(selectedSlotDate.value);
    if (!parsed) return '';

    return new Intl.DateTimeFormat('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(parsed);
});
const selectedSlotTitle = computed(() => {
    if (selectedSlotHour.value === null) {
        return `Compromissos de ${selectedSlotDateLabel.value}`;
    }

    return `Compromissos de ${selectedSlotDateLabel.value} às ${pad2(selectedSlotHour.value)}:00`;
});
const selectedSlotEvents = computed(() =>
    eventsForSlot(selectedSlotDate.value, selectedSlotHour.value),
);
const canCreateForSelectedSlot = computed(() => {
    if (selectedSlotHour.value === null) {
        return canCreateAtDate(selectedSlotDate.value);
    }

    return canCreateAtHour(selectedSlotDate.value, selectedSlotHour.value);
});

const openSlotModal = (date, hour = null) => {
    const safeDate = typeof date === 'string' ? date : toIsoDate(date);
    selectedSlotDate.value = safeDate;
    selectedSlotHour.value = Number.isInteger(hour) ? hour : null;
    showSlotModal.value = true;
};
const closeSlotModal = () => {
    showSlotModal.value = false;
    selectedSlotDate.value = '';
    selectedSlotHour.value = null;
};
const createFromSelectedSlot = () => {
    if (!canCreateForSelectedSlot.value) return;

    const hour = selectedSlotHour.value === null
        ? Math.max(normalizedStartHour.value, nowAtTimezone.value.getHours())
        : selectedSlotHour.value;

    closeSlotModal();
    openCreateAt(selectedSlotDate.value, hour);
};

const formDefaults = () => ({
    title: '',
    service_order_id: '',
    client_id: '',
    service_catalog_id: props.services?.[0]?.id ?? '',
    starts_at: '',
    ends_at: '',
    status: props.statusOptions?.[0]?.value ?? 'scheduled',
    location: '',
    notes: '',
});

const form = useForm(formDefaults());
const showModal = ref(false);
const editingAppointment = ref(null);
const showDeleteModal = ref(false);
const appointmentToDelete = ref(null);
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingAppointment.value?.id));
const clampToMinimumStart = (datetimeLocal) => {
    const candidate = parseDateTime(datetimeLocal);
    const minimum = parseDateTime(minimumStartDateTime.value);
    if (!candidate || !minimum) {
        return minimumStartDateTime.value;
    }

    candidate.setSeconds(0, 0);
    return candidate.getTime() < minimum.getTime()
        ? minimumStartDateTime.value
        : toDateTimeLocal(candidate);
};
const minimumEndDateTime = computed(() => {
    const startsAt = parseDateTime(form.starts_at);
    if (!startsAt) {
        return addMinutes(minimumStartDateTime.value, 1);
    }

    if (isEditing.value) {
        return addMinutes(toDateTimeLocal(startsAt), 1);
    }

    return addMinutes(clampToMinimumStart(toDateTimeLocal(startsAt)), 1);
});

watch(
    () => form.starts_at,
    (nextStartsAt) => {
        if (!nextStartsAt) return;

        if (!isEditing.value) {
            const normalized = clampToMinimumStart(nextStartsAt);
            if (normalized !== nextStartsAt) {
                form.starts_at = normalized;
                return;
            }
        }

        const currentEndsAt = parseDateTime(form.ends_at);
        const minimumEndsAt = parseDateTime(minimumEndDateTime.value);

        if (!currentEndsAt || (minimumEndsAt && currentEndsAt.getTime() < minimumEndsAt.getTime())) {
            form.ends_at = minimumEndDateTime.value;
        }
    },
);

const openCreate = () => {
    openCreateAt(referenceDate.value);
};

const openCreateAt = (date, hour = 9) => {
    editingAppointment.value = null;
    const safeDate = String(date || referenceDate.value || minimumStartDate.value);
    const startsAt = clampToMinimumStart(`${safeDate}T${pad2(hour)}:00`);
    const endsAt = addMinutes(startsAt, 60);
    form.defaults({
        ...formDefaults(),
        starts_at: startsAt,
        ends_at: endsAt,
    });
    form.reset();
    form.starts_at = startsAt;
    form.ends_at = endsAt;
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (appointment) => {
    editingAppointment.value = appointment;
    form.title = appointment.title ?? '';
    form.service_order_id = appointment.service_order_id ?? '';
    form.client_id = appointment.client_id ?? '';
    form.service_catalog_id = appointment.service_catalog_id ?? '';
    form.starts_at = appointment.starts_at ?? '';
    form.ends_at = appointment.ends_at ?? '';
    form.status = appointment.status ?? (props.statusOptions?.[0]?.value ?? 'scheduled');
    form.location = appointment.location ?? '';
    form.notes = appointment.notes ?? '';
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingAppointment.value = null;
    form.clearErrors();
    form.defaults(formDefaults());
    form.reset();
};

const submitAppointment = () => {
    form.clearErrors('starts_at', 'ends_at');

    if (!isEditing.value) {
        form.starts_at = clampToMinimumStart(form.starts_at || minimumStartDateTime.value);
        const startsAt = parseDateTime(form.starts_at);
        const minimum = parseDateTime(minimumStartDateTime.value);
        if (!startsAt || !minimum || startsAt.getTime() < minimum.getTime()) {
            form.setError('starts_at', 'Informe uma data e hora atual ou futura para o agendamento.');
            return;
        }
    }

    const endsAt = parseDateTime(form.ends_at);
    const minimumEnd = parseDateTime(minimumEndDateTime.value);
    if (!endsAt || !minimumEnd || endsAt.getTime() < minimumEnd.getTime()) {
        form.setError('ends_at', 'O horário de término deve ser posterior ao início.');
        return;
    }

    if (isEditing.value) {
        form.put(route('admin.services.schedule.update', editingAppointment.value.id), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    form.post(route('admin.services.schedule.store'), {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const openDeleteModal = (appointment) => {
    appointmentToDelete.value = appointment;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    appointmentToDelete.value = null;
};

const destroyAppointment = () => {
    if (!appointmentToDelete.value?.id) return;

    deleteForm.delete(route('admin.services.schedule.destroy', appointmentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const statusLabel = (value) => {
    const option = (props.statusOptions ?? []).find((item) => item.value === value);
    return option?.label ?? value;
};
</script>

<template>
    <Head title="Agenda de Serviços" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Agenda de Serviços" :show-table-view-toggle="false">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="stat in statsCards" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                        </div>
                        <span class="veshop-stat-icon inline-flex h-9 w-9 items-center justify-center rounded-xl" :class="stat.tone">
                            <component :is="stat.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </article>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Buscar por título, cliente, OS ou responsável"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="veshop-toolbar-actions lg:justify-end">
                        <UiSelect v-model="filterForm.status" :options="statusFilterOptions" button-class="w-full sm:w-auto" @change="applyFilters" />
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto" @click="clearFilters">
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button
                            type="button"
                            class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                            :disabled="!hasServices"
                            @click="openCreate"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Novo compromisso
                        </button>
                    </div>
                </div>

                <div v-if="!hasServices" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-700">
                    Cadastre pelo menos um serviço no catálogo para criar novos compromissos.
                </div>

                <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                            <button
                                v-for="option in viewOptions"
                                :key="`layout-${option.value}`"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                :class="layout === option.value ? 'text-white' : 'text-slate-600 hover:bg-white'"
                                :style="layout === option.value ? { background: 'var(--veshop-accent)' } : null"
                                @click="setLayout(option.value)"
                            >
                                <component :is="option.icon" class="h-3.5 w-3.5" />
                                {{ option.label }}
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Janela diária</p>
                            <UiSelect v-model="startHour" :options="hourOptions" button-class="w-[96px] text-xs" />
                            <span class="text-xs font-semibold text-slate-500">até</span>
                            <UiSelect v-model="endHour" :options="hourOptions" button-class="w-[96px] text-xs" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50" @click="shiftPeriod(-1)">
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50" @click="shiftPeriod(1)">
                            <ChevronRight class="h-4 w-4" />
                        </button>
                        <input v-model="referenceDate" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700" @change="applyFilters">
                        <button type="button" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="goToday">
                            Hoje
                        </button>
                    </div>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Visão atual</p>
                    <p class="text-sm font-semibold text-slate-800">{{ layoutLabel }}: {{ calendarTitle }}</p>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3">
                    <template v-if="layout === 'month'">
                        <div class="grid grid-cols-7 gap-2 border-b border-slate-100 pb-2 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <span>Seg</span>
                            <span>Ter</span>
                            <span>Qua</span>
                            <span>Qui</span>
                            <span>Sex</span>
                            <span>Sáb</span>
                            <span>Dom</span>
                        </div>
                        <div class="mt-2 grid grid-cols-7 gap-2">
                            <article
                                v-for="day in monthGridDays"
                                :key="`month-day-${toIsoDate(day)}`"
                                class="min-h-[110px] rounded-lg border border-slate-200 p-2"
                                :class="[
                                    isCurrentMonthDate(day) ? 'bg-white' : 'bg-slate-50/70',
                                    isPastDate(day) ? 'opacity-70' : '',
                                    isTodayDate(day) ? 'ring-1 ring-slate-900/20' : '',
                                ]"
                            >
                                <div class="flex items-center justify-between gap-1">
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-slate-700 transition hover:text-slate-900"
                                        @click="openSlotModal(day)"
                                    >
                                        {{ day.getDate() }}
                                        <span v-if="isTodayDate(day)" class="ml-1 rounded-full bg-slate-900 px-1.5 py-0.5 text-[10px] text-white">Hoje</span>
                                    </button>
                                    <button type="button" class="inline-flex h-5 w-5 items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40" :disabled="!canCreateAtDate(day)" @click="openCreateAt(toIsoDate(day))">
                                        <Plus class="h-3 w-3" />
                                    </button>
                                </div>
                                <div class="mt-1 space-y-1">
                                    <button
                                        v-for="event in eventsForDate(day).slice(0, 3)"
                                        :key="`event-${event.id}`"
                                        type="button"
                                        class="block w-full truncate rounded-md px-1.5 py-1 text-left text-[11px] font-medium text-slate-700 hover:bg-slate-100"
                                        @click="openEdit(event)"
                                    >
                                        <span class="font-semibold">{{ event.start_label }}</span>
                                        <span class="ml-1">{{ event.title }}</span>
                                    </button>
                                    <p v-if="eventsForDate(day).length > 3" class="text-[10px] font-semibold text-slate-500">
                                        +{{ eventsForDate(day).length - 3 }} compromisso(s)
                                    </p>
                                </div>
                            </article>
                        </div>
                    </template>

                    <template v-else-if="layout === 'week'">
                        <div class="grid gap-2 md:grid-cols-7">
                            <article
                                v-for="day in weekDays"
                                :key="`week-day-${toIsoDate(day)}`"
                                class="min-h-[170px] rounded-xl border border-slate-200 p-2"
                                :class="[
                                    isTodayDate(day) ? 'bg-white ring-1 ring-slate-900/20' : 'bg-slate-50',
                                    isPastDate(day) ? 'opacity-70' : '',
                                ]"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-slate-700 transition hover:text-slate-900"
                                        @click="openSlotModal(day)"
                                    >
                                        {{ new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' }).format(day) }}
                                    </button>
                                    <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40" :disabled="!canCreateAtDate(day)" @click="openCreateAt(toIsoDate(day))">
                                        <Plus class="h-3 w-3" />
                                    </button>
                                </div>
                                <div class="mt-2 space-y-1">
                                    <button
                                        v-for="event in eventsForDate(day)"
                                        :key="`week-event-${event.id}`"
                                        type="button"
                                        class="block w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-left text-[11px] text-slate-700 hover:bg-slate-50"
                                        @click="openEdit(event)"
                                    >
                                        <p class="font-semibold">{{ event.start_label }} - {{ event.end_label }}</p>
                                        <p class="truncate">{{ event.title }}</p>
                                    </button>
                                    <p v-if="!eventsForDate(day).length" class="text-[11px] text-slate-500">Sem compromissos</p>
                                </div>
                            </article>
                        </div>
                    </template>

                    <template v-else>
                        <div class="space-y-2">
                            <article v-for="hour in daySlots" :key="`day-slot-${hour}`" class="rounded-xl border border-slate-200 bg-slate-50 p-2">
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                                        @click="openSlotModal(referenceDate, hour)"
                                    >
                                        {{ pad2(hour) }}:00
                                    </button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40" :disabled="!canCreateAtHour(referenceDate, hour)" @click="openCreateAt(referenceDate, hour)">
                                        <Plus class="h-3 w-3" />
                                        Novo
                                    </button>
                                </div>
                                <div class="mt-2 space-y-1">
                                    <button
                                        v-for="event in eventsForDate(referenceDate).filter((item) => item.start.getHours() === hour)"
                                        :key="`day-event-${event.id}`"
                                        type="button"
                                        class="block w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-left text-xs text-slate-700 hover:bg-slate-50"
                                        @click="openEdit(event)"
                                    >
                                        <p class="font-semibold">{{ event.start_label }} - {{ event.end_label }} | {{ event.title }}</p>
                                        <p class="truncate text-[11px] text-slate-500">{{ event.client_name }}</p>
                                    </button>
                                    <p v-if="!eventsForDate(referenceDate).filter((item) => item.start.getHours() === hour).length" class="text-[11px] text-slate-500">
                                        Sem compromissos neste horário.
                                    </p>
                                </div>
                            </article>
                        </div>
                    </template>
                </div>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-slate-800">Compromissos do período</h4>
                        <p class="text-xs text-slate-500">{{ rows.length }} registro(s)</p>
                    </div>

                    <div v-if="!rows.length" class="mt-2 rounded-lg border border-dashed border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-500">
                        Nenhum compromisso encontrado para os filtros selecionados.
                    </div>

                    <div v-else class="mt-3 grid gap-2">
                        <article v-for="appointment in rows" :key="`appointment-card-${appointment.id}`" class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ appointment.title }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ appointment.time_label }} - {{ appointment.client_name }} - {{ appointment.service_name || 'Sem serviço' }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ appointment.technician || 'Sem responsável' }} - {{ appointment.service_order_code || 'Sem OS' }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                        {{ statusLabel(appointment.status) }}
                                    </span>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="openEdit(appointment)">
                                        <Pencil class="h-3.5 w-3.5" />
                                        Editar
                                    </button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="openDeleteModal(appointment)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

            </section>
        </section>

        <Modal :show="showSlotModal" max-width="3xl" @close="closeSlotModal">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ selectedSlotTitle }}</h3>
                        <p class="text-sm text-slate-500">
                            Visualize os compromissos do período e abra o cadastro rapidamente.
                        </p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeSlotModal">
                        Fechar
                    </button>
                </div>

                <div v-if="selectedSlotEvents.length" class="space-y-2">
                    <article
                        v-for="event in selectedSlotEvents"
                        :key="`slot-event-${event.id}`"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ event.title }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ event.start_label }} - {{ event.end_label }} | {{ event.client_name || 'Sem cliente' }}
                                </p>
                                <p class="text-[11px] text-slate-500">{{ event.service_name || 'Sem serviço' }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="closeSlotModal(); openEdit(event)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                Editar
                            </button>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                >
                    Nenhum compromisso encontrado para este período.
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeSlotModal"
                    >
                        Fechar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!hasServices || !canCreateForSelectedSlot"
                        @click="createFromSelectedSlot"
                    >
                        Novo compromisso
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="showModal" max-width="5xl" @close="closeModal">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ isEditing ? 'Editar compromisso' : 'Novo compromisso' }}</h3>
                        <p class="text-sm text-slate-500">Planejamento operacional da agenda de serviços.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Fechar
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Título</label>
                        <input v-model="form.title" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Ex.: Reunião de fechamento">
                        <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">OS vinculada</label>
                        <UiSelect v-model="form.service_order_id" :options="orderOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.service_order_id" class="mt-1 text-xs text-rose-600">{{ form.errors.service_order_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</label>
                        <UiSelect v-model="form.client_id" :options="clientOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.client_id" class="mt-1 text-xs text-rose-600">{{ form.errors.client_id }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serviço *</label>
                        <UiSelect v-model="form.service_catalog_id" :options="serviceOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.service_catalog_id" class="mt-1 text-xs text-rose-600">{{ form.errors.service_catalog_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <UiSelect v-model="form.status" :options="props.statusOptions" button-class="mt-1 w-full text-sm" />
                        <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">{{ form.errors.status }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Início</label>
                        <input
                            v-model="form.starts_at"
                            type="datetime-local"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :min="isEditing ? null : minimumStartDateTime"
                        >
                        <p v-if="!isEditing" class="mt-1 text-[11px] text-slate-500">
                            Disponível a partir de {{ minimumStartDateTime.replace('T', ' ') }}.
                        </p>
                        <p v-if="form.errors.starts_at" class="mt-1 text-xs text-rose-600">{{ form.errors.starts_at }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fim</label>
                        <input
                            v-model="form.ends_at"
                            type="datetime-local"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :min="minimumEndDateTime"
                        >
                        <p v-if="form.errors.ends_at" class="mt-1 text-xs text-rose-600">{{ form.errors.ends_at }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Local</label>
                        <input v-model="form.location" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Endereço ou sala">
                        <p v-if="form.errors.location" class="mt-1 text-xs text-rose-600">{{ form.errors.location }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</label>
                        <textarea v-model="form.notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Instruções adicionais" />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeModal">
                        Cancelar
                    </button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60" :disabled="form.processing" @click="submitAppointment">
                        {{ form.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir compromisso"
            message="Tem certeza que deseja excluir este compromisso?"
            :item-label="appointmentToDelete?.title ? `Compromisso: ${appointmentToDelete.title}` : ''"
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="destroyAppointment"
        />
    </AuthenticatedLayout>
</template>
