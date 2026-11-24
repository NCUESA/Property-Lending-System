<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InlineBar from '@/components/stats/InlineBar.vue';
import StatsRangeFilter from '@/components/stats/StatsRangeFilter.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { formatDate } from '@/lib/format';
import { overdue } from '@/routes/admin/stats';

type By = 'department' | 'person';

type Row = {
    name: string;
    phone: string | null;
    department: string | null;
    times: number;
    ongoing: number;
    avg_days: number;
    max_days: number;
    last_due_date: string;
};

const props = defineProps<{
    filters: { from?: string; to?: string; by: By };
    rows: Row[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: '逾期排行', href: overdue() }] },
});

const { filters } = useQueryFilters(overdue.url(), {
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    by: props.filters.by,
});

const tabs: { value: By; label: string }[] = [
    { value: 'department', label: '依單位' },
    { value: 'person', label: '依個人' },
];

const max = computed(() => Math.max(0, ...props.rows.map((r) => r.times)));
</script>

<template>
    <Head title="逾期排行" />

    <div class="space-y-4 p-4 md:p-6">
        <Heading
            title="逾期排行"
            description="逾期未還與逾期歸還的借用單，依次數排序；個人以姓名＋電話區分，最多列 50 筆"
        />

        <div class="flex flex-wrap items-center gap-3">
            <StatsRangeFilter
                v-model:from="filters.from"
                v-model:to="filters.to"
            />
            <div class="flex gap-1 rounded-lg border p-1 md:ml-auto">
                <Button
                    v-for="t in tabs"
                    :key="t.value"
                    size="sm"
                    :variant="filters.by === t.value ? 'secondary' : 'ghost'"
                    @click="filters.by = t.value"
                    >{{ t.label }}</Button
                >
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12">#</TableHead>
                        <TableHead>{{
                            filters.by === 'person' ? '借用人' : '單位'
                        }}</TableHead>
                        <TableHead class="w-1/3">逾期次數</TableHead>
                        <TableHead class="text-right">平均逾期天數</TableHead>
                        <TableHead class="text-right">最久</TableHead>
                        <TableHead>最近一次應還日</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(r, i) in rows"
                        :key="`${r.name}-${r.phone ?? ''}`"
                    >
                        <TableCell class="text-muted-foreground tabular-nums">{{
                            i + 1
                        }}</TableCell>
                        <TableCell>
                            <span class="font-medium">{{ r.name }}</span>
                            <Badge
                                v-if="r.ongoing"
                                variant="destructive"
                                class="ml-2"
                                >{{ r.ongoing }} 張未還</Badge
                            >
                            <div
                                v-if="r.phone || r.department"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    [r.phone, r.department]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </div>
                        </TableCell>
                        <TableCell
                            ><InlineBar :value="r.times" :max="max"
                        /></TableCell>
                        <TableCell class="text-right tabular-nums"
                            >{{ r.avg_days }} 天</TableCell
                        >
                        <TableCell class="text-right tabular-nums"
                            >{{ r.max_days }} 天</TableCell
                        >
                        <TableCell class="tabular-nums">{{
                            formatDate(r.last_due_date)
                        }}</TableCell>
                    </TableRow>
                    <TableRow v-if="!rows.length">
                        <TableCell
                            colspan="6"
                            class="py-10 text-center text-muted-foreground"
                            >這段期間沒有逾期紀錄</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
