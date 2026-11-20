<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';
import CodeNameDialog from '@/components/CodeNameDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { destroy, index, store, update } from '@/routes/admin/categories';
import { store as storeSub } from '@/routes/admin/categories/subcategories';
import {
    destroy as destroySub,
    update as updateSub,
} from '@/routes/admin/subcategories';
import type { Category } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: '編碼規則', href: index() }] },
});

defineProps<{
    categories: Category[];
}>();

function remove(url: string, label: string) {
    if (!window.confirm(`確定刪除「${label}」？`)) return;

    router.delete(url, {
        preserveScroll: true,
        onError: (errors) =>
            toast.error(Object.values(errors)[0] ?? '刪除失敗'),
    });
}
</script>

<template>
    <Head title="編碼規則" />

    <div class="space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="編碼規則清冊"
                description="財產編號 8 碼 = 地點 1 碼 + 主要類別 3 碼 + 次要類別 1 碼 + 流水號 3 碼"
            />
            <CodeNameDialog
                title="新增主要類別"
                :url="store.url()"
                method="post"
                code-label="主要類別編號（3 位數字）"
                :code-max-length="3"
                code-placeholder="200"
            >
                <Button><Plus class="mr-1 size-4" />新增主要類別</Button>
            </CodeNameDialog>
        </div>

        <p
            v-if="!categories.length"
            class="py-10 text-center text-muted-foreground"
        >
            還沒有任何類別
        </p>

        <Card v-for="category in categories" :key="category.id">
            <CardHeader
                class="flex flex-row items-center justify-between gap-2 space-y-0"
            >
                <CardTitle class="text-base"
                    ><span class="font-mono">{{ category.code }}</span>
                    {{ category.name }}</CardTitle
                >
                <div class="flex gap-1">
                    <CodeNameDialog
                        :title="`新增次要類別（${category.code}）`"
                        :url="storeSub.url(category.id)"
                        method="post"
                        code-label="次要類別編號（0-9、A-Z）"
                        :code-max-length="1"
                        code-placeholder="0"
                    >
                        <Button size="sm" variant="outline"
                            ><Plus class="mr-1 size-4" />次要類別</Button
                        >
                    </CodeNameDialog>
                    <CodeNameDialog
                        title="編輯主要類別"
                        description="已有器材使用時不能修改編號"
                        :url="update.url(category.id)"
                        method="put"
                        :code="category.code"
                        :name="category.name"
                        code-label="主要類別編號"
                        :code-max-length="3"
                    >
                        <Button size="icon" variant="ghost" aria-label="編輯"
                            ><Pencil class="size-4"
                        /></Button>
                    </CodeNameDialog>
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label="刪除"
                        @click="
                            remove(
                                destroy.url(category.id),
                                `${category.code} ${category.name}`,
                            )
                        "
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <Table v-if="category.subcategories.length">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-24">編號</TableHead>
                            <TableHead>名稱</TableHead>
                            <TableHead class="w-24 text-right"
                                >器材數</TableHead
                            >
                            <TableHead class="w-24"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="sub in category.subcategories"
                            :key="sub.id"
                        >
                            <TableCell class="font-mono"
                                >{{ category.code }}{{ sub.code }}</TableCell
                            >
                            <TableCell>{{ sub.name }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                sub.properties_count ?? 0
                            }}</TableCell>
                            <TableCell class="text-right">
                                <CodeNameDialog
                                    title="編輯次要類別"
                                    description="已有器材使用時不能修改編號"
                                    :url="updateSub.url(sub.id)"
                                    method="put"
                                    :code="sub.code"
                                    :name="sub.name"
                                    code-label="次要類別編號"
                                    :code-max-length="1"
                                >
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        aria-label="編輯"
                                        ><Pencil class="size-4"
                                    /></Button>
                                </CodeNameDialog>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label="刪除"
                                    :disabled="(sub.properties_count ?? 0) > 0"
                                    @click="
                                        remove(
                                            destroySub.url(sub.id),
                                            `${category.code}${sub.code} ${sub.name}`,
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-else class="text-sm text-muted-foreground">尚無次要類別</p>
            </CardContent>
        </Card>
    </div>
</template>
