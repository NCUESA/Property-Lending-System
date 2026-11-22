<script setup lang="ts">
import { CalendarPlus, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { addDays } from '@/lib/format';
import { cn } from '@/lib/utils';

/*
 * 借用日期的日曆，兩種模式：
 *
 *   days 是數字 → 長度固定。點一天就選定「取件日 + 這麼多天」，一次點完。
 *   days 是 null → 自訂。在日曆上點頭尾兩天（取件日已固定時只點歸還日）。
 *
 * 日期一律用 YYYY-MM-DD 字串運算（和 lib/format 一致）：
 * 字典序就是時間序，比較大小不必轉成 Date，也不會踩到時區。
 * 同樣不使用 Intl.DateTimeFormat，避免 SSR 與瀏覽器的 ICU 差異造成 hydration mismatch。
 */

const props = withDefaults(
    defineProps<{
        /** 取件日，YYYY-MM-DD */
        start: string;
        /** 歸還日，YYYY-MM-DD */
        end: string;
        /** 借用天數（含取件當天）；null 代表讓使用者在日曆上自己點歸還日 */
        days: number | null;
        /** 最早可選的日子（通常是今天） */
        minDate: string;
        /** 最晚可當取件日的日子；不開放預借時等於 minDate */
        lastBorrowDate: string;
        /** 最晚可當歸還日的日子 */
        lastDate: string;
        /** 暫停借用的日子，不能當取件日或歸還日 */
        closedDates?: string[];
        /** 一開始顯示幾個月（視窗較窄時只會顯示第一個） */
        months?: number;
        /** 允許使用者自己多展開幾個月；借用天數可自訂時才有意義 */
        expandable?: boolean;
    }>(),
    { closedDates: () => [], months: 1, expandable: false },
);

/** 展開上限，再多就該用自訂歸還日而不是一直翻日曆。 */
const MAX_MONTHS = 4;

// 一次送出完整的一對日期，外面不會收到「只有頭沒有尾」的中間狀態
const emit = defineEmits<{
    'update:range': [value: { start: string; end: string }];
}>();

const WEEKDAYS = ['週日', '週一', '週二', '週三', '週四', '週五', '週六'];

const closed = computed(() => new Set(props.closedDates));

/* ── 日期小工具：全部走 UTC，避免瀏覽器時區把日子算歪 ───────────── */

function monthOf(date: string): string {
    return date.slice(0, 7);
}

function shiftMonth(month: string, delta: number): string {
    const year = Number(month.slice(0, 4));
    const index = Number(month.slice(5, 7)) - 1 + delta;

    return new Date(Date.UTC(year, index, 1)).toISOString().slice(0, 7);
}

/** 該月的格子：開頭補 null 讓 1 號落在正確的星期欄。 */
function monthCells(month: string): (string | null)[] {
    const year = Number(month.slice(0, 4));
    const index = Number(month.slice(5, 7)) - 1;
    const offset = new Date(Date.UTC(year, index, 1)).getUTCDay();
    const total = new Date(Date.UTC(year, index + 1, 0)).getUTCDate();

    return [
        ...Array.from({ length: offset }, () => null),
        ...Array.from(
            { length: total },
            (_, i) => `${month}-${String(i + 1).padStart(2, '0')}`,
        ),
    ];
}

function monthLabel(month: string): string {
    return `${month.slice(0, 4)}年${Number(month.slice(5, 7))}月`;
}

/* ── 可選範圍 ─────────────────────────────────────────────── */

/** 從某一天開始借，會還在哪一天。自訂模式沒有固定長度，就是那天本身。 */
function endFrom(start: string): string {
    if (props.days === null) return start;

    return [addDays(start, props.days - 1), props.lastDate].sort()[0];
}

/** 取件日只有一種可能（不開放預借）時，點日曆就只剩下選歸還日這件事。 */
const startLocked = computed(() => props.minDate === props.lastBorrowDate);

/** 自訂模式下，已經點了取件日、正在等使用者點歸還日。 */
const pendingStart = ref<string | null>(null);

const activeStart = computed(() => pendingStart.value ?? props.start);

/** 現在這一下點擊的意義。 */
const phase = computed<'start' | 'end'>(() => {
    if (props.days !== null) return 'start'; // 長度固定，永遠只點取件日
    if (startLocked.value) return 'end'; // 取件日無從選起
    return pendingStart.value === null ? 'start' : 'end';
});

function canBeStart(date: string): boolean {
    return (
        date >= props.minDate &&
        date <= props.lastBorrowDate &&
        !closed.value.has(date) &&
        // 長度固定時，算出來的歸還日也不能落在暫停借用的日子
        (props.days === null || !closed.value.has(endFrom(date)))
    );
}

function canBeEnd(date: string): boolean {
    return (
        date >= activeStart.value &&
        date <= props.lastDate &&
        !closed.value.has(date)
    );
}

function isSelectable(date: string): boolean {
    if (phase.value === 'start') return canBeStart(date);

    // 等著點歸還日時，往前點仍然可以重新選取件日
    return canBeEnd(date) || (!startLocked.value && canBeStart(date));
}

function select(date: string) {
    if (!isSelectable(date)) return;

    if (props.days !== null) {
        emit('update:range', { start: date, end: endFrom(date) });

        return;
    }

    if (phase.value === 'start') {
        pendingStart.value = date;

        return;
    }

    if (canBeEnd(date)) {
        emit('update:range', { start: activeStart.value, end: date });
        pendingStart.value = null;

        return;
    }

    pendingStart.value = date; // 點到更早的日子 → 重新選取件日
}

/* ── 顯示狀態 ─────────────────────────────────────────────── */

const hovered = ref<string | null>(null);

/** 滑過哪天就預覽那一段，和 Booking 一樣。 */
const shown = computed(() => {
    const hover = hovered.value;

    if (props.days !== null) {
        const from = hover !== null && canBeStart(hover) ? hover : props.start;

        return { from, to: endFrom(from) };
    }

    const from = activeStart.value;

    if (hover !== null && hover >= from && canBeEnd(hover)) {
        return { from, to: hover };
    }

    // 還在等使用者點歸還日時，只亮起點，不要誤導成已經選好一段
    return { from, to: pendingStart.value !== null ? from : props.end };
});

function dayState(date: string) {
    const { from, to } = shown.value;

    return {
        isStart: date === from,
        isEnd: date === to && to !== from,
        inRange: date > from && date < to,
        selected: date === from || (date === to && to !== from),
    };
}

/** 區間底色畫在外層容器上，格子才連得起來（頭尾各切一邊圓角）。 */
function rangeClass(date: string): string {
    const { isStart, isEnd, inRange } = dayState(date);
    const { from, to } = shown.value;

    if (inRange) return 'bg-accent';
    if (from === to) return '';
    if (isStart) return 'rounded-l-md bg-accent';
    if (isEnd) return 'rounded-r-md bg-accent';

    return '';
}

function dayTitle(date: string): string | undefined {
    if (closed.value.has(date)) return '暫停借用';

    if (!isSelectable(date)) {
        return props.days !== null && closed.value.has(endFrom(date))
            ? `從這天借 ${props.days} 天會還在暫停借用的日子`
            : undefined;
    }

    if (props.days !== null) return `從這天取件，${endFrom(date)} 歸還`;
    if (phase.value === 'end' && canBeEnd(date)) return '設為歸還日';

    return '設為取件日';
}

/* ── 月份切換 ─────────────────────────────────────────────── */

const firstMonth = ref(monthOf(props.start));

/** 使用者按「顯示更多月份」加出來的月數。 */
const extraMonths = ref(0);

/** 從第一個可見月份算到 lastDate 總共還有幾個月。 */
const monthsAvailable = computed(() => {
    let count = 1;

    while (
        count < MAX_MONTHS &&
        shiftMonth(firstMonth.value, count - 1) < monthOf(props.lastDate)
    ) {
        count += 1;
    }

    return count;
});

/** 選到的區間橫跨幾個月——至少要讓整段看得到，不然使用者不知道還到哪。 */
const monthsForRange = computed(() => {
    let count = 1;

    while (
        count < MAX_MONTHS &&
        shiftMonth(firstMonth.value, count - 1) < monthOf(shown.value.to)
    ) {
        count += 1;
    }

    return count;
});

const visibleCount = computed(() =>
    Math.min(
        MAX_MONTHS,
        monthsAvailable.value,
        Math.max(props.months, monthsForRange.value, 1 + extraMonths.value),
    ),
);

const canExpand = computed(
    () => props.expandable && visibleCount.value < monthsAvailable.value,
);

const visibleMonths = computed(() =>
    Array.from({ length: visibleCount.value }, (_, i) =>
        shiftMonth(firstMonth.value, i),
    ),
);

const canGoBack = computed(() => firstMonth.value > monthOf(props.minDate));
const canGoForward = computed(
    () =>
        visibleMonths.value[visibleMonths.value.length - 1] <
        monthOf(props.lastDate),
);

function step(delta: number) {
    if (delta < 0 ? canGoBack.value : canGoForward.value) {
        firstMonth.value = shiftMonth(firstMonth.value, delta);
    }
}

/** 把某一天捲進視野，鍵盤移動與外部改值都會用到。 */
function reveal(date: string) {
    const month = monthOf(date);

    if (month < firstMonth.value) {
        firstMonth.value = month;
    } else if (month > visibleMonths.value[visibleMonths.value.length - 1]) {
        firstMonth.value = shiftMonth(month, -(visibleCount.value - 1));
    }
}

watch(() => props.start, reveal);

/* ── 鍵盤操作：整張日曆只有一個 tab stop，方向鍵在格子間移動 ──── */

const focused = ref(props.start);
const dayRefs = new Map<string, HTMLButtonElement>();

function setDayRef(date: string, el: unknown) {
    if (el) {
        dayRefs.set(date, el as HTMLButtonElement);
    } else {
        dayRefs.delete(date);
    }
}

/** 方向鍵移動的落點；超出可視月份就先翻頁再對焦。 */
async function moveFocus(target: string) {
    if (target < props.minDate || target > props.lastDate) return;

    focused.value = target;
    reveal(target);
    await nextTick();
    dayRefs.get(target)?.focus();
}

const KEY_DELTAS: Record<string, number> = {
    ArrowLeft: -1,
    ArrowRight: 1,
    ArrowUp: -7,
    ArrowDown: 7,
};

function onKeydown(event: KeyboardEvent, date: string) {
    const delta = KEY_DELTAS[event.key];

    if (delta !== undefined) {
        event.preventDefault();
        void moveFocus(addDays(date, delta));

        return;
    }

    if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        const weekday = new Date(`${date}T00:00:00Z`).getUTCDay();
        void moveFocus(
            addDays(date, event.key === 'Home' ? -weekday : 6 - weekday),
        );

        return;
    }

    if (event.key === 'PageUp' || event.key === 'PageDown') {
        event.preventDefault();
        const month = shiftMonth(
            monthOf(date),
            event.key === 'PageUp' ? -1 : 1,
        );
        void moveFocus(`${month}-${date.slice(8, 10)}`);
    }
}
</script>

