<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { edit, update } from '@/routes/admin/settings/section';

defineOptions({
    layout: { breadcrumbs: [{ title: '借用規則', href: edit('borrow') }] },
});

const props = defineProps<{
    settings: {
        deposit_amount: number | null;
        max_days: number | null;
        default_open: boolean | null;
        allow_advance: boolean | null;
        deposit_box_count: number | null;
        flexible_duration: boolean | null;
    };
}>();

const form = useForm({
    deposit_amount: props.settings.deposit_amount ?? 100,
    max_days: props.settings.max_days ?? 7,
    default_open: props.settings.default_open ?? true,
    allow_advance: props.settings.allow_advance ?? false,
    deposit_box_count: props.settings.deposit_box_count ?? 20,
    flexible_duration: props.settings.flexible_duration ?? true,
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
                <Label for="max_days">最長借用天數（含借用當天）</Label>
                <Input
                    id="max_days"
                    v-model.number="form.max_days"
                    type="number"
                    min="1"
                />
                <InputError :message="form.errors.max_days" />
            </div>
            <label
                class="flex items-center justify-between gap-4 rounded-md border p-3 text-sm"
            >
                <span>
                    預設開放借用
                    <span class="block text-xs text-muted-foreground"
                        >不在任何「借用期間」內的日期</span
                    >
                </span>
                <Switch v-model="form.default_open" />
            </label>
            <label
                class="flex items-start justify-between gap-4 rounded-md border p-3 text-sm sm:col-span-2"
            >
                <span>
                    彈性借用
                    <span class="block text-xs text-muted-foreground">
                        開啟：一律套用最長借用天數（目前
                        {{ form.max_days }}
                        天），借用人只選取件日。<br />
                        關閉：借用人可自由選擇歸還日，超過規定時借用單會標示提醒值班人員。
                    </span>
                </span>
                <Switch v-model="form.flexible_duration" />
            </label>
            <label
                class="flex items-center justify-between gap-4 rounded-md border p-3 text-sm"
            >
                <span>
                    開放預借
                    <span class="block text-xs text-muted-foreground"
                        >關閉時借用日只能是今天</span
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
