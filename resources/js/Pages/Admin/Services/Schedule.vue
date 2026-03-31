<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { formatCpfCnpjBR, formatPhoneBR } from '@/utils/br';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
    X,
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
    paymentStatusOptions: {
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
const toIsoDate = (date) =>
    `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
const toDateTimeLocal = (date) =>
    `${toIsoDate(date)}T${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
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
const APPOINTMENT_STEP_MINUTES = 15;
const APPOINTMENT_STEP_SECONDS = APPOINTMENT_STEP_MINUTES * 60;
const DAY_TIMELINE_HOUR_HEIGHT = 96;
const DAY_TIMELINE_QUARTER_HEIGHT = DAY_TIMELINE_HOUR_HEIGHT / 4;
const snapDateTimeToStep = (
    datetimeLocal,
    stepMinutes = APPOINTMENT_STEP_MINUTES,
    mode = 'ceil',
) => {
    const parsed = parseDateTime(datetimeLocal);
    if (!parsed) return '';

    const safeStep = Math.max(
        1,
        Number.parseInt(String(stepMinutes ?? APPOINTMENT_STEP_MINUTES), 10) ||
            APPOINTMENT_STEP_MINUTES,
    );

    parsed.setSeconds(0, 0);
    const base = new Date(parsed.getTime());
    base.setHours(0, 0, 0, 0);

    const totalMinutes = parsed.getHours() * 60 + parsed.getMinutes();
    const snappedMinutes =
        mode === 'floor'
            ? Math.floor(totalMinutes / safeStep) * safeStep
            : mode === 'nearest'
              ? Math.round(totalMinutes / safeStep) * safeStep
              : Math.ceil(totalMinutes / safeStep) * safeStep;

    base.setMinutes(snappedMinutes);
    return toDateTimeLocal(base);
};
const formatHourMinuteLabel = (datetimeLocal) => {
    const parsed = parseDateTime(datetimeLocal);
    if (!parsed) return '';

    return `${pad2(parsed.getHours())}:${pad2(parsed.getMinutes())}`;
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
        const parsed = new Date(
            `${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}:${values.second}`,
        );
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
            window.localStorage.setItem(
                hourRangeStorageKey,
                JSON.stringify({ start: safeStart, end: safeEnd }),
            );
        }
    },
    { immediate: true },
);

const nowAtTimezone = computed(() => {
    void nowTicker.value;
    return resolveNowAtTimezone(props.timezone);
});

const minimumStartDateTime = computed(() => toDateTimeLocal(nowAtTimezone.value));
const minimumBookableDateTime = computed(() =>
    snapDateTimeToStep(minimumStartDateTime.value, APPOINTMENT_STEP_MINUTES, 'ceil'),
);
const minimumStartDate = computed(() => toIsoDate(nowAtTimezone.value));

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
});

const layout = ref(props.filters?.layout ?? 'day');
const referenceDate = ref(props.filters?.reference_date ?? toIsoDate(new Date()));

watch(
    () => props.filters,
    (next) => {
        filterForm.search = next?.search ?? '';
        filterForm.status = next?.status ?? '';
        layout.value = next?.layout ?? 'day';
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
const layoutLabel = computed(
    () => viewOptions.find((item) => item.value === layout.value)?.label ?? layout.value,
);

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
        const startLabel = new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: 'short',
            timeZone: props.timezone,
        }).format(start);
        const endLabel = new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            timeZone: props.timezone,
        }).format(end);
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
    date.getFullYear() === calendarBaseDate.value.getFullYear() &&
    date.getMonth() === calendarBaseDate.value.getMonth();
