<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { index, show } from '@/routes/admin/borrows';
import type {
    BorrowOverview,
    BorrowRequestStatus,
    Option,
    Paginator,
} from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: '借用單', href: index() }] },
});

const props = defineProps<{
    filters: {
        status?: BorrowRequestStatus[];
        location_id?: number;
        q?: string;
        from?: string;
        to?: string;
    };
    borrows: Paginator<BorrowOverview>;
    statuses: Option<BorrowRequestStatus>[];
    locations: { id: number; name: string }[];
}>();

const { filters, reset } = useQueryFilters(index.url(), {
    status: props.filters.status ?? ([] as BorrowRequestStatus[]),
    location_id: props.filters.location_id
        ? String(props.filters.location_id)
        : '',
    q: props.filters.q ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

function toggleStatus(value: BorrowRequestStatus) {
    filters.status = filters.status.includes(value)
        ? filters.status.filter((s) => s !== value)
        : [...filters.status, value];
}

// 常用：待處理（申請中 + 逾期未還）
function quickFilter(values: BorrowRequestStatus[]) {
    filters.status = values;
}

const hasFilters = () =>
    filters.status.length ||
    filters.location_id ||
    filters.q ||
    filters.from ||
    filters.to;
</script>

<template>
    <Head title="借用單" />

    <div class="space-y-4 p-4 md:p-6">
        <Heading title="借用單" description="處理借用申請、出借與歸還" />

        <div class="flex flex-wrap gap-2">
            <Button
                size="sm"
                variant="outline"
                @click="quickFilter(['pending'])"
                >待出借</Button
            >
            <Button
                size="sm"
                variant="outline"
                @click="quickFilter(['lent', 'partially_returned', 'overdue'])"
                >借出中</Button
            >
            <Button
                size="sm"
                variant="outline"
                @click="quickFilter(['overdue'])"
                >逾期未還</Button
            >
        </div>

        <div class="space-y-3 rounded-lg border p-3">
            <div class="flex flex-col gap-3 md:flex-row">
                <div class="relative flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="filters.q"
                        placeholder="單號、姓名、單位、電話、財產編號"
                        class="pl-9"
                    />
                </div>
                <select
                    v-model="filters.location_id"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    aria-label="地點"
                >
                    <option value="">全部地點</option>
                    <option
                        v-for="loc in locations"
                        :key="loc.id"
                        :value="String(loc.id)"
                    >
                        {{ loc.name }}
                    </option>
                </select>
                <div class="flex items-center gap-2 text-sm">
                    <Input
                        v-model="filters.from"
                        type="date"
                        class="w-auto"
                        aria-label="借用日起"
                    />
                    <span class="text-muted-foreground">至</span>
                    <Input
                        v-model="filters.to"
                        type="date"
                        class="w-auto"
                        aria-label="借用日迄"
                    />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-for="s in statuses"
                    :key="s.value"
                    size="sm"
                    :variant="
                        filters.status.includes(s.value) ? 'default' : 'outline'
                    "
                    @click="toggleStatus(s.value)"
                >
                    {{ s.label }}
                </Button>
                <Button
                    v-if="hasFilters()"
                    size="sm"
                    variant="ghost"
                    @click="reset"
                    ><X class="mr-1 size-4" />清除篩選</Button
                >
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-16">單號</TableHead>
                        <TableHead>借用單位／聯絡人</TableHead>
                        <TableHead>電話</TableHead>
                        <TableHead>地點</TableHead>
                        <TableHead>借用日</TableHead>
                        <TableHead>歸還日</TableHead>
                        <TableHead class="text-right">件數</TableHead>
                        <TableHead>狀態</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="b in borrows.data"
                        :key="b.id"
                        class="cursor-pointer"
                        tabindex="0"
                        @click="router.visit(show(b.id))"
                        @keydown.enter="router.visit(show(b.id))"
                    >
                        <TableCell class="font-mono text-xs"
                            >#{{ b.id }}</TableCell
                        >
                        <TableCell>
                            <div class="font-medium">
                                {{ b.borrower_department }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ b.borrower_name }}
                            </div>
                        </TableCell>
                        <TableCell class="tabular-nums">{{
                            b.phone
                        }}</TableCell>
                        <TableCell>{{ b.location?.name }}</TableCell>
                        <TableCell class="tabular-nums">{{
                            formatDate(b.borrow_date)
                        }}</TableCell>
                        <TableCell
                            class="tabular-nums"
                            :class="
                                b.status === 'overdue'
                                    ? 'font-medium text-red-600'
                                    : ''
                            "
                            >{{ formatDate(b.due_date) }}</TableCell
                        >
                        <TableCell class="text-right tabular-nums">{{
                            b.item_count - b.cancelled_count
                        }}</TableCell>
                        <TableCell
                            ><StatusBadge kind="request" :status="b.status"
                        /></TableCell>
                    </TableRow>
                    <TableRow v-if="!borrows.data.length">
                        <TableCell
                            colspan="8"
                            class="py-10 text-center text-muted-foreground"
                            >沒有符合條件的借用單</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <PaginationLinks :paginator="borrows" />
    </div>
</template>
