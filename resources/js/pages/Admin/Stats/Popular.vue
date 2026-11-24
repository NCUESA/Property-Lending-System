<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
import { show as propertyShow } from '@/routes/admin/properties';
import { popular } from '@/routes/admin/stats';

type Level = 'category' | 'subcategory' | 'property';

type Row = {
    kind: 'property' | 'pool';
    key: number;
    label: string;
    detail: string;
    times: number;
    quantity: number;
    item_days: number;
    property_id: number | null;
};

const props = defineProps<{
    filters: { from?: string; to?: string; level: Level };
    rows: Row[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: '熱門清單', href: popular() }] },
});

const { filters } = useQueryFilters(popular.url(), {
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    level: props.filters.level,
});

const levels: { value: Level; label: string }[] = [
    { value: 'category', label: '類別' },
    { value: 'subcategory', label: '子類別' },
    { value: 'property', label: '單件器材' },
];

const max = computed(() => Math.max(0, ...props.rows.map((r) => r.times)));
</script>

<template>
    <Head title="熱門清單" />

    <div class="space-y-4 p-4 md:p-6">
        <Heading
            title="熱門清單"
            description="依借出次數排序（只算真的借出去的，退回、未取件不算），最多列 50 筆"
        />

        <div class="flex flex-wrap items-center gap-3">
            <StatsRangeFilter
                v-model:from="filters.from"
                v-model:to="filters.to"
            />
            <div class="flex gap-1 rounded-lg border p-1 md:ml-auto">
                <Button
                    v-for="l in levels"
                    :key="l.value"
                    size="sm"
                    :variant="filters.level === l.value ? 'secondary' : 'ghost'"
                    @click="filters.level = l.value"
                    >{{ l.label }}</Button
                >
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12">#</TableHead>
                        <TableHead>名稱</TableHead>
                        <TableHead class="w-1/3">借出次數</TableHead>
                        <TableHead class="text-right">件數</TableHead>
                        <TableHead class="text-right" title="件數 × 借出天數"
                            >累計件天</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(r, i) in rows"
                        :key="`${r.kind}-${r.key}`"
                    >
                        <TableCell class="text-muted-foreground tabular-nums">{{
                            i + 1
                        }}</TableCell>
                        <TableCell>
                            <Link
                                v-if="r.property_id"
                                :href="propertyShow(r.property_id)"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ r.label }}</Link
                            >
                            <span v-else class="font-medium">{{
                                r.label
                            }}</span>
                            <Badge
                                v-if="r.kind === 'pool'"
                                variant="secondary"
                                class="ml-2"
                                >大量借用</Badge
                            >
                            <div
                                v-if="r.detail && r.kind !== 'pool'"
                                class="text-xs text-muted-foreground"
                            >
                                {{ r.detail }}
                            </div>
                        </TableCell>
                        <TableCell
                            ><InlineBar :value="r.times" :max="max"
                        /></TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            r.quantity
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            r.item_days
                        }}</TableCell>
                    </TableRow>
                    <TableRow v-if="!rows.length">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                            >這段期間沒有借出紀錄</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