const isTodayDate = (date) => toIsoDate(date) === minimumStartDate.value;
const isPastDate = (date) => {
    const parsed = typeof date === 'string' ? parseDate(date) : parseDate(toIsoDate(date));
    const today = parseDate(minimumStartDate.value);
    if (!parsed || !today) return false;
    return parsed.getTime() < today.getTime();
};
const canCreateAtDate = (date) => !isPastDate(date);
const canCreateAtHour = (date, hour) => {
    if (!canCreateAtDate(date)) return false;

    const isoDate = typeof date === 'string' ? date : toIsoDate(date);
    const slot = parseDateTime(`${isoDate}T${pad2(hour)}:00`);
    const minimum = parseDateTime(minimumBookableDateTime.value);

    if (!slot) return false;
    if (isoDate !== minimumStartDate.value || !minimum) return true;

    const slotEnd = new Date(slot.getTime());
    slotEnd.setMinutes(slotEnd.getMinutes() + 60);

    return slotEnd.getTime() > minimum.getTime();
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
const isReferenceDateToday = computed(() => referenceDate.value === minimumStartDate.value);
const dayTimelineStartMinutes = computed(() => normalizedStartHour.value * 60);
const dayTimelineEndMinutes = computed(() => (normalizedEndHour.value + 1) * 60);
const dayTimelineHeight = computed(
    () =>
        ((dayTimelineEndMinutes.value - dayTimelineStartMinutes.value) / 60) *
        DAY_TIMELINE_HOUR_HEIGHT,
);
const currentTimeIndicatorOffset = computed(() => {
    if (!isReferenceDateToday.value) return null;

    const currentMinutes = nowAtTimezone.value.getHours() * 60 + nowAtTimezone.value.getMinutes();
    if (
        currentMinutes < dayTimelineStartMinutes.value ||
        currentMinutes > dayTimelineEndMinutes.value
    ) {
        return null;
    }

    return ((currentMinutes - dayTimelineStartMinutes.value) / 60) * DAY_TIMELINE_HOUR_HEIGHT;
});
const pastTimeOverlayHeight = computed(() => {
    if (!isReferenceDateToday.value) return 0;

    const minimum = parseDateTime(minimumBookableDateTime.value);
    if (!minimum) return 0;

    const minimumMinutes = minimum.getHours() * 60 + minimum.getMinutes();
    const clampedMinutes = Math.max(
        dayTimelineStartMinutes.value,
        Math.min(dayTimelineEndMinutes.value, minimumMinutes),
    );

    return ((clampedMinutes - dayTimelineStartMinutes.value) / 60) * DAY_TIMELINE_HOUR_HEIGHT;
});
const nextAvailableCreateDateTime = computed(() => {
    if (!canCreateAtDate(referenceDate.value)) return '';

    if (referenceDate.value === minimumStartDate.value) {
        return minimumBookableDateTime.value;
    }

    return clampToMinimumStart(`${referenceDate.value}T${pad2(normalizedStartHour.value)}:00`);
});
const nextAvailableCreateLabel = computed(() =>
    formatHourMinuteLabel(nextAvailableCreateDateTime.value),
);
const dayTimelineEvents = computed(() => {
    const rangeStart = dayTimelineStartMinutes.value;
    const rangeEnd = dayTimelineEndMinutes.value;

    const items = eventsForDate(referenceDate.value)
        .map((item) => {
            const startMinutes = item.start.getHours() * 60 + item.start.getMinutes();
            const endMinutes = item.end.getHours() * 60 + item.end.getMinutes();
            const clippedStart = Math.max(rangeStart, startMinutes);
            const clippedEnd = Math.min(rangeEnd, Math.max(clippedStart + 1, endMinutes));

            if (clippedEnd <= rangeStart || clippedStart >= rangeEnd) {
                return null;
            }

            return {
                ...item,
                timelineStartMinutes: clippedStart,
                timelineEndMinutes: clippedEnd,
            };
        })
        .filter(Boolean)
        .sort((a, b) => a.timelineStartMinutes - b.timelineStartMinutes);

    const groups = [];
    let activeGroup = [];
    let activeGroupEnd = -Infinity;

    items.forEach((item) => {
        if (!activeGroup.length || item.timelineStartMinutes < activeGroupEnd) {
            activeGroup.push(item);
            activeGroupEnd = Math.max(activeGroupEnd, item.timelineEndMinutes);
            return;
        }

        groups.push(activeGroup);
        activeGroup = [item];
        activeGroupEnd = item.timelineEndMinutes;
    });

    if (activeGroup.length) {
        groups.push(activeGroup);
    }

    return groups.flatMap((group) => {
        const columns = [];

        group.forEach((item) => {
            let columnIndex = columns.findIndex(
                (endMinute) => endMinute <= item.timelineStartMinutes,
            );

            if (columnIndex === -1) {
                columnIndex = columns.length;
                columns.push(item.timelineEndMinutes);
            } else {
                columns[columnIndex] = item.timelineEndMinutes;
            }

            item.columnIndex = columnIndex;
        });

        const columnCount = Math.max(1, columns.length);

        return group.map((item) => ({
            ...item,
            columnCount,
            topPx: ((item.timelineStartMinutes - rangeStart) / 60) * DAY_TIMELINE_HOUR_HEIGHT,
            heightPx: Math.max(
                DAY_TIMELINE_QUARTER_HEIGHT,
                ((item.timelineEndMinutes - item.timelineStartMinutes) / 60) *
                    DAY_TIMELINE_HOUR_HEIGHT,
            ),
            leftPercent: (100 / columnCount) * item.columnIndex,
            widthPercent: 100 / columnCount,
        }));
    });
});
const dayTimelineEventStyle = (item) => ({
    top: `${item.topPx}px`,
    left: `calc(${item.leftPercent}% + 0.375rem)`,
    width: `calc(${item.widthPercent}% - 0.5rem)`,
    height: `${item.heightPx}px`,
});
const handleDayTimelineClick = (event) => {
    if (!canCreateAtDate(referenceDate.value)) return;

    const bounds = event.currentTarget.getBoundingClientRect();
    const offsetY = Math.max(0, Math.min(bounds.height, event.clientY - bounds.top));
    const quarterIndex = Math.floor(offsetY / DAY_TIMELINE_QUARTER_HEIGHT);
    const totalMinutes = dayTimelineStartMinutes.value + quarterIndex * APPOINTMENT_STEP_MINUTES;
    const clampedMinutes = Math.max(
        dayTimelineStartMinutes.value,
        Math.min(dayTimelineEndMinutes.value - APPOINTMENT_STEP_MINUTES, totalMinutes),
    );

    openCreateAt(referenceDate.value, Math.floor(clampedMinutes / 60), clampedMinutes % 60);
};
const openCreateAtNextAvailable = () => {
    const next = parseDateTime(nextAvailableCreateDateTime.value);
    if (!next) return;

    openCreateAt(referenceDate.value, next.getHours(), next.getMinutes());
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
    {
        key: 'today',
        label: 'Visitas hoje',
        value: String(props.stats?.today ?? 0),
        icon: CalendarClock,
        tone: 'text-slate-700',
    },
    {
        key: 'next',
        label: 'Próximas 24h',
        value: String(props.stats?.next_24h ?? 0),
        icon: Clock3,
        tone: 'text-slate-700',
    },
    {
        key: 'teams',
        label: 'Responsáveis ativos',
        value: String(props.stats?.teams ?? 0),
        icon: UserRound,
        tone: 'text-slate-700',
    },
]);

const clientOptions = computed(() => [
    { value: '', label: 'Sem cliente' },
    ...(props.clients ?? []).map((client) => ({ value: client.id, label: client.name })),
]);

const serviceOptions = computed(() => [
    { value: '', label: 'Sem serviço vinculado' },
    ...(props.services ?? []).map((service) => ({ value: service.id, label: service.name })),
]);
const serviceDurationMinutesById = computed(
    () =>
        new Map(
            (props.services ?? []).map((service) => {
                const parsedDuration = Number.parseInt(String(service?.duration_minutes ?? 60), 10);
                const safeDuration = Number.isNaN(parsedDuration)
                    ? 60
                    : Math.max(APPOINTMENT_STEP_MINUTES, parsedDuration);

                return [String(service.id), safeDuration];
            }),
        ),
);
const resolveServiceDefaultDurationMinutes = (serviceCatalogId = form.service_catalog_id) => {
    const duration = serviceDurationMinutesById.value.get(String(serviceCatalogId ?? ''));
    return duration ?? 60;
};

const orderOptions = computed(() => [
    { value: '', label: 'Sem OS vinculada' },
    ...(props.orders ?? []).map((order) => ({ value: order.id, label: order.label })),
]);

const statusFilterOptions = computed(() => [
    { value: '', label: 'Todos os status' },
    ...(props.statusOptions ?? []),
]);
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

    const hour =
        selectedSlotHour.value === null
            ? Math.max(normalizedStartHour.value, nowAtTimezone.value.getHours())
            : selectedSlotHour.value;

    closeSlotModal();
    openCreateAt(selectedSlotDate.value, hour);
};

const formDefaults = () => ({
    title: '',
    service_order_id: '',
    client_id: '',
    service_catalog_id: '',
    starts_at: '',
    ends_at: '',
    status: props.statusOptions?.[0]?.value ?? 'scheduled',
    payment_status: props.paymentStatusOptions?.[0]?.value ?? 'pending',
    location: '',
    notes: '',
});

