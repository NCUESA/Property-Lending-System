<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { redirect } from '@/routes/auth/sso';

defineOptions({
    layout: {
        title: '登入',
        description: '使用學生會 SSO 帳號登入',
    },
});

defineProps<{
    ssoConfigured: boolean;
}>();

const page = usePage();
// SSO 失敗時後端以 Inertia flash 帶回錯誤訊息
const error = computed(() =>
    page.flash.toast?.type === 'error' ? page.flash.toast.message : null,
);
</script>

<template>
    <Head title="登入" />

    <div class="flex flex-col gap-6">
        <Alert v-if="error" variant="destructive">
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <!-- SSO 會導向外部網域，必須是整頁跳轉，不能用 Inertia Link -->
        <Button
            v-if="ssoConfigured"
            as="a"
            :href="redirect().url"
            class="w-full"
            data-test="sso-login-button"
        >
            使用 NCUESA SSO 登入
        </Button>

        <Alert v-else variant="destructive">
            <AlertDescription
                >SSO 尚未設定，請聯絡系統管理員。</AlertDescription
            >
        </Alert>
    </div>
</template>
