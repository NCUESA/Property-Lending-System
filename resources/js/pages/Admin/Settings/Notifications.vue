<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { edit, update } from '@/routes/admin/settings/section';

defineOptions({
    layout: { breadcrumbs: [{ title: '通知信', href: edit('notifications') }] },
});

type Settings = {
    submitted_enabled: boolean | null;
    submitted_subject: string;
    submitted_body: string;
    lent_enabled: boolean | null;
    lent_subject: string;
    lent_body: string;
    due_enabled: boolean | null;
    due_subject: string;
    due_body: string;
    due_days_before: number;
};

const props = defineProps<{
    settings: Settings;
    variables: Record<string, string>;
}>();

const form = useForm({
    submitted_enabled: props.settings.submitted_enabled ?? false,
    submitted_subject: props.settings.submitted_subject,
    submitted_body: props.settings.submitted_body,
    lent_enabled: props.settings.lent_enabled ?? false,
    lent_subject: props.settings.lent_subject,
    lent_body: props.settings.lent_body,
    due_enabled: props.settings.due_enabled ?? false,
    due_subject: props.settings.due_subject,
    due_body: props.settings.due_body,
    due_days_before: props.settings.due_days_before,
});

/** 三封信的欄位長得一樣，用一份設定描述避免模板重複三次 */
const mails = [
    {
        key: 'submitted',
        title: '申請確認信',
        when: '借用人在前台送出申請後立即寄出',
    },
    {
        key: 'lent',
        title: '出借通知信',
        when: '值班人員在後台按下「出借」後寄出',
    },
    {
        key: 'due',
        title: '歸還提醒信',
        when: '歸還日前 N 天由排程寄出，每張借用單只寄一次',
    },
] as const;

/**
 * 變數的顯示寫法。含 }} 的字串不能直接寫在樣板插值裡——
 * Vue 的 parser 會在第一個 }} 就把插值結束掉，所以在 script 裡組好再帶進去。
 */
const placeholder = (name: string) => `{{ ${name} }}`;

const submit = () =>
    form.put(update.url('notifications'), { preserveScroll: true });
</script>

<template>
    <Head title="通知信" />

    <form class="max-w-3xl space-y-6 p-4 md:p-6" @submit.prevent="submit">
        <Heading
            title="通知信"
            description="三種系統通知信的開關與內容。需先在「寄信設定」啟用寄信。"
        />

        <div class="rounded-md border bg-muted/40 p-3 text-sm">
            <p class="mb-2 font-medium">可用變數</p>
            <ul class="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                <li
                    v-for="(label, name) in variables"
                    :key="name"
                    class="text-xs text-muted-foreground"
                >
                    <code class="font-mono text-foreground">{{
                        placeholder(String(name))
                    }}</code>
                    — {{ label }}
                </li>
            </ul>
        </div>

        <section
            v-for="mail in mails"
            :key="mail.key"
            class="space-y-3 rounded-md border p-4"
        >
            <label class="flex items-center justify-between gap-4 text-sm">
                <span>
                    <span class="font-medium">{{ mail.title }}</span>
                    <span class="block text-xs text-muted-foreground">{{
                        mail.when
                    }}</span>
                </span>
                <Switch v-model="form[`${mail.key}_enabled`]" />
            </label>

            <div class="grid gap-2">
                <Label :for="`${mail.key}_subject`">主旨</Label>
                <Input
                    :id="`${mail.key}_subject`"
                    v-model="form[`${mail.key}_subject`]"
                />
                <InputError :message="form.errors[`${mail.key}_subject`]" />
            </div>

            <div class="grid gap-2">
                <Label :for="`${mail.key}_body`">內文</Label>
                <Textarea
                    :id="`${mail.key}_body`"
                    v-model="form[`${mail.key}_body`]"
                    rows="10"
                    class="font-mono text-xs"
                />
                <InputError :message="form.errors[`${mail.key}_body`]" />
            </div>

            <div v-if="mail.key === 'due'" class="grid max-w-xs gap-2">
                <Label for="due_days_before">提前幾天提醒</Label>
                <Input
                    id="due_days_before"
                    v-model.number="form.due_days_before"
                    type="number"
                    min="0"
                    max="30"
                />
                <p class="text-xs text-muted-foreground">
                    0 代表歸還當天才提醒
                </p>
                <InputError :message="form.errors.due_days_before" />
            </div>
        </section>

        <Button type="submit" :disabled="form.processing || !form.isDirty"
            >儲存設定</Button
        >
    </form>
</template>
