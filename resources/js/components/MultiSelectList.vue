<script setup lang="ts">
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

export type MultiSelectOption = {
    value: number;
    label: string;
    hint?: string | null;
};

const props = withDefaults(
    defineProps<{
        options: MultiSelectOption[];
        placeholder?: string;
        emptyText?: string;
    }>(),
    { placeholder: '搜尋', emptyText: '沒有選項' },
);

const selected = defineModel<number[]>({ required: true });
const search = ref('');

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();
    const list = term
        ? props.options.filter((o) =>
              `${o.label} ${o.hint ?? ''}`.toLowerCase().includes(term),
          )
        : props.options;

    // 已選的排在前面，方便確認
    return [...list].sort(
        (a, b) =>
            Number(selected.value.includes(b.value)) -
            Number(selected.value.includes(a.value)),
    );
});

function toggle(value: number, checked: boolean | 'indeterminate') {
    selected.value =
        checked === true
            ? [...new Set([...selected.value, value])]
            : selected.value.filter((v) => v !== value);
}
</script>

<template>
    <div class="space-y-2">
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input v-model="search" :placeholder="placeholder" class="pl-9" />
        </div>
        <div class="max-h-64 overflow-y-auto rounded-md border">
            <label
                v-for="o in visible"
                :key="o.value"
                class="flex cursor-pointer items-center gap-3 border-b px-3 py-2 text-sm last:border-b-0 hover:bg-accent/50"
            >
                <Checkbox
                    :model-value="selected.includes(o.value)"
                    @update:model-value="toggle(o.value, $event)"
                />
                <span class="flex-1">{{ o.label }}</span>
                <span v-if="o.hint" class="text-xs text-muted-foreground">{{
                    o.hint
                }}</span>
            </label>
            <p
                v-if="!visible.length"
                class="p-4 text-center text-sm text-muted-foreground"
            >
                {{ emptyText }}
            </p>
        </div>
        <p class="text-xs text-muted-foreground">
            已選 {{ selected.length }} 項
        </p>
    </div>
</template>
