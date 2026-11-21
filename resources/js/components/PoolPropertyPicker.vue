<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    index as poolProperties,
    update as syncPoolProperties,
} from '@/routes/admin/pools/properties';

type Candidate = {
    id: number;
    ssid: string;
    name: string;
    second_name: string | null;
    is_lendable: boolean;
    property_pool_id: number | null;
    location: { id: number; name: string } | null;
    pool: { id: number; name: string } | null;
};

type PoolRef = { id: number; name: string; location: { name: string } };

const props = defineProps<{ pool: PoolRef | null }>();
const open = defineModel<boolean>('open', { required: true });

const loading = ref(false);
const saving = ref(false);
const candidates = ref<Candidate[]>([]);
const selected = ref(new Set<number>());
const search = ref('');

/** 已屬於其他品項：不能直接搶過來，要先從原品項移出。 */
function takenByOther(c: Candidate): boolean {
    return c.property_pool_id !== null && c.property_pool_id !== props.pool?.id;
}

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) return candidates.value;

    return candidates.value.filter((c) =>
        [c.ssid, c.name, c.second_name ?? '']
            .join(' ')
            .toLowerCase()
            .includes(term),
    );
});

const selectableVisible = computed(() =>
    visible.value.filter((c) => !takenByOther(c)),
);

const allVisibleSelected = computed(
    () =>
        selectableVisible.value.length > 0 &&
        selectableVisible.value.every((c) => selected.value.has(c.id)),
);

const lendableSelected = computed(
    () =>
        candidates.value.filter(
            (c) => selected.value.has(c.id) && c.is_lendable,
        ).length,
);

watch(open, async (isOpen) => {
    if (!isOpen || !props.pool) return;

    loading.value = true;
    search.value = '';
    candidates.value = [];
    selected.value = new Set();

    try {
        const response = await fetch(poolProperties.url(props.pool.id), {
            headers: { Accept: 'application/json' },
        });
        const body = await response.json();

        if (!response.ok) {
            toast.error('無法載入器材清單');
            open.value = false;
            return;
        }

        candidates.value = body.properties;
        selected.value = new Set(
            candidates.value
                .filter((c) => c.property_pool_id === props.pool?.id)
                .map((c) => c.id),
        );
    } finally {
        loading.value = false;
    }
});

function toggle(c: Candidate, checked: boolean) {
    const next = new Set(selected.value);

    if (checked) next.add(c.id);
    else next.delete(c.id);

    selected.value = next;
}

function toggleAllVisible(checked: boolean) {
    const next = new Set(selected.value);

    for (const c of selectableVisible.value) {
        if (checked) next.add(c.id);
        else next.delete(c.id);
    }

    selected.value = next;
}

function save() {
    if (!props.pool) return;

    saving.value = true;

    router.put(
        syncPoolProperties.url(props.pool.id),
        { property_ids: [...selected.value] },
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? '儲存失敗'),
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[85vh] flex-col sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>挑選「{{ pool?.name }}」的器材</DialogTitle>
                <DialogDescription>
                    勾選要納入此品項的器材。
                </DialogDescription>
            </DialogHeader>

            <div class="relative">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-8"
                    placeholder="搜尋財產編號或名稱"
                />
            </div>

            <div class="flex-1 overflow-y-auto rounded-md border">
                <div v-if="loading" class="flex justify-center py-10">
                    <Spinner />
                </div>

                <p
                    v-else-if="!visible.length"
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    {{
                        candidates.length
                            ? '沒有符合搜尋條件的器材'
                            : '此地點沒有可納入的器材'
                    }}
                </p>

                <template v-else>
                    <label
                        class="flex items-center gap-3 border-b bg-muted/50 px-3 py-2 text-sm font-medium"
                    >
                        <Checkbox
                            :model-value="allVisibleSelected"
                            :disabled="!selectableVisible.length"
                            @update:model-value="
                                toggleAllVisible($event === true)
                            "
                        />
                        全選（{{ selectableVisible.length }} 件）
                    </label>

                    <label
                        v-for="c in visible"
                        :key="c.id"
                        class="flex items-center gap-3 border-b px-3 py-2 text-sm last:border-b-0"
                        :class="
                            takenByOther(c)
                                ? 'cursor-not-allowed opacity-60'
                                : 'cursor-pointer hover:bg-accent'
                        "
                    >
                        <Checkbox
                            :model-value="selected.has(c.id)"
                            :disabled="takenByOther(c)"
                            @update:model-value="toggle(c, $event === true)"
                        />
                        <span class="w-20 shrink-0 font-mono text-xs">{{
                            c.ssid
                        }}</span>
                        <span class="flex-1 truncate">
                            {{ c.name
                            }}<span
                                v-if="c.second_name"
                                class="text-muted-foreground"
                            >
                                / {{ c.second_name }}</span
                            >
                        </span>
                        <span class="text-xs text-muted-foreground">{{
                            c.location?.name
                        }}</span>
                        <Badge v-if="!c.is_lendable" variant="outline"
                            >未開放</Badge
                        >
                        <Badge v-if="takenByOther(c)" variant="secondary"
                            >已屬於 {{ c.pool?.name }}</Badge
                        >
                    </label>
                </template>
            </div>

            <DialogFooter class="sm:justify-between">
                <p class="text-sm text-muted-foreground">
                    已選 {{ selected.size }} 件，其中可借 {{ lendableSelected }}
                    件
                </p>
                <div class="flex gap-2">
                    <Button variant="outline" @click="open = false"
                        >取消</Button
                    >
                    <Button :disabled="loading || saving" @click="save">
                        <Spinner v-if="saving" class="mr-1" />儲存
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
