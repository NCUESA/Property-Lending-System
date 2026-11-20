<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { edit, update } from '@/routes/admin/settings';

defineOptions({
    layout: { breadcrumbs: [{ title: '系統設定', href: edit() }] },
});

const props = defineProps<{
    settings: {
        deposit_amount: number | null;
        max_days: number | null;
        default_open: boolean | null;
        allow_advance: boolean | null;
        notices: string[] | null;
    };
}>();

const form = useForm({
    deposit_amount: props.settings.deposit_amount ?? 100,
    max_days: props.settings.max_days ?? 7,
    default_open: props.settings.default_open ?? true,
    allow_advance: props.settings.allow_advance ?? false,
    notices: [...(props.settings.notices ?? [])],
});

function move(i: number, delta: number) {
    const j = i + delta;
    if (j < 0 || j >= form.notices.length) return;
    [form.notices[i], form.notices[j]] = [form.notices[j], form.notices[i]];
}

const submit = () => form.put(update.url(), { preserveScroll: true });
</script>

<template>
    <Head title="系統設定" />

    <form class="max-w-3xl space-y-6 p-4 md:p-6" @submit.prevent="submit">
        <Heading title="系統設定" description="借用規則與借用頁的注意事項" />

        <Card>
            <CardHeader
                ><CardTitle class="text-base">借用規則</CardTitle></CardHeader
            >
            <CardContent class="grid gap-4 sm:grid-cols-2">
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
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">注意事項</CardTitle>
                <CardDescription
                    >顯示在借用申請頁，借用人送出前需勾選同意</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-for="(_, i) in form.notices" :key="i" class="flex gap-2">
                    <span
                        class="w-6 pt-2 text-right text-sm text-muted-foreground tabular-nums"
                        >{{ i + 1 }}.</span
                    >
                    <div class="flex-1">
                        <Textarea v-model="form.notices[i]" rows="2" />
                        <InputError
                            :message="
                                form.errors[
                                    `notices.${i}` as keyof typeof form.errors
                                ]
                            "
                        />
                    </div>
                    <div class="flex flex-col">
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="size-7"
                            :disabled="i === 0"
                            aria-label="上移"
                            @click="move(i, -1)"
                            ><ArrowUp class="size-4"
                        /></Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="size-7"
                            :disabled="i === form.notices.length - 1"
                            aria-label="下移"
                            @click="move(i, 1)"
                            ><ArrowDown class="size-4"
                        /></Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="size-7"
                            aria-label="刪除"
                            @click="form.notices.splice(i, 1)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="form.notices.push('')"
                    ><Plus class="mr-1 size-4" />新增一條</Button
                >
            </CardContent>
        </Card>

        <Button type="submit" :disabled="form.processing || !form.isDirty"
            >儲存設定</Button
        >
    </form>
</template>
