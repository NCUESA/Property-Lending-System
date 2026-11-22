<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { test as testMail } from '@/routes/admin/settings/mail';
import { edit, update } from '@/routes/admin/settings/section';

defineOptions({
    layout: { breadcrumbs: [{ title: '寄信設定', href: edit('mail') }] },
});

const props = defineProps<{
    settings: {
        enabled: boolean | null;
        host: string | null;
        port: number | null;
        scheme: string | null;
        username: string | null;
        password: string | null;
        from_address: string | null;
        from_name: string | null;
        ehlo_domain: string | null;
    };
    passwordSet: boolean;
}>();

const form = useForm({
    enabled: props.settings.enabled ?? false,
    host: props.settings.host ?? '',
    port: props.settings.port ?? 587,
    scheme: props.settings.scheme ?? '',
    username: props.settings.username ?? '',
    password: '',
    from_address: props.settings.from_address ?? '',
    from_name: props.settings.from_name ?? '',
    ehlo_domain: props.settings.ehlo_domain ?? '',
});

const submit = () => form.put(update.url('mail'), { preserveScroll: true });

// 測試信獨立一張表單，才不會把未存檔的 SMTP 欄位跟著送出去造成誤會
const testForm = useForm({ email: '' });
const testOpen = ref(false);

const sendTest = () =>
    testForm.post(testMail.url(), {
        preserveScroll: true,
        onSuccess: () => {
            testOpen.value = false;
            testForm.reset();
        },
    });
</script>

<template>
    <Head title="寄信設定" />

    <div class="max-w-3xl space-y-6 p-4 md:p-6">
        <Heading
            title="寄信設定"
            description="系統通知信使用的 SMTP 連線。未啟用時所有通知信都不會寄出。"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <label
                class="flex items-center justify-between gap-4 rounded-md border p-3 text-sm"
            >
                <span>
                    啟用寄信
                    <span class="block text-xs text-muted-foreground"
                        >關閉時所有通知信都不寄，排程也不會執行</span
                    >
                </span>
                <Switch v-model="form.enabled" />
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="host">SMTP 主機</Label>
                    <Input
                        id="host"
                        v-model="form.host"
                        placeholder="smtp.gmail.com"
                    />
                    <InputError :message="form.errors.host" />
                </div>
                <div class="grid gap-2">
                    <Label for="port">連接埠</Label>
                    <Input
                        id="port"
                        v-model.number="form.port"
                        type="number"
                        min="1"
                    />
                    <InputError :message="form.errors.port" />
                </div>
                <div class="grid gap-2">
                    <Label for="scheme">加密方式</Label>
                    <select
                        id="scheme"
                        v-model="form.scheme"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">自動（依連接埠判斷）</option>
                        <option value="smtp">STARTTLS（連接埠 587）</option>
                        <option value="smtps">SSL/TLS（連接埠 465）</option>
                    </select>
                    <InputError :message="form.errors.scheme" />
                </div>
                <div class="grid gap-2">
                    <Label for="username">帳號</Label>
                    <Input id="username" v-model="form.username" />
                    <InputError :message="form.errors.username" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="password">密碼</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            passwordSet ? '已設定，留空則不變更' : '尚未設定'
                        "
                    />
                    <p class="text-xs text-muted-foreground">
                        密碼加密後存在資料庫，不會再顯示出來。
                    </p>
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="from_address">寄件者信箱</Label>
                    <Input
                        id="from_address"
                        v-model="form.from_address"
                        type="email"
                    />
                    <InputError :message="form.errors.from_address" />
                </div>
                <div class="grid gap-2">
                    <Label for="from_name">寄件者名稱</Label>
                    <Input id="from_name" v-model="form.from_name" />
                    <InputError :message="form.errors.from_name" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="ehlo_domain">EHLO 網域</Label>
                    <Input
                        id="ehlo_domain"
                        v-model="form.ehlo_domain"
                        placeholder="留空則用寄件者信箱的網域"
                    />
                    <p class="text-xs text-muted-foreground">
                        連線時向對方宣告的網域。Gmail 等服務會拒絕
                        <code class="font-mono">localhost</code>
                        這種非正式網域，出現 421 (EHLO) 錯誤時請填入真實網域。
                    </p>
                    <InputError :message="form.errors.ehlo_domain" />
                </div>
            </div>

            <Button type="submit" :disabled="form.processing || !form.isDirty"
                >儲存設定</Button
            >
        </form>

        <div class="rounded-md border p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm">
                    寄一封測試信
                    <span class="block text-xs text-muted-foreground"
                        >用目前「已儲存」的設定寄出，確認 SMTP 是否可用</span
                    >
                </div>
                <Button
                    v-if="!testOpen"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="testOpen = true"
                    ><Send class="mr-1 size-4" />測試</Button
                >
            </div>

            <form
                v-if="testOpen"
                class="mt-3 flex flex-wrap items-start gap-2"
                @submit.prevent="sendTest"
            >
                <div class="flex-1">
                    <Input
                        v-model="testForm.email"
                        type="email"
                        placeholder="收件信箱"
                        required
                    />
                    <InputError :message="testForm.errors.email" />
                </div>
                <Button type="submit" :disabled="testForm.processing">
                    <Spinner v-if="testForm.processing" class="mr-1" />寄出
                </Button>
                <Button type="button" variant="ghost" @click="testOpen = false"
                    >取消</Button
                >
            </form>
        </div>
    </div>
</template>
