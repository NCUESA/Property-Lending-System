<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { edit, update } from '@/routes/admin/settings/section';

defineOptions({
    layout: { breadcrumbs: [{ title: '借用規則', href: edit('borrow') }] },
});

type OpenMode = {
    value: string;
    label: string;
    description: string;
    available: boolean;
};

const props = defineProps<{
    openModes: OpenMode[];
    settings: {
        deposit_amount: number | null;
        max_days: number | null;
        open_mode: string | null;
        allow_advance: boolean | null;
        deposit_box_count: number | null;
        fixed_duration: boolean | null;
    };
}>();

const form = useForm({
    deposit_amount: props.settings.deposit_amount ?? 100,
    max_days: props.settings.max_days ?? 7,
    open_mode: props.settings.open_mode ?? 'always_open',
    allow_advance: props.settings.allow_advance ?? false,
    deposit_box_count: props.settings.deposit_box_count ?? 20,
    fixed_duration: props.settings.fixed_duration ?? true,
});

const submit = () => form.put(update.url('borrow'), { preserveScroll: true });
</script>

<template>
    <Head title="借用規則" />

    <form class="max-w-3xl space-y-6 p-4 md:p-6" @submit.prevent="submit">
        <Heading title="借用規則" description="押金、借用天數與預借設定" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="deposit_amount">押金金額（NT$）</Label>
                <Input
                    id="deposit_amount"
                    v-model.number="form.deposit_amount"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.deposit_amount" />
            </div>
            <div class="grid gap-2">
                <Label for="deposit_box_count">押金箱數量</Label>
                <Input
                    id="deposit_box_count"
                    v-model.number="form.deposit_box_count"
                    type="number"
                    min="1"
                    max="200"
                />
                <p class="text-xs text-muted-foreground">
                    出借時的箱號下拉選單會是 1 ~ 這個數字
                </p>
                <InputError :message="form.errors.deposit_box_count" />
            </div>
            <div class="grid gap-2">
                <Label for="max_days">規定借用天數（含借用當天）</Label>
                <Input
                    id="max_days"
                    v-model.number="form.max_days"
                    type="number"
                    min="1"
                />
                <p class="text-xs text-muted-foreground">
                    {{
                        form.fixed_duration
                            ? '所有借用一律是這個天數'
                            : '借用人可自選天數；超過時借用單會標示，但仍可送出'
                    }}
                </p>
                <InputError :message="form.errors.max_days" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label>開放方式</Label>
                <div
                    class="grid gap-2 sm:grid-cols-3"
                    role="radiogroup"
                    aria-label="開放方式"
                >
                    <button
                        v-for="mode in openModes"
                        :key="mode.value"
                        type="button"
                        role="radio"
                        :aria-checked="form.open_mode === mode.value"
                        :disabled="!mode.available"
                        :title="mode.available ? undefined : mode.description"
                        :class="[
                            'rounded-md border p-3 text-left text-sm transition-colors',
                            form.open_mode === mode.value
                                ? 'border-primary bg-primary/10 ring-1 ring-primary'
                                : 'hover:bg-accent',
                            mode.available
                                ? ''
                                : 'cursor-not-allowed opacity-50 hover:bg-transparent',
                        ]"
                        @click="mode.available && (form.open_mode = mode.value)"
                    >
                        <span class="block font-medium">
                            {{ mode.label }}
                            <Badge
                                v-if="!mode.available"
                                variant="secondary"
                                class="ml-1 align-middle"
                            >
                                尚未開放
                            </Badge>
                        </span>
                        <span
                            class="mt-1 block text-xs text-muted-foreground"
                            >{{ mode.description }}</span
                        >
                    </button>
                </div>
                <InputError :message="form.errors.open_mode" />
            </div>

            <label
                class="flex items-start justify-between gap-4 rounded-md border p-3 text-sm sm:col-span-2"
            >
                <span>
                    固定借用天數
                    <span class="block text-xs text-muted-foreground">
                        開啟：一律借滿規定的
                        {{ form.max_days }} 天，借用人只選取件日。<br />
                        關閉：借用人可自選天數，超過 {{ form.max_days }}
                        天仍可送出，但借用單會標示提醒值班人員。
                    </span>
                </span>
                <Switch v-model="form.fixed_duration" />
            </label>
            <label
                class="flex items-center justify-between gap-4 rounded-md border p-3 text-sm"
            >
                <span>
                    開放預借
                    <span class="block text-xs text-muted-foreground"
                        >關閉時取件日只能是今天，不能挑未來的日子</span
                    >
                </span>
                <Switch v-model="form.allow_advance" />
            </label>
        </div>

        <Button type="submit" :disabled="form.processing || !form.isDirty"
            >儲存設定</Button
        >
    </form>
</template>
