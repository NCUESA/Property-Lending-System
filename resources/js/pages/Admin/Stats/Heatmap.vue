<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InlineBar from '@/components/stats/InlineBar.vue';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { heatmap } from '@/routes/admin/stats';

type Day = { requests: number; items: number };

const props = defineProps<{
    year: number;
    years: number[];
    days: Record<string, Day>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: '借用量熱力圖', href: heatmap() }] },
});

const WEEKDAYS = ['一', '二', '三', '四', '五', '六', '日'];

/** 日期一律用 UTC 計算，避免時區讓某天跑到前一天 */
const iso = (d: Date) => d.toISOString().slice(0, 10);

type Cell = { date: string; month: number; inYear: boolean; value: Day | null };

/** 週一開頭，一欄一週，從包含 1/1 的那週排到包含 12/31 的那週 */
const weeks = computed<Cell[][]>(() => {
    const first = new Date(Date.UTC(props.year, 0, 1));
    const start = new Date(first);
    start.setUTCDate(first.getUTCDate() - ((first.getUTCDay() + 6) % 7));
    const last = new Date(Date.UTC(props.year, 11, 31));

    const result: Cell[][] = [];

    for (const d = new Date(start); d <= last;) {
        const week: Cell[] = [];

        for (let i = 0; i < 7; i++) {
            const date = iso(d);
            week.push({
                date,
                month: d.getUTCMonth(),
                inYear: d.getUTCFullYear() === props.year,
                value: props.days[date] ?? null,
            });
            d.setUTCDate(d.getUTCDate() + 1);
        }

        result.push(week);
    }

    return result;
});

/** 月份標籤放在該月 1 號所在的那一欄 */
const monthLabels = computed(() =>
    weeks.value.map((week) => {
        const firstOfMonth = week.find(
            (c) => c.inYear && c.date.endsWith('-01'),
        );

        return firstOfMonth ? `${firstOfMonth.month + 1}月` : '';
    }),
);

const max = computed(() =>
    Math.max(0, ...Object.values(props.days).map((d) => d.requests)),
);

/** 0 = 沒有借用；1–4 依當年最大值等分 */
function level(cell: Cell): number {
    if (!cell.value || max.value === 0) {
        return 0;
    }

    return Math.min(4, Math.ceil((cell.value.requests / max.value) * 4));
}

// 單一色相、淺到深（dataviz 預設 sequential 藍）；深色模式另取一組，由暗到亮
const levelClasses = [
    'bg-muted',
    'bg-[#cde2fb] dark:bg-[#104281]',
    'bg-[#86b6ef] dark:bg-[#1c5cab]',
    'bg-[#3987e5] dark:bg-[#3987e5]',
    'bg-[#1c5cab] dark:bg-[#86b6ef]',
];

const totals = computed(() => {
    const entries = Object.entries(props.days);
    const byMonth = Array.from({ length: 12 }, () => ({
        requests: 0,
        items: 0,
    }));

    for (const [date, d] of entries) {
        const m = Number(date.slice(5, 7)) - 1;
        byMonth[m].requests += d.requests;
        byMonth[m].items += d.items;
    }

    const busiestDay = entries.reduce<[string, Day] | null>(
        (best, cur) =>
            !best || cur[1].requests > best[1].requests ? cur : best,
        null,
    );
    const busiestMonth = byMonth.reduce(
        (best, cur, i) => (cur.requests > byMonth[best].requests ? i : best),
        0,
    );

    return {
        requests: byMonth.reduce((s, m) => s + m.requests, 0),
        items: byMonth.reduce((s, m) => s + m.items, 0),
        activeDays: entries.length,
        busiestDay,
        busiestMonth: byMonth[busiestMonth].requests ? busiestMonth : null,
        byMonth,
    };
});

const monthMax = computed(() =>
    Math.max(0, ...totals.value.byMonth.map((m) => m.requests)),
);

// 滑過格子時的提示
const wrapper = ref<HTMLElement | null>(null);
const hover = ref<{ cell: Cell; x: number; y: number } | null>(null);

function showTip(cell: Cell, event: MouseEvent | FocusEvent) {
    const target = event.currentTarget as HTMLElement;
    const box = wrapper.value?.getBoundingClientRect();
    const rect = target.getBoundingClientRect();

    if (!box) {
        return;
    }

    hover.value = {
        cell,
        x:
            rect.left -
            box.left +
            rect.width / 2 +
            (wrapper.value?.scrollLeft ?? 0),
        y: rect.top - box.top,
    };
}

function weekdayOf(date: string): string {
    return WEEKDAYS[(new Date(`${date}T00:00:00Z`).getUTCDay() + 6) % 7];
}