<template>
    <div class="select-none">
        <!-- 月份切換 -->
        <div class="mb-2 flex items-center gap-2">
            <Button
                type="button"
                size="icon"
                variant="ghost"
                class="size-8 shrink-0"
                :disabled="!canGoBack"
                aria-label="上個月"
                @click="step(-1)"
            >
                <ChevronLeft class="size-4" />
            </Button>

            <div
                class="grid flex-1 gap-4"
                :class="visibleCount > 1 ? 'md:grid-cols-2' : ''"
            >
                <div
                    v-for="(month, i) in visibleMonths"
                    :key="month"
                    class="text-center text-sm font-semibold"
                    :class="i > 0 ? 'hidden md:block' : ''"
                    aria-hidden="true"
                >
                    {{ monthLabel(month) }}
                </div>
            </div>

            <Button
                type="button"
                size="icon"
                variant="ghost"
                class="size-8 shrink-0"
                :disabled="!canGoForward"
                aria-label="下個月"
                @click="step(1)"
            >
                <ChevronRight class="size-4" />
            </Button>
        </div>

        <!-- 月曆本體 -->
        <div
            class="grid gap-4 px-8"
            :class="visibleCount > 1 ? 'md:grid-cols-2' : ''"
            @mouseleave="hovered = null"
        >
            <div
                v-for="(month, i) in visibleMonths"
                :key="month"
                :class="i > 0 ? 'hidden md:block' : ''"
                role="grid"
                :aria-label="monthLabel(month)"
            >
                <div class="grid grid-cols-7" role="row">
                    <div
                        v-for="day in WEEKDAYS"
                        :key="day"
                        class="pb-1 text-center text-xs text-muted-foreground"
                        role="columnheader"
                    >
                        {{ day }}
                    </div>
                </div>

                <div class="grid grid-cols-7">
                    <template v-for="(date, cell) in monthCells(month)">
                        <div v-if="date === null" :key="`gap-${cell}`" />

                        <div
                            v-else
                            :key="date"
                            :class="cn('py-0.5', rangeClass(date))"
                        >
                            <!--
                                用 aria-disabled 而不是 disabled：
                                disabled 的 button 不能聚焦，方向鍵走到不能選的
                                日子就會卡住。點擊無效由 select() 自己擋。
                            -->
                            <button
                                :ref="(el) => setDayRef(date!, el)"
                                type="button"
                                :aria-disabled="!isSelectable(date)"
                                :tabindex="focused === date ? 0 : -1"
                                :aria-label="date"
                                :aria-selected="dayState(date).selected"
                                :title="dayTitle(date)"
                                :class="
                                    cn(
                                        'relative flex size-9 items-center justify-center rounded-md text-sm transition-colors',
                                        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        isSelectable(date)
                                            ? 'hover:bg-primary/15'
                                            : 'cursor-default text-muted-foreground/40',
                                        dayState(date).selected &&
                                            'bg-primary font-semibold text-primary-foreground hover:bg-primary',
                                        closed.has(date) && 'line-through',
                                        date === minDate &&
                                            !dayState(date).selected &&
                                            isSelectable(date) &&
                                            'font-semibold text-primary',
                                    )
                                "
                                @click="select(date)"
                                @focus="focused = date"
                                @mouseenter="hovered = date"
                                @keydown="onKeydown($event, date)"
                            >
                                {{ Number(date.slice(8, 10)) }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div
            class="mt-3 flex flex-wrap items-center justify-between gap-2 px-8"
        >
            <p class="text-xs text-muted-foreground">
                <span v-if="closedDates.length" class="mr-3">
                    <span class="line-through">刪除線</span> 為暫停借用的日子
                </span>
                <span v-if="days !== null">
                    點一天就選定 {{ days }} 天的借用區間
                </span>
                <span v-else-if="phase === 'end' && pendingStart !== null">
                    已選取件日 {{ pendingStart }}，再點一天作為歸還日
                </span>
                <span v-else-if="startLocked">點一天設定歸還日</span>
                <span v-else>先點取件日，再點歸還日</span>
            </p>

            <div v-if="expandable" class="flex gap-1">
                <Button
                    v-if="canExpand"
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="extraMonths = visibleCount"
                >
                    <CalendarPlus class="size-4" />多顯示一個月
                </Button>
                <Button
                    v-if="extraMonths > 0"
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="extraMonths = 0"
                >
                    收合
                </Button>
            </div>
        </div>
    </div>
</template>
