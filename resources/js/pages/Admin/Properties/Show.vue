<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ImageOff, Pencil } from '@lucide/vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { show as borrowShow } from '@/routes/admin/borrows';
import { edit, index } from '@/routes/admin/properties';
import type {
    BorrowItemStatus,
    BorrowRequestStatus,
    Paginator,
    Property,
} from '@/types';

type HistoryRow = {
    id: number;
    status: BorrowRequestStatus;
    item_status: BorrowItemStatus | null;
    borrower_department: string;
    borrower_name: string;
    phone: string;
    borrow_date: string;
    due_date: string;
    lent_on: string | null;
    returned_on: string | null;
    lender: string | null;
    returner: string | null;
    extended: boolean;
    late: boolean;
};

const props = defineProps<{
    property: Property;
    current: HistoryRow | null;
    stats: {
        times: number;
        days: number;
        late: number;
        last_lent_on: string | null;
    };
    history: Paginator<HistoryRow>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: '器材', href: index() }] },
});

const details: [string, string | number | null][] = [
    [
        '分類',
        props.property.subcategory
            ? `${props.property.subcategory.category.code} ${props.property.subcategory.category.name} / ${props.property.subcategory.name}`
            : null,
    ],
    ['存放地點', props.property.location?.name ?? null],
    ['規格', props.property.spec],
    ['保管單位', props.property.department],
    ['保管人', props.property.custodian],
    ['取得日期', props.property.acquired_on],
    [
        '價格',
        props.property.price !== null
            ? `${props.property.price.toLocaleString()} 元`
            : null,
    ],
    ['備註', props.property.remark],
];
</script>