function changeYear(event: Event) {
    router.get(
        heatmap.url(),
        { year: (event.target as HTMLSelectElement).value },
        { preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head title="借用量熱力圖" />

    <div class="space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="借用量熱力圖"
                description="每一格是一天，顏色越深代表當天借出的借用單越多（依借用日）"
            />
            <select
                :value="year"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                aria-label="年份"
                @change="changeYear"
            >
                <option v-for="y in years" :key="y" :value="y">
                    {{ y }} 年
                </option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <div class="text-2xl font-semibold tabular-nums">
                        {{ totals.requests }}
                    </div>
                    <div class="text-xs text-muted-foreground">借用單</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="text-2xl font-semibold tabular-nums">
                        {{ totals.items }}
                    </div>
                    <div class="text-xs text-muted-foreground">借出件數</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="text-lg font-semibold tabular-nums">
                        <template v-if="totals.busiestDay"
                            >{{ totals.busiestDay[0].slice(5) }}（{{
                                totals.busiestDay[1].requests
                            }}
                            張）</template
                        >
                        <template v-else>—</template>
                    </div>
                    <div class="text-xs text-muted-foreground">最忙的一天</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="text-lg font-semibold tabular-nums">
                        <template v-if="totals.busiestMonth !== null"
                            >{{ totals.busiestMonth + 1 }} 月（{{
                                totals.byMonth[totals.busiestMonth].requests
                            }}
                            張）</template
                        >
                        <template v-else>—</template>
                    </div>
                    <div class="text-xs text-muted-foreground">最忙的月份</div>
                </CardContent>
            </Card>
        </div>

        <div class="rounded-lg border p-4">
            <div
                ref="wrapper"
                class="relative overflow-x-auto pb-2"
                @mouseleave="hover = null"
            >
                <div class="inline-flex gap-2">
                    <div
                        class="mt-5 grid grid-rows-7 gap-[2px] text-[10px] leading-3 text-muted-foreground"
                    >
                        <span v-for="(w, i) in WEEKDAYS" :key="w" class="h-3">{{
                            i % 2 === 0 ? w : ''
                        }}</span>
                    </div>
                    <div>
                        <div
                            class="flex h-5 gap-[2px] text-[10px] text-muted-foreground"
                        >
                            <span
                                v-for="(label, i) in monthLabels"
                                :key="i"
                                class="w-3 overflow-visible whitespace-nowrap"
                                >{{ label }}</span
                            >
                        </div>
                        <div class="flex gap-[2px]">
                            <div
                                v-for="(week, wi) in weeks"
                                :key="wi"
                                class="grid grid-rows-7 gap-[2px]"
                            >
                                <template v-for="cell in week" :key="cell.date">
                                    <button
                                        v-if="cell.inYear"
                                        type="button"
                                        class="size-3 rounded-[2px] outline-offset-1 hover:outline hover:outline-1 hover:outline-foreground focus-visible:outline focus-visible:outline-2"
                                        :class="levelClasses[level(cell)]"
                                        :aria-label="`${cell.date}：${cell.value?.requests ?? 0} 張借用單`"
                                        @mouseenter="showTip(cell, $event)"
                                        @focus="showTip(cell, $event)"
                                        @blur="hover = null"
                                    />
                                    <span v-else class="size-3" />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hover"
                    class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap text-popover-foreground shadow-md"
                    :style="{ left: `${hover.x}px`, top: `${hover.y - 4}px` }"
                >
                    <div class="font-medium tabular-nums">
                        {{ hover.cell.date }}（{{
                            weekdayOf(hover.cell.date)
                        }}）
                    </div>
                    <div v-if="hover.cell.value" class="tabular-nums">
                        {{ hover.cell.value.requests }} 張借用單 ·
                        {{ hover.cell.value.items }} 件
                    </div>
                    <div v-else class="text-muted-foreground">沒有借用</div>
                </div>
            </div>

            <div
                class="mt-2 flex items-center justify-end gap-1 text-xs text-muted-foreground"
            >
                少
                <span
                    v-for="(cls, i) in levelClasses"
                    :key="i"
                    class="size-3 rounded-[2px]"
                    :class="cls"
                />
                多
                <span class="ml-2">（最深一格＝單日 {{ max }} 張）</span>
            </div>
        </div>

        <section class="space-y-2">
            <h2 class="font-semibold">每月合計</h2>
            <div class="overflow-x-auto rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-16">月份</TableHead>
                            <TableHead class="w-1/2">借用單</TableHead>
                            <TableHead class="text-right">借出件數</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(m, i) in totals.byMonth" :key="i">
                            <TableCell>{{ i + 1 }} 月</TableCell>
                            <TableCell
                                ><InlineBar :value="m.requests" :max="monthMax"
                            /></TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                m.items
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </section>
    </div>
</template>
