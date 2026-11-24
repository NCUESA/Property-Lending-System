<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { destroy, index, store } from '@/routes/admin/stats/aliases';

type Row = {
    spelling: string;
    canonical: string | null;
    times: number;
    last_borrow_date: string;
};

const props = defineProps<{
    filters: { q?: string };
    rows: Row[];
    canonicals: string[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: '單位同義詞設定', href: index() }] },
});

const { filters } = useQueryFilters(index.url(), { q: props.filters.q ?? '' });

const selected = ref<string[]>([]);
const canonical = ref('');
const errors = ref<Record<string, string>>({});
const processing = ref(false);

// 搜尋換了列表，勾選中但已不在畫面上的就取消，避免合併到看不到的項目
watch(
    () => props.rows,
    (rows) => {
        const visible = new Set(rows.map((r) => r.spelling));
        selected.value = selected.value.filter((s) => visible.has(s));
    },
);

function toggle(spelling: string, checked: boolean | 'indeterminate') {
    selected.value = checked
        ? [...new Set([...selected.value, spelling])]
        : selected.value.filter((s) => s !== spelling);
}

/** 名稱建議：既有的統一名稱 + 勾選的寫法 */
const suggestions = computed(() => [
    ...new Set([...props.canonicals, ...selected.value]),
]);

// 勾第一個寫法時，預設用它（或它已合併到的名稱）當統一名稱
watch(selected, (now, before) => {
    if (!canonical.value && now.length && !before.length) {
        const row = props.rows.find((r) => r.spelling === now[0]);
        canonical.value = row?.canonical ?? now[0];
    }
});

const selectedMapped = computed(() =>
    props.rows.filter(
        (r) => selected.value.includes(r.spelling) && r.canonical,
    ),
);

const options = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
    onError: (e: Record<string, string>) => (errors.value = e),
    onSuccess: () => {
        selected.value = [];
        canonical.value = '';
        errors.value = {};
    },
};

function merge() {
    router.post(
        store.url(),
        { aliases: selected.value, canonical: canonical.value },
        options,
    );
}

function unmerge(aliases: string[]) {
    router.delete(destroy.url(), { data: { aliases }, ...options });
}
</script>

<template>
    <Head title="單位同義詞設定" />

    <div class="space-y-4 p-4 md:p-6">
        <Heading
            title="單位同義詞設定"
            description="借用單位是申請人手填的，同一個單位常有好幾種寫法。勾選要合併的寫法、填上統一名稱，統計時就會算在一起；借用單上的原文不會被修改。"
        />

        <div
            class="sticky top-0 z-10 flex flex-col gap-3 rounded-lg border bg-background p-3 md:flex-row md:items-center"
        >
            <div class="text-sm whitespace-nowrap">
                已勾選
                <span class="font-semibold tabular-nums">{{
                    selected.length
                }}</span>
                種寫法
            </div>
            <div class="flex-1">
                <Input
                    v-model="canonical"
                    list="canonical-suggestions"
                    placeholder="統一名稱，例如：熱舞社"
                    aria-label="統一名稱"
                    :disabled="!selected.length"
                />
                <datalist id="canonical-suggestions">
                    <option v-for="c in suggestions" :key="c" :value="c" />
                </datalist>
                <InputError
                    :message="errors.canonical ?? errors.aliases"
                    class="mt-1"
                />
            </div>
            <div class="flex gap-2">
                <Button
                    :disabled="
                        !selected.length || !canonical.trim() || processing
                    "
                    @click="merge"
                    >合併</Button
                >
                <Button
                    variant="outline"
                    :disabled="!selectedMapped.length || processing"
                    @click="unmerge(selectedMapped.map((r) => r.spelling))"
                    >取消合併</Button
                >
            </div>
        </div>

        <div class="relative max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="filters.q"
                placeholder="搜尋寫法或統一名稱"
                class="pl-9"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-10" />
                        <TableHead>借用單上的寫法</TableHead>
                        <TableHead>統計時算作</TableHead>
                        <TableHead class="text-right">借用單數</TableHead>
                        <TableHead>最近借用</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="r in rows"
                        :key="r.spelling"
                        :data-state="
                            selected.includes(r.spelling)
                                ? 'selected'
                                : undefined
                        "
                    >
                        <TableCell>
                            <Checkbox
                                :model-value="selected.includes(r.spelling)"
                                :aria-label="`勾選 ${r.spelling}`"
                                @update:model-value="toggle(r.spelling, $event)"
                            />
                        </TableCell>
                        <TableCell class="font-medium">{{
                            r.spelling
                        }}</TableCell>
                        <TableCell>
                            <Badge v-if="r.canonical" variant="secondary">{{
                                r.canonical
                            }}</Badge>
                            <span v-else class="text-xs text-muted-foreground"
                                >（原文）</span
                            >
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            r.times
                        }}</TableCell>
                        <TableCell class="tabular-nums">{{
                            formatDate(r.last_borrow_date)
                        }}</TableCell>
                    </TableRow>
                    <TableRow v-if="!rows.length">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                            >沒有符合的寫法</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