const form = useForm(formDefaults());
const buildQuickClientDefaults = () => ({
    name: '',
    email: '',
    phone: '',
    document: '',
    is_active: true,
});
const createClientForm = useForm(buildQuickClientDefaults());
const showCreateClientInline = ref(false);
const page = usePage();
const showModal = ref(false);
const editingAppointment = ref(null);
const showDeleteModal = ref(false);
const appointmentToDelete = ref(null);
const deleteForm = useForm({});

const isEditing = computed(() => Boolean(editingAppointment.value?.id));
const resolveDurationMinutes = (startsAtValue = form.starts_at, endsAtValue = form.ends_at) => {
    const startsAt = parseDateTime(startsAtValue);
    const endsAt = parseDateTime(endsAtValue);

    if (!startsAt || !endsAt) {
        return resolveServiceDefaultDurationMinutes();
    }

    const durationInMinutes = Math.round((endsAt.getTime() - startsAt.getTime()) / 60000);
    return durationInMinutes >= APPOINTMENT_STEP_MINUTES
        ? durationInMinutes
        : resolveServiceDefaultDurationMinutes();
};
const clampToMinimumStart = (datetimeLocal) => {
    const candidateValue = snapDateTimeToStep(
        datetimeLocal || minimumBookableDateTime.value,
        APPOINTMENT_STEP_MINUTES,
        'ceil',
    );
    const candidate = parseDateTime(candidateValue);
    const minimum = parseDateTime(minimumBookableDateTime.value);
    if (!candidate || !minimum) {
        return minimumBookableDateTime.value;
    }

    return candidate.getTime() < minimum.getTime() ? minimumBookableDateTime.value : candidateValue;
};
const syncTimeRange = (startsAtValue, durationMinutes = resolveDurationMinutes()) => {
    const parsedDuration = Number.parseInt(String(durationMinutes ?? 60), 10);
    const safeDuration = Number.isNaN(parsedDuration)
        ? resolveServiceDefaultDurationMinutes()
        : Math.max(APPOINTMENT_STEP_MINUTES, parsedDuration);
    const nextStartsAt = isEditing.value
        ? String(startsAtValue || '')
        : clampToMinimumStart(startsAtValue || minimumStartDateTime.value);

    form.starts_at = nextStartsAt;
    form.ends_at = addMinutes(nextStartsAt, safeDuration);
};
const useCurrentTime = () => {
    syncTimeRange(minimumBookableDateTime.value);
    form.clearErrors('starts_at', 'ends_at');
};
const minimumEndDateTime = computed(() => {
    const startsAt = parseDateTime(form.starts_at);
    if (!startsAt) {
        return addMinutes(minimumBookableDateTime.value, APPOINTMENT_STEP_MINUTES);
    }

    if (isEditing.value) {
        return addMinutes(toDateTimeLocal(startsAt), APPOINTMENT_STEP_MINUTES);
    }

    return addMinutes(
        clampToMinimumStart(toDateTimeLocal(startsAt)),
        APPOINTMENT_STEP_MINUTES,
    );
});
const findOptionLabel = (options, value, fallback) => {
    const selected = (options ?? []).find(
        (option) => String(option?.value ?? '') === String(value ?? ''),
    );

    return selected?.label ?? fallback;
};
const appointmentPeriodLabel = computed(() => {
    const startsAt = parseDateTime(form.starts_at);
    const endsAt = parseDateTime(form.ends_at);

    if (!startsAt && !endsAt) {
        return 'Horário ainda não definido';
    }

    const dayLabel = startsAt
        ? new Intl.DateTimeFormat('pt-BR', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              timeZone: props.timezone,
          }).format(startsAt)
        : 'Data pendente';

    const startLabel = startsAt ? formatHourMinuteLabel(form.starts_at) : '--:--';
    const endLabel = endsAt ? formatHourMinuteLabel(form.ends_at) : '--:--';

    return `${dayLabel} • ${startLabel} às ${endLabel}`;
});
const appointmentDurationLabel = computed(() => {
    const minutes = resolveDurationMinutes(form.starts_at, form.ends_at);
    return `${minutes} min`;
});
const appointmentClientSummary = computed(() =>
    findOptionLabel(clientOptions.value, form.client_id, 'Sem cliente'),
);
const appointmentServiceSummary = computed(() =>
    findOptionLabel(serviceOptions.value, form.service_catalog_id, 'Sem serviço'),
);
const appointmentStatusSummary = computed(() =>
    findOptionLabel(props.statusOptions, form.status, 'Sem status'),
);
const appointmentPaymentSummary = computed(() =>
    findOptionLabel(props.paymentStatusOptions, form.payment_status, 'Pagamento pendente'),
);
const flashNewClientId = computed(() => {
    const rawValue = page.props?.flash?.new_client_id;
    const parsed = Number.parseInt(String(rawValue ?? ''), 10);
    return Number.isNaN(parsed) ? 0 : parsed;
});

const openCreateClientInline = () => {
    createClientForm.defaults(buildQuickClientDefaults());
    createClientForm.reset();
    createClientForm.clearErrors();
    showCreateClientInline.value = true;
};

const closeCreateClientInline = () => {
    showCreateClientInline.value = false;
    createClientForm.clearErrors();
    createClientForm.reset();
};

const onCreateClientPhoneInput = (event) => {
    createClientForm.phone = formatPhoneBR(event?.target?.value ?? createClientForm.phone);
};

const onCreateClientDocumentInput = (event) => {
    createClientForm.document = formatCpfCnpjBR(
        event?.target?.value ?? createClientForm.document,
    );
};

const submitCreateClient = () => {
    createClientForm.phone = formatPhoneBR(createClientForm.phone);
    createClientForm.document = formatCpfCnpjBR(createClientForm.document);

    createClientForm.post(route('admin.clients.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showCreateClientInline.value = false;
            createClientForm.defaults(buildQuickClientDefaults());
            createClientForm.reset();
            createClientForm.clearErrors();
        },
    });
};

watch(
    () => form.starts_at,
    (nextStartsAt, previousStartsAt) => {
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
        const previousDuration = resolveDurationMinutes(
            previousStartsAt || nextStartsAt,
            form.ends_at,
        );

        if (previousStartsAt && previousStartsAt !== nextStartsAt) {
            const shiftedEndsAt = addMinutes(nextStartsAt, previousDuration);
            const shiftedEndsAtDate = parseDateTime(shiftedEndsAt);

            if (
                shiftedEndsAtDate &&
                minimumEndsAt &&
                shiftedEndsAtDate.getTime() >= minimumEndsAt.getTime()
            ) {
                form.ends_at = shiftedEndsAt;
                return;
            }
        }

        if (
            !currentEndsAt ||
            (minimumEndsAt && currentEndsAt.getTime() < minimumEndsAt.getTime())
        ) {
            form.ends_at = minimumEndDateTime.value;
        }
    },
);

