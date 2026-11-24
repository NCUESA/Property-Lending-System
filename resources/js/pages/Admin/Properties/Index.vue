<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ImageOff, Lock, Plus, Search } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
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
import { create, edit, index } from '@/routes/admin/properties';
import type {
    Category,
    LocationRef,
    Paginator,
    PoolRef,
    Property,
} from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: '器材', href: index() }] },
});

const props = defineProps<{
    filters: {
        q?: string;
        location_id?: number;
        category_id?: number;
        pool_id?: number | 'none';
        per_page?: number;
    };
    properties: Paginator<Property>;
    locations: LocationRef[];
    categories: Category[];
    pools: PoolRef[];
    perPageOptions?: number[];
}>();

/**
 * 選項以後端的 PER_PAGE_OPTIONS 為準，不在前端重複定義一份。
 * 但 props 萬一沒帶（例如 HMR 換掉元件、頁面 props 還是舊的），至少要列出目前生效的值，
 * 否則 v-for 跑空陣列會渲染出一個沒有任何選項的空白選單。
 */
const perPageChoices = computed(() =>
    props.perPageOptions?.length
        ? props.perPageOptions
        : [props.properties.per_page],
);

const { filters } = useQueryFilters(index.url(), {
    q: props.filters.q ?? '',
    location_id: props.filters.location_id
        ? String(props.filters.location_id)
        : '',
    category_id: props.filters.category_id
        ? String(props.filters.category_id)
        : '',
    pool_id: props.filters.pool_id ? String(props.filters.pool_id) : '',
    // 留空代表用後端預設值；顯示時以 paginator 實際的 per_page 為準
    per_page: String(props.properties.per_page),
});

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm';
</script>

<template>
    <Head title="器材" />

    <div class="space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading title="器材" description="財產資料、照片與借用權限" />
            <Button as-child>
                <Link :href="create()"
                    ><Plus class="mr-1 size-4" />新增器材</Link
                >
            </Button>
        </div>

        <div class="flex flex-col gap-3 md:flex-row">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.q"
                    placeholder="財產編號、名稱、保管人"
                    class="pl-9"
                />
            </div>
            <select
                v-model="filters.location_id"
                :class="selectClass"
                aria-label="地點"
            >
                <option value="">全部地點</option>
                <option
                    v-for="l in locations"
                    :key="l.id"
                    :value="String(l.id)"
                >
                    {{ l.name }}
                </option>
            </select>
            <select
                v-model="filters.category_id"
                :class="selectClass"
                aria-label="主要類別"
            >
                <option value="">全部類別</option>
                <option
                    v-for="c in categories"
                    :key="c.id"
                    :value="String(c.id)"
                >
                    {{ c.code }} {{ c.name }}
                </option>
            </select>
            <select
                v-model="filters.pool_id"
                :class="selectClass"
                aria-label="品項池"
            >
                <option value="">全部（含大量借用品項）</option>
                <option value="none">不含大量借用品項</option>
                <option v-for="p in pools" :key="p.id" :value="String(p.id)">
                    {{ p.name }}（{{ p.location?.name }}）
                </option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12"></TableHead>
                        <TableHead>財產編號</TableHead>
                        <TableHead>名稱</TableHead>
                        <TableHead>類別</TableHead>
                        <TableHead>存放地點</TableHead>
                        <TableHead>借用</TableHead>
                        <TableHead>狀態</TableHead>
                        <TableHead class="w-16"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="p in properties.data"
                        :key="p.id"
                        :class="!p.is_lendable ? 'opacity-60' : ''"
                    >
                        <TableCell>
                            <div
                                class="flex size-9 items-center justify-center overflow-hidden rounded bg-muted"
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
                            <div class="font-medium">{{ p.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    [p.second_name, p.spec]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </div>
                        </TableCell>
                        <TableCell class="text-sm"
                            >{{ p.subcategory?.category.name }} /
                            {{ p.subcategory?.name }}</TableCell
                        >
                        <TableCell>{{ p.location?.name }}</TableCell>
                        <TableCell class="space-x-1">
                            <Badge v-if="!p.is_lendable" variant="outline"
                                >不開放</Badge
                            >
                            <Badge v-if="p.pool" variant="secondary"
                                >大量借用：{{ p.pool.name }}</Badge
                            >
                            <Badge
                                v-if="p.auth_level > 0 && !p.pool"
                                variant="outline"
                                class="gap-1"
                                ><Lock class="size-3" />等級
                                {{ p.auth_level }}</Badge
                            >
                        </TableCell>
                        <TableCell>
                            <StatusBadge
                                v-if="p.active_borrow_item"
                                kind="item"
                                :status="p.active_borrow_item.status"
                            />
                            <span v-else class="text-xs text-muted-foreground"
                                >在庫</span
                            >
                        </TableCell>
                        <TableCell>
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="edit(p.id)">編輯</Link>
                            </Button>
                        </TableCell>
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

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                每頁顯示
                <select
                    v-model="filters.per_page"
                    :class="selectClass"
                    aria-label="每頁顯示筆數"
                >
                    <option
                        v-for="n in perPageChoices"
                        :key="n"
                        :value="String(n)"
                    >
                        {{ n }}
                    </option>
                </select>
                筆
            </label>
            <PaginationLinks :paginator="properties" class="flex-1" />
        </div>
    </div>
</template>
