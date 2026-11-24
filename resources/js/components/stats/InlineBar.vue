<script setup lang="ts">
import { computed } from 'vue';

/**
 * 排行表格裡的長條：長度依 value / max，數字直接標在右邊。
 * 單一序列、量值用同一個藍色（dataviz 預設的 sequential 色）。
 */
const props = defineProps<{ value: number; max: number; label?: string }>();

const width = computed(() =>
    props.max > 0 ? Math.max((props.value / props.max) * 100, 1) : 0,
);
</script>

<template>
    <div class="flex min-w-32 items-center gap-2">
        <div class="h-2.5 flex-1">
            <div
                class="h-full rounded-r bg-[#2a78d6] dark:bg-[#3987e5]"
                :style="{ width: `${width}%` }"
            />
        </div>
        <span class="w-10 text-right font-medium tabular-nums">{{
            label ?? value
        }}</span>
    </div>
</template>
