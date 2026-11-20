<script setup lang="ts">
import { ImageOff, Lock, Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

export type PickableProperty = {
    id: number;
    ssid: string;
    name: string;
    second_name: string | null;
    spec: string | null;
    category: string;
    subcategory: string;
    location: string;
    image_url: string | null;
    restricted: boolean;
};

const props = defineProps<{
    properties: PickableProperty[];
}>();

/** 已選的器材 id；切換搜尋或分類時保留（舊系統 selectedItems 的行為）。 */
const selected = defineModel<number[]>({ required: true });

const search = ref('');
const category = ref<string | null>(null);
const onlySelected = ref(false);

const categories = computed(() =>
    [...new Set(props.properties.map((p) => p.category))].sort(),
);

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.properties.filter((p) => {
        if (onlySelected.value && !selected.value.includes(p.id)) return false;
        if (category.value && p.category !== category.value) return false;
        if (!term) return true;

        return [p.name, p.second_name, p.ssid, p.spec, p.subcategory]
            .filter(Boolean)
            .some((v) => v!.toLowerCase().includes(term));
    });
});

const selectedItems = computed(() =>
    props.properties.filter((p) => selected.value.includes(p.id)),
);

function toggle(id: number, checked: boolean | 'indeterminate') {
    selected.value =
        checked === true
            ? [...new Set([...selected.value, id])]
            : selected.value.filter((v) => v !== id);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="搜尋名稱、財產編號、規格"
                    class="pl-9"
                />
            </div>
            <label class="flex items-center gap-2 text-sm whitespace-nowrap">
                <Checkbox
                    :model-value="onlySelected"
                    @update:model-value="onlySelected = $event === true"
                />
                只看已選（{{ selected.length }}）
            </label>
        </div>

        <div v-if="categories.length > 1" class="flex flex-wrap gap-2">
            <Button
                size="sm"
                :variant="category === null ? 'default' : 'outline'"
                @click="category = null"
                >全部</Button
            >
            <Button
                v-for="c in categories"
                :key="c"
                size="sm"
                :variant="category === c ? 'default' : 'outline'"
                @click="category = category === c ? null : c"
            >
                {{ c }}
            </Button>
        </div>

        <div
            v-if="selectedItems.length"
            class="flex flex-wrap gap-2 rounded-lg border border-dashed p-3"
        >
            <span class="text-sm text-muted-foreground">已選：</span>
            <Badge
                v-for="p in selectedItems"
                :key="p.id"
                variant="secondary"
                class="gap-1 pr-1"
            >
                {{ p.name
                }}<span class="font-mono text-xs opacity-70">{{ p.ssid }}</span>
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-background"
                    :aria-label="`移除 ${p.name}`"
                    @click="toggle(p.id, false)"
                >
                    <X class="size-3" />
                </button>
            </Badge>
        </div>

        <p
            v-if="!visible.length"
            class="py-8 text-center text-sm text-muted-foreground"
        >
            {{
                properties.length
                    ? '找不到符合條件的器材'
                    : '這個地點目前沒有可借用的器材'
            }}
        </p>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label
                v-for="p in visible"
                :key="p.id"
                class="flex cursor-pointer gap-3 rounded-lg border p-3 transition-colors hover:bg-accent/50"
                :class="
                    selected.includes(p.id)
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : ''
                "
            >
                <Checkbox
                    class="mt-1"
                    :model-value="selected.includes(p.id)"
                    @update:model-value="toggle(p.id, $event)"
                />
                <div
                    class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted"
                >
                    <img
                        v-if="p.image_url"
                        :src="p.image_url"
                        :alt="p.name"
                        class="size-full object-cover"
                        loading="lazy"
                    />
                    <ImageOff v-else class="size-5 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div
                        class="flex items-start gap-1 leading-tight font-medium"
                    >
                        <span class="truncate">{{ p.name }}</span>
                        <Lock
                            v-if="p.restricted"
                            class="mt-0.5 size-3.5 shrink-0 text-amber-600"
                            aria-label="需授權"
                        />
                    </div>
                    <div class="truncate text-xs text-muted-foreground">
                        {{
                            [p.second_name, p.spec]
                                .filter(Boolean)
                                .join(' · ') || p.subcategory
                        }}
                    </div>
                    <div class="flex flex-wrap items-center gap-1 text-xs">
                        <span class="font-mono text-muted-foreground">{{
                            p.ssid
                        }}</span>
                        <Badge variant="outline" class="text-[10px]">{{
                            p.location
                        }}</Badge>
                    </div>
                </div>
            </label>
        </div>
    </div>
</template>