<template>
    <Head :title="`${property.ssid} ${property.name}`" />

    <div class="space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Button variant="ghost" size="icon" as-child>
                <Link :href="index()" aria-label="回列表"
                    ><ArrowLeft class="size-4"
                /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                <span class="mr-2 font-mono">{{ property.ssid }}</span
                >{{ property.name }}
            </h1>
            <Badge v-if="!property.is_lendable" variant="outline"
                >不開放借用</Badge
            >
            <Badge v-if="property.is_school_owned" variant="outline"
                >校產</Badge
            >
            <Badge v-if="property.pool" variant="secondary"
                >大量借用：{{ property.pool.name }}</Badge
            >
            <Button variant="outline" size="sm" class="ml-auto" as-child>
                <Link :href="edit(property.id)"><Pencil />編輯</Link>
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-[12rem_1fr_1fr]">
            <div
                class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border bg-muted"
            >
                <img
                    v-if="property.image_url"
                    :src="property.image_url"
                    :alt="property.name"
                    class="size-full object-cover"
                />
                <ImageOff v-else class="size-8 text-muted-foreground" />
            </div>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >基本資料</CardTitle
                    ></CardHeader
                >
                <CardContent>
                    <dl
                        class="grid grid-cols-[5rem_1fr] gap-x-3 gap-y-1 text-sm"
                    >
                        <template
                            v-for="[label, value] in details"
                            :key="label"
                        >
                            <dt class="text-muted-foreground">{{ label }}</dt>
                            <dd class="break-words">{{ value ?? '—' }}</dd>
                        </template>
                    </dl>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >借用統計</CardTitle
                    ></CardHeader
                >
                <CardContent class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-2xl font-semibold tabular-nums">
                            {{ stats.times }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            借出次數
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold tabular-nums">
                            {{ stats.days }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            累計借出天數
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-2xl font-semibold tabular-nums"
                            :class="stats.late ? 'text-red-600' : ''"
                        >
                            {{ stats.late }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            逾期次數
                        </div>
                    </div>
                    <div>
                        <div class="text-lg font-semibold tabular-nums">
                            {{ formatDate(stats.last_lent_on) }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            最近一次借出
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card
            v-if="current"
            :class="
                current.status === 'overdue'
                    ? 'border-red-300 dark:border-red-900'
                    : 'border-amber-300 dark:border-amber-900'
            "
        >
            <CardHeader class="flex flex-row flex-wrap items-center gap-2">
                <CardTitle class="text-base">目前借用</CardTitle>
                <StatusBadge kind="request" :status="current.status" />
                <Link
                    :href="borrowShow(current.id)"
                    class="ml-auto text-sm font-medium underline underline-offset-4"
                    >借用單 #{{ current.id }}</Link
                >
            </CardHeader>
            <CardContent class="grid gap-x-6 gap-y-1 text-sm md:grid-cols-3">
                <div>
                    <div class="font-medium">
                        {{ current.borrower_department }}
                    </div>
                    <div>{{ current.borrower_name }}</div>
                    <div class="tabular-nums">{{ current.phone }}</div>
                </div>
                <div class="tabular-nums">
                    <div>借用日：{{ formatDate(current.borrow_date) }}</div>
                    <div
                        :class="current.late ? 'font-medium text-red-600' : ''"
                    >
                        歸還日：{{ formatDate(current.due_date) }}
                        <Badge
                            v-if="current.extended"
                            variant="outline"
                            class="ml-1"
                            >展延過</Badge
                        >
                    </div>
                </div>
                <div>
                    <template v-if="current.lent_on">
                        <div class="tabular-nums">
                            出借：{{ formatDate(current.lent_on) }}
                        </div>
                        <div>經手：{{ current.lender ?? '—' }}</div>
                    </template>
                    <div v-else class="text-muted-foreground">尚未取件</div>
                </div>
            </CardContent>
        </Card>

        <p
            v-if="property.pool"
            class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground"
        >
            這件器材屬於大量借用品項「{{
                property.pool.name
            }}」，借用時是整池按數量借出、不指定單件，所以加入品項池之後的借用不會出現在下面的紀錄裡。
        </p>

        <section class="space-y-3">
            <h2 class="font-semibold">
                借用紀錄
                <span class="text-sm font-normal text-muted-foreground"
                    >共 {{ history.total }} 筆</span
                >
            </h2>
            <div class="overflow-x-auto rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>單號</TableHead>
                            <TableHead>借用人</TableHead>
                            <TableHead>借用期間</TableHead>
                            <TableHead>實際出借／歸還</TableHead>
                            <TableHead>經手人</TableHead>
                            <TableHead>這件器材</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="h in history.data" :key="h.id">
                            <TableCell>
                                <Link
                                    :href="borrowShow(h.id)"
                                    class="font-mono underline-offset-4 hover:underline"
                                    >#{{ h.id }}</Link
                                >
                            </TableCell>
                            <TableCell>
                                <div class="font-medium">
                                    {{ h.borrower_department }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ h.borrower_name }} · {{ h.phone }}
                                </div>
                            </TableCell>
                            <TableCell class="text-sm tabular-nums">
                                {{ formatDate(h.borrow_date) }} ～
                                {{ formatDate(h.due_date) }}
                            </TableCell>
                            <TableCell class="text-sm tabular-nums">
                                {{ formatDate(h.lent_on) }} ～
                                {{ formatDate(h.returned_on) }}
                            </TableCell>
                            <TableCell class="text-sm">
                                <div>出借：{{ h.lender ?? '—' }}</div>
                                <div class="text-muted-foreground">
                                    歸還：{{ h.returner ?? '—' }}
                                </div>
                            </TableCell>
                            <TableCell class="space-x-1">
                                <StatusBadge
                                    v-if="h.item_status"
                                    kind="item"
                                    :status="h.item_status"
                                />
                                <Badge v-if="h.late" variant="destructive"
                                    >逾期</Badge
                                >
                                <Badge v-if="h.extended" variant="outline"
                                    >展延</Badge
                                >
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!history.data.length">
                            <TableCell
                                colspan="6"
                                class="py-10 text-center text-muted-foreground"
                                >還沒有借用紀錄</TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <PaginationLinks :paginator="history" />
        </section>
    </div>
</template>
