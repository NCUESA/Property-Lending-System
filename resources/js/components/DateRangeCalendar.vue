<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { addDays } from '@/lib/format';
import { cn } from '@/lib/utils';

/*
 * Booking 式的區間日曆：一次把日子攤開讓使用者點「取件日 → 歸還日」，
 * 取代兩個 <input type="date">。
 *
 * 日期一律用 YYYY-MM-DD 字串運算（和 lib/format 一致）：
 * 字典序就是時間序，比較大小不必轉成 Date，也不會踩到時區。
 * 同樣不使用 Intl.DateTimeFormat，避免 SSR 與瀏覽器的 ICU 差異造成 hydration mismatch。
 */

const props = withDefaults(
    defineProps<{
        /** 取件日，YYYY-MM-DD，未選為 null */
        start: string | null;
        /** 歸還日，YYYY-MM-DD，未選為 null */
        end: string | null;
        /** 最早可選的日子（通常是今天） */
        minDate: string;
        /** 最晚可當取件日的日子；不開放預借時等於 minDate */
        lastBorrowDate: string;
        /** 最晚可當歸還日的日子 */
        lastDate: string;
        /** 最長借用天數，含取件當天 */
        maxDays: number;
        /** 暫停借用的日子，不能當取件日或歸還日 */
        closedDates?: string[];
        /** 同時顯示幾個月（視窗較窄時只會顯示第一個） */
        months?: number;
    }>(),
    { closedDates: () => [], months: 2 },
);

const emit = defineEmits<{
    'update:start': [value: string | null];
    'update:end': [value: string | null];
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

/** 還沒選歸還日 → 現在點的是歸還日。 */
const picking = computed(() => props.start !== null && props.end === null);

/** 選定取件日後，最晚能還的日子。 */
const latestEnd = computed(() =>
    props.start === null
        ? null
        : [addDays(props.start, props.maxDays - 1), props.lastDate].sort()[0],
);

function isStartable(date: string): boolean {
    return (
        date >= props.minDate &&
        date <= props.lastBorrowDate &&
        !closed.value.has(date)
    );
}

function isEndable(date: string): boolean {
    return (
        props.start !== null &&
        latestEnd.value !== null &&
        date >= props.start &&
        date <= latestEnd.value &&
        !closed.value.has(date)
    );
}

/** 點不動的日子：既不能當取件日也不能當歸還日。 */
function isDisabled(date: string): boolean {
    return !isStartable(date) && !isEndable(date);
}

function select(date: string) {
    // 選歸還日優先：取件日已選時，點在合法區間內就是收尾
    if (picking.value && isEndable(date)) {
        emit('update:end', date);

        return;
    }

    // 其餘情況一律重新開始選，不讓使用者卡在「點了沒反應」
    if (isStartable(date)) {
        emit('update:start', date);
        emit('update:end', null);
    }
}

/* ── 顯示狀態 ─────────────────────────────────────────────── */

const hovered = ref<string | null>(null);

/** 選取中時用滑鼠停留的位置預覽區間，和 Booking 一樣。 */
const previewEnd = computed(() => {
    if (!picking.value) return props.end;

    return hovered.value !== null && isEndable(hovered.value)
        ? hovered.value
        : null;
});

type DayState = {
    selected: boolean;
    inRange: boolean;
    isStart: boolean;
    isEnd: boolean;
};

function dayState(date: string): DayState {
    const from = props.start;
    const to = previewEnd.value;
    const isStart = from === date;
    const isEnd = to === date;

    return {
        selected: isStart || isEnd,
        inRange: from !== null && to !== null && date > from && date < to,
        isStart,
        isEnd,
    };
}

/** 區間底色畫在外層容器上，格子才連得起來（頭尾各切一邊圓角）。 */
function rangeClass(date: string): string {
    const { inRange, isStart, isEnd } = dayState(date);
    const spans =
        props.start !== null &&
        previewEnd.value !== null &&
        props.start !== previewEnd.value;

    if (inRange) return 'bg-accent';
    if (!spans) return '';
    if (isStart) return 'rounded-l-md bg-accent';
    if (isEnd) return 'rounded-r-md bg-accent';

    return '';
}

function dayTitle(date: string): string | undefined {
    if (closed.value.has(date)) return '暫停借用';
    if (isDisabled(date)) return undefined;
    if (picking.value && isEndable(date)) return '設為歸還日';

    return '設為借用日';
}

/* ── 月份切換 ─────────────────────────────────────────────── */

const firstMonth = ref(monthOf(props.start ?? props.minDate));

const visibleMonths = computed(() =>
    Array.from({ length: props.months }, (_, i) =>
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
        firstMonth.value = shiftMonth(month, -(props.months - 1));
    }
}

watch(
    () => props.start,
    (value) => {
        if (value) reveal(value);
    },
);

/* ── 鍵盤操作：整張日曆只有一個 tab stop，方向鍵在格子間移動 ──── */

const focused = ref(props.start ?? props.minDate);
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
                :class="months > 1 ? 'md:grid-cols-2' : ''"
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
            :class="months > 1 ? 'md:grid-cols-2' : ''"
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
                                disabled 的 button 不能聚焦，方向鍵走到暫停借用的
                                日子就會卡住。點擊無效由 select() 自己擋。
                            -->
                            <button
                                :ref="(el) => setDayRef(date!, el)"
                                type="button"
                                :aria-disabled="isDisabled(date)"
                                :tabindex="focused === date ? 0 : -1"
                                :aria-label="date"
                                :aria-selected="dayState(date).selected"
                                :title="dayTitle(date)"
                                :class="
                                    cn(
                                        'relative flex size-9 items-center justify-center rounded-md text-sm transition-colors',
                                        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        isDisabled(date)
                                            ? 'cursor-default text-muted-foreground/40'
                                            : 'hover:bg-primary/15',
                                        dayState(date).selected &&
                                            'bg-primary font-semibold text-primary-foreground hover:bg-primary',
                                        closed.has(date) && 'line-through',
                                        date === minDate &&
                                            !dayState(date).selected &&
                                            !isDisabled(date) &&
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

        <!-- 圖例 -->
        <p class="mt-3 px-8 text-xs text-muted-foreground">
            <span v-if="closedDates.length" class="mr-3">
                <span class="line-through">刪除線</span> 為暫停借用的日子
            </span>
            <span>最長 {{ maxDays }} 天（含借用當天）</span>
        </p>
    </div>
</template>
