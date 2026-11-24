<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { addDays, todayInTaipei } from '@/lib/format';

/** 數據頁共用的借用日範圍；空字串代表不限。 */
const from = defineModel<string>('from', { required: true });
const to = defineModel<string>('to', { required: true });

const today = todayInTaipei();

const presets = [
    { label: '近 3 個月', from: addDays(today, -90), to: today },
    { label: '近一年', from: addDays(today, -365), to: today },
    { label: '今年', from: `${today.slice(0, 4)}-01-01`, to: today },
    { label: '全部', from: '', to: '' },
];

const activePreset = computed(
    () =>
        presets.find((p) => p.from === from.value && p.to === to.value)?.label,
);

function apply(preset: (typeof presets)[number]) {
    from.value = preset.from;
    to.value = preset.to;
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            v-for="p in presets"
            :key="p.label"
            size="sm"
            :variant="activePreset === p.label ? 'default' : 'outline'"
            @click="apply(p)"
            >{{ p.label }}</Button
        >
        <div class="flex items-center gap-2 text-sm">
            <Input
                v-model="from"
                type="date"
                class="w-auto"
                aria-label="借用日起"
            />
            <span class="text-muted-foreground">至</span>
            <Input
                v-model="to"
                type="date"
                class="w-auto"
                aria-label="借用日迄"
            />
        </div>
    </div>
</template>