watch(
    () => form.service_catalog_id,
    (nextServiceId, previousServiceId) => {
        if (!showModal.value || isEditing.value || nextServiceId === previousServiceId) return;

        const safeStartsAt = clampToMinimumStart(form.starts_at || minimumBookableDateTime.value);
        const durationMinutes = resolveServiceDefaultDurationMinutes(nextServiceId);

        form.starts_at = safeStartsAt;
        form.ends_at = addMinutes(safeStartsAt, durationMinutes);
    },
);

watch(
    [flashNewClientId, () => props.clients],
    ([clientId]) => {
        if (!clientId) return;

        const matchedClient = (props.clients ?? []).find(
            (client) => Number(client?.id ?? 0) === Number(clientId),
        );

        if (!matchedClient) return;

        form.client_id = matchedClient.id;
        showCreateClientInline.value = false;
        createClientForm.defaults(buildQuickClientDefaults());
        createClientForm.reset();
        createClientForm.clearErrors();
    },
    { immediate: true, deep: true },
);

const openCreate = () => {
    openCreateAt(referenceDate.value);
};

const alignFormStartToGrid = () => {
    if (!form.starts_at) return;

    const durationMinutes = resolveDurationMinutes();
    const normalizedStartsAt = isEditing.value
        ? snapDateTimeToStep(form.starts_at, APPOINTMENT_STEP_MINUTES, 'ceil')
        : clampToMinimumStart(form.starts_at);

    if (!normalizedStartsAt) return;

    if (normalizedStartsAt !== form.starts_at) {
        form.starts_at = normalizedStartsAt;
        form.ends_at = addMinutes(normalizedStartsAt, durationMinutes);
        return;
    }

    const currentEndsAt = parseDateTime(form.ends_at);
    const minimumEndsAt = parseDateTime(minimumEndDateTime.value);
    if (!currentEndsAt || (minimumEndsAt && currentEndsAt.getTime() < minimumEndsAt.getTime())) {
        form.ends_at = minimumEndDateTime.value;
    }
};

