<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { exportMethod, index } from '@/routes/admin/properties';
import { create, preview, store } from '@/routes/admin/properties/import';

type Row = {
    line: number;
    action: 'create' | 'update' | 'error';
    ssid: string | null;
    name: string | null;
    errors: string[];
    changes: string[];
};

const props = defineProps<{
    token: string | null;
    rows: Row[] | null;
    columns: string[];
    maxRows: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '器材', href: index() },
            { title: '批量匯入', href: create() },
        ],
    },
});

const upload = useForm({ file: null as File | null });

function submitUpload() {
    upload.post(preview.url(), { forceFormData: true });
}

const confirming = ref(false);
const confirmError = ref('');

function confirmImport() {
    router.post(
        store.url(),
        { token: props.token },
        {
            onStart: () => (confirming.value = true),
            onFinish: () => (confirming.value = false),
            onError: (e) => (confirmError.value = e.file ?? '匯入失敗'),
        },
    );
}

const counts = computed(() => ({
    create: props.rows?.filter((r) => r.action === 'create').length ?? 0,
    update: props.rows?.filter((r) => r.action === 'update').length ?? 0,
    unchanged:
        props.rows?.filter((r) => r.action === 'update' && !r.changes.length)
            .length ?? 0,
    error: props.rows?.filter((r) => r.action === 'error').length ?? 0,
}));

const onlyProblems = ref(false);
const visibleRows = computed(
    () =>
        props.rows?.filter(
            (r) =>
                !onlyProblems.value ||
                r.action === 'error' ||
                r.changes.length ||
                r.action === 'create',
        ) ?? [],
);

const actionMeta = {
    create: {
        label: '新增',
        class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    update: {
        label: '更新',
        class: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    error: {
        label: '錯誤',
        class: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    },
} as const;
</script>

<template>
    <Head title="批量匯入器材" />

    <div class="space-y-6 p-4 md:p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon" as-child>
                <Link :href="index()" aria-label="回器材列表"
                    ><ArrowLeft class="size-4"
                /></Link>
            </Button>
            <Heading
                title="批量匯入器材"
                description="上傳 CSV，先預覽每一列會新增還是更新，確認後才會寫入"
            />
        </div>

        <!-- 上傳 -->
        <Card v-if="!rows">
            <CardHeader>
                <CardTitle class="text-base">上傳 CSV</CardTitle>
                <CardDescription>
                    最簡單的做法：先
                    <a
                        :href="exportMethod.url()"
                        download
                        class="font-medium underline underline-offset-4"
                        >匯出目前的清冊</a
                    >，在 Excel 改完或往下加新的列，再傳回來。
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <form
                    class="flex flex-col gap-3 sm:flex-row sm:items-start"
                    @submit.prevent="submitUpload"
                >
                    <div class="flex-1">
                        <Input
                            type="file"
                            accept=".csv,text/csv"
                            aria-label="CSV 檔"
                            @input="
                                upload.file =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                        <InputError
                            :message="upload.errors.file"
                            class="mt-1"
                        />
                    </div>
                    <Button
                        type="submit"
                        :disabled="!upload.file || upload.processing"
                        ><Upload class="mr-1 size-4" />上傳並預覽</Button
                    >
                </form>

                <div class="space-y-2 rounded-lg bg-muted/50 p-4 text-sm">
                    <p class="font-medium">規則</p>
                    <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>
                            以<strong class="text-foreground">財產編號</strong
                            >對應：已存在就更新，不存在就新增。
                        </li>
                        <li>
                            新器材的財產編號可以<strong class="text-foreground"
                                >留空</strong
                            >，會依存放地點和子類別自動編號（例如一次加 20
                            張椅子）。
                        </li>
                        <li>
                            新器材必填：名稱、類別碼、子類別碼、存放地點。類別要先在「編碼規則」建好。
                        </li>
                        <li>
                            檔案裡有的欄位才會更新，儲存格空白代表清空；不想動的欄位可以整欄刪掉。
                        </li>
                        <li>
                            存放地點填名稱或代碼都可以；校產、開放借用填「是／否」；日期用
                            2026-01-31。
                        </li>
                        <li>
                            「類別」「子類別」「目前狀態」只是方便閱讀，匯入時不會使用。
                        </li>
                        <li>
                            Excel 存檔請選「CSV UTF-8」；一般的
                            CSV（Big5）也讀得懂。一次最多
                            {{ maxRows }} 列。
                        </li>
                    </ul>
                    <p class="pt-1 text-xs text-muted-foreground">
                        欄位：{{ columns.join('、') }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- 預覽 -->
        <template v-else>
            <div class="flex flex-wrap items-center gap-3">
                <Badge :class="actionMeta.create.class"
                    >新增 {{ counts.create }}</Badge
                >
                <Badge :class="actionMeta.update.class"
                    >更新 {{ counts.update }}
                    <template v-if="counts.unchanged"
                        >（其中 {{ counts.unchanged }} 件沒有變更）</template
                    ></Badge
                >
                <Badge v-if="counts.error" :class="actionMeta.error.class"
                    >錯誤 {{ counts.error }}</Badge
                >
                <label class="flex items-center gap-2 text-sm md:ml-auto">
                    <Checkbox v-model="onlyProblems" />
                    隱藏沒有變更的列
                </label>
            </div>

            <div
                v-if="counts.error"
                class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
            >
                有
                {{ counts.error }}
                列有錯誤，整批都不會匯入。請修正檔案後重新上傳。
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-14">列</TableHead>
                            <TableHead class="w-20">動作</TableHead>
                            <TableHead>財產編號</TableHead>
                            <TableHead>名稱</TableHead>
                            <TableHead>說明</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in visibleRows" :key="r.line">
                            <TableCell
                                class="text-muted-foreground tabular-nums"
                                >{{ r.line }}</TableCell
                            >
                            <TableCell>
                                <Badge :class="actionMeta[r.action].class">{{
                                    actionMeta[r.action].label
                                }}</Badge>
                            </TableCell>
                            <TableCell class="font-mono text-xs">
                                {{ r.ssid ?? '—' }}
                                <span
                                    v-if="r.action === 'create' && r.ssid"
                                    class="block font-sans text-muted-foreground"
                                    >（新編號）</span
                                >
                            </TableCell>
                            <TableCell>{{ r.name ?? '—' }}</TableCell>
                            <TableCell class="text-sm">
                                <ul
                                    v-if="r.errors.length"
                                    class="list-disc pl-4 text-red-700 dark:text-red-400"
                                >
                                    <li v-for="e in r.errors" :key="e">
                                        {{ e }}
                                    </li>
                                </ul>
                                <span v-else-if="r.action === 'update'">
                                    <template v-if="r.changes.length"
                                        >會更新：{{
                                            r.changes.join('、')
                                        }}</template
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >沒有變更</span
                                    >
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <InputError :message="confirmError" />

            <div class="flex flex-wrap justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link :href="create()">重新上傳</Link>
                </Button>
                <Button
                    :disabled="
                        counts.error > 0 ||
                        counts.create + counts.update - counts.unchanged ===
                            0 ||
                        confirming
                    "
                    @click="confirmImport"
                    ><Download class="mr-1 size-4 rotate-180" />確認匯入
                    {{ counts.create + counts.update - counts.unchanged }}
                    件</Button
                >
            </div>
        </template>
    </div>
</template>
