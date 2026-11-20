<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ImageOff, Lock, Search } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { status } from '@/routes/properties';
import type { BorrowItemStatus, Paginator } from '@/types';

type Row = {
    id: number;
    ssid: string;
    name: string;
    second_name: string | null;
    spec: string | null;
    category: string;
    location: string;
    image_url: string | null;
    restricted: boolean;
    borrow: {
        status: BorrowItemStatus;
        department: string;
        borrow_date: string;
        due_date: string;
    } | null;
};

const props = defineProps<{
    filters: { location?: string; q?: string; availability?: string };
    locations: { id: number; code: string; name: string }[];
    properties: Paginator<Row>;
    pools: {
        id: number;
        name: string;
        location: string;
        available: number;
        total: number;
    }[];
}>();

const { filters } = useQueryFilters(status.url(), {
    location: props.filters.location ?? '',
    q: props.filters.q ?? '',
    availability: props.filters.availability ?? '',
});

const availabilityOptions = [
    { value: '', label: '全部' },
    { value: 'available', label: '可借' },
    { value: 'borrowed', label: '借出中' },
];
</script>

<template>
    <Head title="器材狀態" />

    <Heading
        title="器材狀態"
        description="即時查詢器材是否可借，以及目前的借用單位與預計歸還日"
    />

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center">
        <div class="relative flex-1">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="filters.q"
                placeholder="搜尋名稱或財產編號"
                class="pl-9"
            />
        </div>
        <div class="flex flex-wrap gap-2">
            <Button
                size="sm"
                :variant="filters.location === '' ? 'default' : 'outline'"
                @click="filters.location = ''"
                >全部地點</Button
            >
            <Button
                v-for="loc in locations"
                :key="loc.id"
                size="sm"
                :variant="filters.location === loc.code ? 'default' : 'outline'"
                @click="filters.location = loc.code"
            >
                {{ loc.name }}
            </Button>
        </div>
        <div class="flex gap-2">
            <Button
                v-for="opt in availabilityOptions"
                :key="opt.value"
                size="sm"
                :variant="
                    filters.availability === opt.value ? 'secondary' : 'ghost'
                "
                @click="filters.availability = opt.value"
            >
                {{ opt.label }}
            </Button>
        </div>
    </div>

    <section v-if="pools.length" class="mb-6">
        <h3 class="mb-2 text-sm font-medium text-muted-foreground">
            依數量借用的品項
        </h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Card v-for="pool in pools" :key="pool.id" class="py-4">
                <CardContent class="px-4">
                    <div class="font-medium">{{ pool.name }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ pool.location }}
                    </div>
                    <div class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ pool.available
                        }}<span
                            class="text-sm font-normal text-muted-foreground"
                        >
                            / {{ pool.total }}</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>
    </section>

    <!-- 桌機：表格 -->
    <div class="hidden overflow-x-auto rounded-lg border md:block">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-14"></TableHead>
                    <TableHead>財產編號</TableHead>
                    <TableHead>名稱</TableHead>
                    <TableHead>分類</TableHead>
                    <TableHead>地點</TableHead>
                    <TableHead>狀態</TableHead>
                    <TableHead>借用單位</TableHead>
                    <TableHead>預計歸還</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="p in properties.data" :key="p.id">
                    <TableCell>
                        <div
                            class="flex size-10 items-center justify-center overflow-hidden rounded bg-muted"
                        >
                            <img
                                v-if="p.image_url"
                                :src="p.image_url"
                                :alt="p.name"
                                class="size-full object-cover"
                                loading="lazy"
                            />
                            <ImageOff
                                v-else
                                class="size-4 text-muted-foreground"
                            />
                        </div>
                    </TableCell>
                    <TableCell class="font-mono text-xs">{{
                        p.ssid
                    }}</TableCell>
                    <TableCell>
                        <div class="flex items-center gap-1 font-medium">
                            {{ p.name }}
                            <Lock
                                v-if="p.restricted"
                                class="size-3.5 text-amber-600"
                                aria-label="需授權"
                            />
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{
                                [p.second_name, p.spec]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </div>
                    </TableCell>
                    <TableCell>{{ p.category }}</TableCell>
                    <TableCell>{{ p.location }}</TableCell>
                    <TableCell>
                        <Badge
                            v-if="!p.borrow"
                            class="border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                            >可借</Badge
                        >
                        <Badge
                            v-else-if="p.borrow.status === 'pending'"
                            class="border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200"
                            >申請中</Badge
                        >
                        <Badge
                            v-else
                            class="border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                            >借出中</Badge
                        >
                    </TableCell>
                    <TableCell>{{ p.borrow?.department ?? '—' }}</TableCell>
                    <TableCell class="tabular-nums">{{
                        p.borrow?.due_date ?? '—'
                    }}</TableCell>
                </TableRow>
                <TableRow v-if="!properties.data.length">
                    <TableCell
                        colspan="8"
                        class="py-10 text-center text-muted-foreground"
                        >沒有符合條件的器材</TableCell
                    >
                </TableRow>
            </TableBody>
        </Table>
    </div>

    <!-- 手機：卡片 -->
    <div class="space-y-3 md:hidden">
        <Card v-for="p in properties.data" :key="p.id" class="py-4">
            <CardContent class="flex gap-3 px-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded bg-muted"
                >
                    <img
                        v-if="p.image_url"
                        :src="p.image_url"
                        :alt="p.name"
                        class="size-full object-cover"
                        loading="lazy"
                    />
                    <ImageOff v-else class="size-4 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate font-medium">{{ p.name }}</span>
                        <Badge
                            v-if="!p.borrow"
                            class="border-transparent bg-emerald-100 text-emerald-800"
                            >可借</Badge
                        >
                        <Badge
                            v-else
                            class="border-transparent bg-amber-100 text-amber-800"
                            >{{
                                p.borrow.status === 'pending'
                                    ? '申請中'
                                    : '借出中'
                            }}</Badge
                        >
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ p.ssid }} · {{ p.location }}
                    </div>
                    <div v-if="p.borrow" class="mt-1 text-xs">
                        {{ p.borrow.department }}，預計
                        {{ p.borrow.due_date }} 歸還
                    </div>
                </div>
            </CardContent>
        </Card>
        <p
            v-if="!properties.data.length"
            class="py-10 text-center text-sm text-muted-foreground"
        >
            沒有符合條件的器材
        </p>
    </div>

    <PaginationLinks :paginator="properties" />
</template>