const openCreateAt = (date, hour = null, minute = 0) => {
    editingAppointment.value = null;
    showCreateClientInline.value = false;
    createClientForm.defaults(buildQuickClientDefaults());
    createClientForm.reset();
    createClientForm.clearErrors();
    const safeDate = String(date || referenceDate.value || minimumStartDate.value);
    const fallbackHour = Number.isInteger(hour) ? hour : normalizedStartHour.value;
    const fallbackMinute = Number.isInteger(minute) ? minute : 0;
    const serviceCatalogId = '';
    const startsAt = clampToMinimumStart(
        `${safeDate}T${pad2(fallbackHour)}:${pad2(fallbackMinute)}`,
    );
    const endsAt = addMinutes(startsAt, resolveServiceDefaultDurationMinutes(serviceCatalogId));
    form.defaults({
        ...formDefaults(),
        service_catalog_id: serviceCatalogId,
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
    showCreateClientInline.value = false;
    createClientForm.defaults(buildQuickClientDefaults());
    createClientForm.reset();
    createClientForm.clearErrors();
    form.title = appointment.title ?? '';
    form.service_order_id = appointment.service_order_id ?? '';
    form.client_id = appointment.client_id ?? '';
    form.service_catalog_id = appointment.service_catalog_id ?? '';
    form.starts_at = appointment.starts_at ?? '';
    form.ends_at = appointment.ends_at ?? '';
    form.status = appointment.status ?? props.statusOptions?.[0]?.value ?? 'scheduled';
    form.payment_status =
        appointment.payment_status ?? props.paymentStatusOptions?.[0]?.value ?? 'pending';
    form.location = appointment.location ?? '';
    form.notes = appointment.notes ?? '';
    form.clearErrors();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingAppointment.value = null;
    showCreateClientInline.value = false;
    createClientForm.defaults(buildQuickClientDefaults());
    createClientForm.reset();
    createClientForm.clearErrors();
    form.clearErrors();
    form.defaults(formDefaults());
    form.reset();
};

const submitAppointment = () => {
    form.clearErrors('starts_at', 'ends_at');
    const durationMinutes = resolveDurationMinutes();

    if (!isEditing.value) {
        const normalizedStart = clampToMinimumStart(form.starts_at || minimumStartDateTime.value);
        if (normalizedStart !== form.starts_at) {
            form.starts_at = normalizedStart;
            form.ends_at = addMinutes(normalizedStart, durationMinutes);
        }

        const startsAt = parseDateTime(form.starts_at);
        const minimum = parseDateTime(minimumStartDateTime.value);
        if (!startsAt || !minimum || startsAt.getTime() < minimum.getTime()) {
            form.setError(
                'starts_at',
                'Informe uma data e hora atual ou futura para o agendamento.',
            );
            return;
        }
    }

    const endsAt = parseDateTime(form.ends_at);
    const minimumEnd = parseDateTime(minimumEndDateTime.value);
    if (!endsAt || !minimumEnd || endsAt.getTime() < minimumEnd.getTime()) {
        form.setError('ends_at', 'O horário de término deve ter pelo menos 15 min após o início.');
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

watch(showModal, (isOpen) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

const closeModalOnEscape = (event) => {
    if (event.key !== 'Escape' || !showModal.value) return;
    event.preventDefault();
    closeModal();
};

onMounted(() => {
    if (typeof document === 'undefined') return;
    document.addEventListener('keydown', closeModalOnEscape);
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return;
    document.removeEventListener('keydown', closeModalOnEscape);
    document.body.style.overflow = '';
});

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
const appointmentStatusTheme = (value) => {
    const status = String(value ?? '').trim().toLowerCase();

    if (status === 'cancelled' || status === 'no_show') {
        return {
            cardStyle: {
                background:
                    'linear-gradient(135deg, rgba(254, 242, 242, 0.98) 0%, rgba(254, 226, 226, 0.98) 100%)',
                borderColor: 'rgba(252, 165, 165, 0.75)',
            },
            badgeClass: 'bg-rose-100 text-rose-700',
            accentClass: 'text-rose-700',
        };
    }

    if (status === 'in_service') {
        return {
            cardStyle: {
                background:
                    'linear-gradient(135deg, rgba(239, 246, 255, 0.98) 0%, rgba(219, 234, 254, 0.98) 100%)',
                borderColor: 'rgba(147, 197, 253, 0.85)',
            },
            badgeClass: 'bg-sky-100 text-sky-700',
            accentClass: 'text-sky-700',
        };
    }

    if (status === 'done') {
        return {
            cardStyle: {
                background:
                    'linear-gradient(135deg, rgba(240, 253, 244, 0.98) 0%, rgba(220, 252, 231, 0.98) 100%)',
                borderColor: 'rgba(134, 239, 172, 0.85)',
            },
            badgeClass: 'bg-emerald-100 text-emerald-700',
            accentClass: 'text-emerald-700',
        };
    }

    return {
        cardStyle: {
            background:
                'linear-gradient(135deg, rgba(248, 250, 252, 0.98) 0%, rgba(241, 245, 249, 0.98) 100%)',
            borderColor: 'rgba(203, 213, 225, 0.9)',
        },
        badgeClass: 'bg-slate-200 text-slate-700',
        accentClass: 'text-slate-700',
    };
};
const appointmentStatusCardStyle = (value) => appointmentStatusTheme(value).cardStyle;
const appointmentStatusBadgeClass = (value) => appointmentStatusTheme(value).badgeClass;
const appointmentStatusAccentClass = (value) => appointmentStatusTheme(value).accentClass;
</script>

<template>
    <Head title="Agenda de Serviços" />

    <AuthenticatedLayout
        area="admin"
        header-variant="compact"
        header-title="Agenda de Serviços"
        :show-table-view-toggle="false"
    >
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="stat in statsCards"
                    :key="stat.key"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                        </div>
                        <span
                            class="veshop-stat-icon inline-flex h-9 w-9 items-center justify-center rounded-xl"
                            :class="stat.tone"
                        >
                            <component :is="stat.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </article>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div
                        class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                    >
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
                        <UiSelect
                            v-model="filterForm.status"
                            :options="statusFilterOptions"
                            button-class="w-full sm:w-auto"
                            @change="applyFilters"
                        />
                        <button
                            type="button"
                            class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto"
                            @click="clearFilters"
                        >
                            <Filter class="h-3.5 w-3.5" />
                            Limpar
                        </button>
                        <button
                            type="button"
                            class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                            @click="openCreate"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Novo compromisso
                        </button>
                    </div>
                </div>

                <div
                    v-if="!hasServices"
                    class="mt-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs font-semibold text-sky-700"
                >
                    Você ainda pode criar compromissos com título manual, mesmo sem serviço vinculado.
                </div>

                <div
                    class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                            <button
                                v-for="option in viewOptions"
                                :key="`layout-${option.value}`"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                :class="
                                    layout === option.value
                                        ? 'text-white'
                                        : 'text-slate-600 hover:bg-white'
                                "
                                :style="
                                    layout === option.value
                                        ? { background: 'var(--veshop-accent)' }
                                        : null
                                "
                                @click="setLayout(option.value)"
                            >
                                <component :is="option.icon" class="h-3.5 w-3.5" />
                                {{ option.label }}
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"
                            >
                                Janela diária
                            </p>
                            <UiSelect
                                v-model="startHour"
                                :options="hourOptions"
                                button-class="w-[96px] text-xs"
                            />
                            <span class="text-xs font-semibold text-slate-500">até</span>
                            <UiSelect
                                v-model="endHour"
                                :options="hourOptions"
                                button-class="w-[96px] text-xs"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50"
                            @click="shiftPeriod(-1)"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-600 hover:bg-slate-50"
                            @click="shiftPeriod(1)"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                        <input
                            v-model="referenceDate"
                            type="date"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700"
                            @change="applyFilters"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="goToday"
                        >
                            Hoje
                        </button>
                    </div>
                </div>

                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Visão atual
                    </p>
                    <p class="text-sm font-semibold text-slate-800">
                        {{ layoutLabel }}: {{ calendarTitle }}
                    </p>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3">
                    <template v-if="layout === 'month'">
                        <div
                            class="grid grid-cols-7 gap-2 border-b border-slate-100 pb-2 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500"
                        >
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
                                        <span
                                            v-if="isTodayDate(day)"
                                            class="ml-1 rounded-full bg-slate-900 px-1.5 py-0.5 text-[10px] text-white"
                                            >Hoje</span
                                        >
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                        :disabled="!canCreateAtDate(day)"
                                        @click="openCreateAt(toIsoDate(day))"
                                    >
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
                                    <p
                                        v-if="eventsForDate(day).length > 3"
                                        class="text-[10px] font-semibold text-slate-500"
                                    >
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
                                    isTodayDate(day)
                                        ? 'bg-white ring-1 ring-slate-900/20'
                                        : 'bg-slate-50',
                                    isPastDate(day) ? 'opacity-70' : '',
                                ]"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-slate-700 transition hover:text-slate-900"
                                        @click="openSlotModal(day)"
                                    >
                                        {{
                                            new Intl.DateTimeFormat('pt-BR', {
                                                weekday: 'short',
                                                day: '2-digit',
                                                month: '2-digit',
                                            }).format(day)
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                        :disabled="!canCreateAtDate(day)"
                                        @click="openCreateAt(toIsoDate(day))"
                                    >
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
                                        <p class="font-semibold">
                                            {{ event.start_label }} - {{ event.end_label }}
                                        </p>
                                        <p class="truncate">{{ event.title }}</p>
                                    </button>
                                    <p
                                        v-if="!eventsForDate(day).length"
                                        class="text-[11px] text-slate-500"
                                    >
                                        Sem compromissos
                                    </p>
                                </div>
                            </article>
                        </div>
                    </template>

                    <template v-else>
                        <div class="space-y-3">
                            <div
                                class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 md:flex-row md:items-center md:justify-between"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Timeline do dia
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        Clique em uma lacuna para abrir no próximo encaixe válido.
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        v-if="isReferenceDateToday"
                                        class="rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-slate-700"
                                    >
                                        Agora {{ formatHourMinuteLabel(minimumStartDateTime) }}
                                    </span>
                                    <span
                                        v-if="nextAvailableCreateLabel"
                                        class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700"
                                    >
                                        Próximo encaixe {{ nextAvailableCreateLabel }}
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="!canCreateAtDate(referenceDate)"
                                        @click="openCreateAtNextAvailable"
                                    >
                                        <Plus class="h-3.5 w-3.5" />
                                        Novo no próximo encaixe
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-hidden rounded-2xl border border-slate-200">
                                <div class="flex border-b border-slate-200 bg-slate-50/80">
                                    <div
                                        class="w-[78px] shrink-0 border-r border-slate-200 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Hora
                                    </div>
                                    <div
                                        class="flex-1 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500"
                                    >
                                        Agenda do dia
                                    </div>
                                </div>

                                <div class="flex">
                                    <div
                                        class="w-[78px] shrink-0 border-r border-slate-200 bg-slate-50/70"
                                    >
                                        <div
                                            v-for="hour in daySlots"
                                            :key="`day-label-${hour}`"
                                            class="relative border-b border-slate-200 px-3 pt-2"
                                            :style="{ height: `${DAY_TIMELINE_HOUR_HEIGHT}px` }"
                                        >
                                            <p class="text-xs font-semibold text-slate-600">
                                                {{ pad2(hour) }}:00
                                            </p>
                                        </div>
                                    </div>

                                    <div class="relative flex-1 bg-white">
                                        <div
                                            class="relative"
                                            :style="{ height: `${dayTimelineHeight}px` }"
                                        >
                                            <div
                                                v-if="pastTimeOverlayHeight > 0"
                                                class="pointer-events-none absolute inset-x-0 top-0 z-0 bg-slate-100/60"
                                                :style="{ height: `${pastTimeOverlayHeight}px` }"
                                            />

                                            <div
                                                class="absolute inset-0 z-0"
                                                :class="
                                                    canCreateAtDate(referenceDate)
                                                        ? 'cursor-crosshair'
                                                        : 'cursor-default'
                                                "
                                                @click="handleDayTimelineClick"
                                            >
                                                <div
                                                    v-for="hour in daySlots"
                                                    :key="`day-grid-${hour}`"
                                                    class="relative border-b border-slate-200 transition hover:bg-slate-50/70"
                                                    :style="{
                                                        height: `${DAY_TIMELINE_HOUR_HEIGHT}px`,
                                                    }"
                                                >
                                                    <div
                                                        class="absolute inset-x-0 top-1/4 border-t border-dashed border-slate-200"
                                                    />
                                                    <div
                                                        class="absolute inset-x-0 top-2/4 border-t border-dashed border-slate-200"
                                                    />
                                                    <div
                                                        class="absolute inset-x-0 top-3/4 border-t border-dashed border-slate-200"
                                                    />
                                                </div>
                                            </div>

                                            <div
                                                v-if="!dayTimelineEvents.length"
                                                class="pointer-events-none absolute inset-x-4 top-4 z-10 rounded-xl border border-dashed border-slate-300 bg-white/90 px-4 py-8 text-center text-sm text-slate-500"
                                            >
                                                Nenhum compromisso nesta timeline.
                                            </div>

                                            <div
                                                v-if="currentTimeIndicatorOffset !== null"
                                                class="pointer-events-none absolute inset-x-0 z-20"
                                                :style="{ top: `${currentTimeIndicatorOffset}px` }"
                                            >
                                                <div class="flex items-center gap-2 px-2">
                                                    <span
                                                        class="h-2.5 w-2.5 rounded-full bg-rose-500"
                                                    />
                                                    <div class="h-px flex-1 bg-rose-400/80" />
                                                </div>
                                            </div>

                                            <div class="absolute inset-0 z-10 px-2">
                                                <button
                                                    v-for="event in dayTimelineEvents"
                                                    :key="`day-event-${event.id}`"
                                                    type="button"
                                                    class="absolute overflow-hidden rounded-xl border border-transparent px-3 py-2 text-left shadow-sm transition hover:brightness-[0.96]"
                                                    :style="[
                                                        dayTimelineEventStyle(event),
                                                        appointmentStatusCardStyle(event.status),
                                                    ]"
                                                    @click.stop="openEdit(event)"
                                                >
                                                    <p
                                                        class="text-[11px] font-semibold"
                                                        :class="appointmentStatusAccentClass(event.status)"
                                                    >
                                                        {{ event.start_label }} -
                                                        {{ event.end_label }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-sm font-semibold text-slate-900"
                                                    >
                                                        {{ event.title }}
                                                    </p>
                                                    <p
                                                        class="mt-1 truncate text-[11px] text-slate-600"
                                                    >
                                                        {{ event.client_name || 'Sem cliente' }}
                                                    </p>
                                                    <p class="truncate text-[11px] text-slate-500">
                                                        {{ event.service_name || 'Sem serviço' }}
                                                    </p>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold text-slate-800">
                            Compromissos do período
                        </h4>
                        <p class="text-xs text-slate-500">{{ rows.length }} registro(s)</p>
                    </div>

                    <div
                        v-if="!rows.length"
                        class="mt-2 rounded-lg border border-dashed border-slate-200 bg-white px-3 py-8 text-center text-sm text-slate-500"
                    >
                        Nenhum compromisso encontrado para os filtros selecionados.
                    </div>

                    <div v-else class="mt-3 grid gap-2">
                        <article
                            v-for="appointment in rows"
                            :key="`appointment-card-${appointment.id}`"
                            class="rounded-xl border border-transparent px-3 py-2 shadow-sm"
                            :style="appointmentStatusCardStyle(appointment.status)"
                        >
                            <div
                                class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ appointment.title }}
                                    </p>
                                    <p class="text-xs text-slate-600">
                                        {{ appointment.time_label }} -
                                        {{ appointment.client_name }} -
                                        {{ appointment.service_name || 'Sem serviço' }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ appointment.technician || 'Sem responsável' }} -
                                        {{ appointment.service_order_code || '' }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                        :class="appointmentStatusBadgeClass(appointment.status)"
                                    >
                                        {{ statusLabel(appointment.status) }}
                                    </span>
                                    <span
                                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                        :class="
                                            appointment.payment_status_tone ||
                                            'bg-amber-100 text-amber-700'
                                        "
                                    >
                                        {{ appointment.payment_status_label || 'Pagamento pendente' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                        @click="openEdit(appointment)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                                        @click="openDeleteModal(appointment)"
                                    >
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
                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ selectedSlotTitle }}
                        </h3>
                        <p class="text-sm text-slate-500">
                            Visualize os compromissos do período e abra o cadastro rapidamente.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeSlotModal"
                    >
                        Fechar
                    </button>
                </div>

                <div v-if="selectedSlotEvents.length" class="space-y-2">
                    <article
                        v-for="event in selectedSlotEvents"
                        :key="`slot-event-${event.id}`"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ event.title }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ event.start_label }} - {{ event.end_label }} |
                                    {{ event.client_name || 'Sem cliente' }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ event.service_name || 'Sem serviço' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="
                                    closeSlotModal();
                                    openEdit(event);
                                "
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
                        :disabled="!canCreateForSelectedSlot"
                        @click="createFromSelectedSlot"
                    >
                        Novo compromisso
                    </button>
                </div>
            </div>
        </Modal>

        <Transition name="appointment-drawer-backdrop">
            <div
                v-if="showModal"
                class="fixed inset-0 z-[120] bg-slate-950/30 backdrop-blur-[2px]"
                @click="closeModal"
            />
        </Transition>

        <Transition name="appointment-drawer">
            <aside
                v-if="showModal"
                class="fixed inset-y-0 right-0 z-[130] flex w-full max-w-4xl flex-col border-l border-emerald-100 bg-white shadow-2xl"
                @click.stop
            >
                <header class="space-y-4 border-b border-emerald-100 px-5 py-4 sm:px-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                Agenda
                            </p>
                            <h3 class="text-base font-semibold text-slate-900">
                                {{ isEditing ? 'Editar compromisso' : 'Novo compromisso' }}
                            </h3>
                            <p class="text-sm text-slate-500">
                                Organize o agendamento sem sair da visão atual da agenda.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                            aria-label="Fechar compromisso"
                            @click="closeModal"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-full border border-slate-200 bg-white px-3 py-2 shadow-sm">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                Período
                            </p>
                            <p class="mt-1 text-xs font-semibold text-slate-700">
                                {{ appointmentPeriodLabel }}
                            </p>
                        </div>
                        <div class="rounded-full border border-slate-200 bg-white px-3 py-2 shadow-sm">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                Duração
                            </p>
                            <p class="mt-1 text-xs font-semibold text-slate-700">
                                {{ appointmentDurationLabel }}
                            </p>
                        </div>
                        <div class="rounded-full border border-slate-200 bg-white px-3 py-2 shadow-sm">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                Status
                            </p>
                            <p class="mt-1 text-xs font-semibold text-slate-700">
                                {{ appointmentStatusSummary }}
                            </p>
                        </div>
                        <div class="rounded-full border border-slate-200 bg-white px-3 py-2 shadow-sm">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                Pagamento
                            </p>
                            <p class="mt-1 text-xs font-semibold text-slate-700">
                                {{ appointmentPaymentSummary }}
                            </p>
                        </div>
                    </div>
                </header>

                <div class="flex-1 overflow-auto px-5 py-4 sm:px-6">
                    <div class="space-y-4">
                        <section class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">
                                        Visão geral
                                    </h4>
                                    <p class="text-xs text-slate-500">
                                        Defina o compromisso, quem será atendido e o contexto do atendimento.
                                    </p>
                                </div>
                                <span class="rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-slate-500 shadow-sm">
                                    {{ appointmentServiceSummary }}
                                </span>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Serviço
                                    </label>
                                    <UiSelect
                                        v-model="form.service_catalog_id"
                                        :options="serviceOptions"
                                        button-class="mt-1 w-full text-sm"
                                        searchable
                                        search-placeholder="Buscar serviço..."
                                    />
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Opcional quando o compromisso for cadastrado apenas com título.
                                    </p>
                                    <p v-if="form.errors.service_catalog_id" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.service_catalog_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Título
                                    </label>
                                    <input
                                        v-model="form.title"
                                        type="text"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                        placeholder="Ex.: Revisão do equipamento"
                                    />
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Preencha o título ou selecione um serviço.
                                    </p>
                                    <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.title }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Cliente
                                    </label>
                                    <div class="mt-1 flex items-stretch gap-2">
                                        <div class="min-w-0 flex-1">
                                            <UiSelect
                                                v-model="form.client_id"
                                                :options="clientOptions"
                                                button-class="w-full text-sm"
                                                searchable
                                                search-placeholder="Buscar cliente..."
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-2xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                            @click="openCreateClientInline"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                            Novo
                                        </button>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        {{ appointmentClientSummary }}
                                    </p>
                                    <p v-if="form.errors.client_id" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.client_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        OS vinculada
                                    </label>
                                    <UiSelect
                                        v-model="form.service_order_id"
                                        :options="orderOptions"
                                        button-class="mt-1 w-full text-sm"
                                        searchable
                                        search-placeholder="Buscar OS..."
                                    />
                                    <p v-if="form.errors.service_order_id" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.service_order_id }}
                                    </p>
                                </div>
                                <div
                                    v-if="showCreateClientInline"
                                    class="xl:col-span-2 rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm"
                                >
                                    <div class="mb-3 flex items-start justify-between gap-3">
                                        <div>
                                            <h5 class="text-sm font-semibold text-slate-900">
                                                Cadastrar cliente
                                            </h5>
                                            <p class="text-xs text-slate-500">
                                                Crie o cliente sem sair do compromisso. Nome é obrigatório.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                                            aria-label="Fechar cadastro de cliente"
                                            @click="closeCreateClientInline"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="md:col-span-2">
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Nome
                                            </label>
                                            <input
                                                v-model="createClientForm.name"
                                                type="text"
                                                class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                                placeholder="Nome do cliente"
                                            />
                                            <p v-if="createClientForm.errors.name" class="mt-1 text-xs text-rose-600">
                                                {{ createClientForm.errors.name }}
                                            </p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Telefone
                                            </label>
                                            <input
                                                :value="createClientForm.phone"
                                                type="text"
                                                inputmode="numeric"
                                                maxlength="15"
                                                class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                                placeholder="(11) 99999-9999"
                                                @input="onCreateClientPhoneInput"
                                            />
                                            <p v-if="createClientForm.errors.phone" class="mt-1 text-xs text-rose-600">
                                                {{ createClientForm.errors.phone }}
                                            </p>
                                        </div>

                                        <div>
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                E-mail
                                            </label>
                                            <input
                                                v-model="createClientForm.email"
                                                type="email"
                                                class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                                placeholder="cliente@email.com"
                                            />
                                            <p v-if="createClientForm.errors.email" class="mt-1 text-xs text-rose-600">
                                                {{ createClientForm.errors.email }}
                                            </p>
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Documento
                                            </label>
                                            <input
                                                :value="createClientForm.document"
                                                type="text"
                                                inputmode="numeric"
                                                maxlength="18"
                                                class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                                placeholder="CPF ou CNPJ"
                                                @input="onCreateClientDocumentInput"
                                            />
                                            <p v-if="createClientForm.errors.document" class="mt-1 text-xs text-rose-600">
                                                {{ createClientForm.errors.document }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                                        <button
                                            type="button"
                                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                            @click="closeCreateClientInline"
                                        >
                                            Cancelar
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                                            :disabled="createClientForm.processing"
                                            @click="submitCreateClient"
                                        >
                                            {{ createClientForm.processing ? 'Salvando...' : 'Salvar cliente' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="mb-3">
                                <h4 class="text-sm font-semibold text-slate-900">
                                    Horário e operação
                                </h4>
                                <p class="text-xs text-slate-500">
                                    Ajuste os horarios e o estado atual do compromisso.
                                </p>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-2">
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Inicio
                                        </label>
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                            @click="useCurrentTime"
                                        >
                                            Agora
                                        </button>
                                    </div>
                                    <input
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                        :min="isEditing ? null : minimumBookableDateTime"
                                        :step="APPOINTMENT_STEP_SECONDS"
                                        @change="alignFormStartToGrid"
                                    />
                                    <p v-if="!isEditing" class="mt-1 text-[11px] text-slate-500">
                                        Clique em Agora para usar o próximo encaixe válido.
                                    </p>
                                    <p v-if="!isEditing" class="mt-1 text-[11px] text-slate-500">
                                        Disponível a partir de {{ minimumBookableDateTime.replace('T', ' ') }}.
                                    </p>
                                    <p v-if="form.errors.starts_at" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.starts_at }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Fim
                                    </label>
                                    <input
                                        v-model="form.ends_at"
                                        type="datetime-local"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                        :min="minimumEndDateTime"
                                        :step="APPOINTMENT_STEP_SECONDS"
                                    />
                                    <p v-if="form.errors.ends_at" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.ends_at }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Status
                                    </label>
                                    <UiSelect
                                        v-model="form.status"
                                        :options="props.statusOptions"
                                        button-class="mt-1 w-full text-sm"
                                    />
                                    <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.status }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Status do pagamento
                                    </label>
                                    <UiSelect
                                        v-model="form.payment_status"
                                        :options="props.paymentStatusOptions"
                                        button-class="mt-1 w-full text-sm"
                                    />
                                    <p v-if="form.errors.payment_status" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.payment_status }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="mb-3">
                                <h4 class="text-sm font-semibold text-slate-900">
                                    Detalhes complementares
                                </h4>
                                <p class="text-xs text-slate-500">
                                    Inclua observações operacionais para a execução do serviço.
                                </p>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-2">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Local
                                    </label>
                                    <input
                                        v-model="form.location"
                                        type="text"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                        placeholder="Endereço, sala ou referência"
                                    />
                                    <p v-if="form.errors.location" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.location }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Observações
                                    </label>
                                    <textarea
                                        v-model="form.notes"
                                        rows="4"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                                        placeholder="Instruções adicionais para a equipe"
                                    />
                                    <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">
                                        {{ form.errors.notes }}
                                    </p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="border-t border-slate-200 bg-white px-5 py-4 sm:px-6">
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="closeModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                            :disabled="form.processing"
                            @click="submitAppointment"
                        >
                            {{ form.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </div>
            </aside>
        </Transition>

        <Modal v-if="false" :show="showModal" max-width="5xl" @close="closeModal">
            <div class="space-y-4 px-6 py-6 sm:px-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ isEditing ? 'Editar compromisso' : 'Novo compromisso' }}
                        </h3>
                        <p class="text-sm text-slate-500">
                            Planejamento operacional da agenda de serviços.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Fechar
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Título</label
                        >
                        <input
                            v-model="form.title"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Ex.: Reunião de fechamento"
                        />
                        <p class="mt-1 text-[11px] text-slate-500">
                            Preencha o título ou selecione um serviço.
                        </p>
                        <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >OS vinculada</label
                        >
                        <UiSelect
                            v-model="form.service_order_id"
                            :options="orderOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="form.errors.service_order_id" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.service_order_id }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Cliente</label
                        >
                        <UiSelect
                            v-model="form.client_id"
                            :options="clientOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="form.errors.client_id" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.client_id }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Serviço</label
                        >
                        <UiSelect
                            v-model="form.service_catalog_id"
                            :options="serviceOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p class="mt-1 text-[11px] text-slate-500">
                            Opcional quando o compromisso for cadastrado apenas com título.
                        </p>
                        <p v-if="form.errors.service_catalog_id" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.service_catalog_id }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Status</label
                        >
                        <UiSelect
                            v-model="form.status"
                            :options="props.statusOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.status }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Status do pagamento</label
                        >
                        <UiSelect
                            v-model="form.payment_status"
                            :options="props.paymentStatusOptions"
                            button-class="mt-1 w-full text-sm"
                        />
                        <p v-if="form.errors.payment_status" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.payment_status }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Início</label
                        >
                        <div class="mt-1 flex items-center justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                                @click="useCurrentTime"
                            >
                                Agora
                            </button>
                        </div>
                        <input
                            v-model="form.starts_at"
                            type="datetime-local"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :min="isEditing ? null : minimumBookableDateTime"
                            :step="APPOINTMENT_STEP_SECONDS"
                            @change="alignFormStartToGrid"
                        />
                        <p v-if="!isEditing" class="mt-1 text-[11px] text-slate-500">
                            Clique em Agora para usar o próximo encaixe válido.
                        </p>
                        <p v-if="!isEditing" class="mt-1 text-[11px] text-slate-500">
                            Disponível a partir de {{ minimumBookableDateTime.replace('T', ' ') }}.
                        </p>
                        <p v-if="form.errors.starts_at" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.starts_at }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Fim</label
                        >
                        <input
                            v-model="form.ends_at"
                            type="datetime-local"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            :min="minimumEndDateTime"
                            :step="APPOINTMENT_STEP_SECONDS"
                        />
                        <p v-if="form.errors.ends_at" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.ends_at }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Local</label
                        >
                        <input
                            v-model="form.location"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Endereço ou sala"
                        />
                        <p v-if="form.errors.location" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.location }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Observações</label
                        >
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Instruções adicionais"
                        />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.notes }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submitAppointment"
                    >
                        {{ form.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="showDeleteModal"
            title="Excluir compromisso"
            message="Tem certeza que deseja excluir este compromisso?"
            :item-label="
                appointmentToDelete?.title ? `Compromisso: ${appointmentToDelete.title}` : ''
            "
            :processing="deleteForm.processing"
            @close="closeDeleteModal"
            @confirm="destroyAppointment"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.appointment-drawer-enter-active,
.appointment-drawer-leave-active {
    transition:
        transform 0.28s ease,
        opacity 0.28s ease;
}

.appointment-drawer-enter-from,
.appointment-drawer-leave-to {
    transform: translateX(24px);
    opacity: 0;
}

.appointment-drawer-backdrop-enter-active,
.appointment-drawer-backdrop-leave-active {
    transition: opacity 0.2s ease;
}

.appointment-drawer-backdrop-enter-from,
.appointment-drawer-backdrop-leave-to {
    opacity: 0;
}
</style>
