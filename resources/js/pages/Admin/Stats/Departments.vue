<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InlineBar from '@/components/stats/InlineBar.vue';
import StatsRangeFilter from '@/components/stats/StatsRangeFilter.vue';
import { Badge } from '@/components/ui/badge';
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
import { departments } from '@/routes/admin/stats';
import { index as aliasesIndex } from '@/routes/admin/stats/aliases';

type Row = {
    department: string;
    times: number;
    quantity: number;
    late: number;
    last_borrow_date: string;
    spellings: string[];
};

const props = defineProps<{
    filters: { from?: string; to?: string };
    rows: Row[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: '常借單位', href: departments() }] },
});

const { filters } = useQueryFilters(departments.url(), {
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const max = computed(() => Math.max(0, ...props.rows.map((r) => r.times)));
</script>

<template>
    <Head title="常借單位" />

    <div class="space-y-4 p-4 md:p-6">
        <Heading
            title="常借單位"
            description="依借用次數排序，同一單位的不同寫法已依同義詞合併，最多列 50 筆"
        />

        <div class="flex flex-wrap items-center gap-3">
            <StatsRangeFilter
                v-model:from="filters.from"
                v-model:to="filters.to"
            />
            <Link
                :href="aliasesIndex()"
                class="text-sm text-muted-foreground underline underline-offset-4 md:ml-auto"
                >單位寫法不一致？到同義詞設定合併</Link
            >
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12">#</TableHead>
                        <TableHead>單位</TableHead>
                        <TableHead class="w-1/3">借用次數</TableHead>
                        <TableHead class="text-right">借出件數</TableHead>
                        <TableHead class="text-right">逾期次數</TableHead>
                        <TableHead>最近借用</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(r, i) in rows" :key="r.department">
                        <TableCell class="text-muted-foreground tabular-nums">{{
                            i + 1
                        }}</TableCell>
                        <TableCell>
                            <span class="font-medium">{{ r.department }}</span>
                            <Badge
                                v-if="r.spellings.length > 1"
                                variant="outline"
                                class="ml-2"
                                :title="r.spellings.join('、')"
                                >{{ r.spellings.length }} 種寫法</Badge
                            >
                        </TableCell>
                        <TableCell
                            ><InlineBar :value="r.times" :max="max"
                        /></TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            r.quantity
                        }}</TableCell>
                        <TableCell
                            class="text-right tabular-nums"
                            :class="r.late ? 'font-medium text-red-600' : ''"
                            >{{ r.late }}</TableCell
                        >
                        <TableCell class="tabular-nums">{{
                            formatDate(r.last_borrow_date)
                        }}</TableCell>
                    </TableRow>
                    <TableRow v-if="!rows.length">
                        <TableCell
                            colspan="6"
                            class="py-10 text-center text-muted-foreground"
                            >這段期間沒有借出紀錄</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
