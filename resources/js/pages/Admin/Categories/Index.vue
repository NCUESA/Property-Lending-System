<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import CodeNameDialog from '@/components/CodeNameDialog.vue';
import Heading from '@/components/Heading.vue';
import InlineTextCell from '@/components/InlineTextCell.vue';
import { Button } from '@/components/ui/button';
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
import type { Category, Subcategory } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: '編碼規則', href: index() }] },
});

defineProps<{
    categories: Category[];
}>();

/** 儲存失敗時遞增，讓所有格子把顯示值還原成伺服器的值。 */
const resetKey = ref(0);

/** 沒有次要類別的主類別也要佔一列，否則整個主類別會在表格裡消失。 */
function rowsOf(category: Category): (Subcategory | null)[] {
    return category.subcategories.length ? category.subcategories : [null];
}

/** 已有器材使用的編號不能改（財產編號會對不上），直接在前端擋掉。 */
function categoryLocked(category: Category): boolean {
    return category.subcategories.some((s) => (s.properties_count ?? 0) > 0);
}

function save(url: string, payload: { code: string; name: string }) {
    router.put(url, payload, {
        preserveScroll: true,
        onError: (errors) => {
            resetKey.value++;
            toast.error(Object.values(errors)[0] ?? '儲存失敗');
        },
    });
}

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
                description="財產編號 8 碼 = 地點 1 碼 + 主要類別 3 碼 + 次要類別 1 碼 + 流水號 3 碼。表格內的欄位可直接點選修改，離開欄位即儲存。"
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

        <div v-else class="overflow-x-auto rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-28">財產編碼號</TableHead>
                        <TableHead class="w-56">類別</TableHead>
                        <TableHead class="w-28">次類編號</TableHead>
                        <TableHead>次類名稱</TableHead>
                        <TableHead class="w-20 text-right">器材數</TableHead>
                        <TableHead class="w-12"></TableHead>
                        <TableHead class="w-24"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-for="category in categories" :key="category.id">
                        <TableRow
                            v-for="(sub, i) in rowsOf(category)"
                            :key="sub?.id ?? `empty-${category.id}`"
                            :class="i === 0 ? 'border-t-2' : 'border-t-0'"
                        >
                            <!-- 主類別：整組只在第一列顯示，等同 CSV 裡留白的續列 -->
                            <TableCell
                                v-if="i === 0"
                                :rowspan="rowsOf(category).length"
                                class="align-top"
                            >
                                <InlineTextCell
                                    :value="category.code"
                                    :reset-key="resetKey"
                                    :maxlength="3"
                                    :disabled="categoryLocked(category)"
                                    mono
                                    label="主要類別編號"
                                    @save="
                                        save(update.url(category.id), {
                                            code: $event,
                                            name: category.name,
                                        })
                                    "
                                />
                            </TableCell>
                            <TableCell
                                v-if="i === 0"
                                :rowspan="rowsOf(category).length"
                                class="align-top"
                            >
                                <InlineTextCell
                                    :value="category.name"
                                    :reset-key="resetKey"
                                    :maxlength="50"
                                    label="類別名稱"
                                    @save="
                                        save(update.url(category.id), {
                                            code: category.code,
                                            name: $event,
                                        })
                                    "
                                />
                            </TableCell>

                            <template v-if="sub">
                                <TableCell>
                                    <InlineTextCell
                                        :value="sub.code"
                                        :reset-key="resetKey"
                                        :maxlength="1"
                                        :disabled="
                                            (sub.properties_count ?? 0) > 0
                                        "
                                        mono
                                        uppercase
                                        label="次要類別編號"
                                        @save="
                                            save(updateSub.url(sub.id), {
                                                code: $event,
                                                name: sub.name,
                                            })
                                        "
                                    />
                                </TableCell>
                                <TableCell>
                                    <InlineTextCell
                                        :value="sub.name"
                                        :reset-key="resetKey"
                                        :maxlength="50"
                                        label="次類名稱"
                                        @save="
                                            save(updateSub.url(sub.id), {
                                                code: sub.code,
                                                name: $event,
                                            })
                                        "
                                    />
                                </TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{ sub.properties_count ?? 0 }}
                                </TableCell>
                                <TableCell>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        aria-label="刪除次要類別"
                                        :disabled="
                                            (sub.properties_count ?? 0) > 0
                                        "
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
                            </template>
                            <TableCell
                                v-else
                                colspan="4"
                                class="text-sm text-muted-foreground"
                            >
                                尚無次要類別
                            </TableCell>

                            <!-- 主類別層級的操作，同樣只在第一列 -->
                            <TableCell
                                v-if="i === 0"
                                :rowspan="rowsOf(category).length"
                                class="align-top whitespace-nowrap"
                            >
                                <CodeNameDialog
                                    :title="`新增次要類別（${category.code}）`"
                                    :url="storeSub.url(category.id)"
                                    method="post"
                                    code-label="次要類別編號（0-9、A-Z）"
                                    :code-max-length="1"
                                    code-placeholder="0"
                                >
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        aria-label="新增次要類別"
                                    >
                                        <Plus class="size-4" />
                                    </Button>
                                </CodeNameDialog>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label="刪除主要類別"
                                    :disabled="
                                        category.subcategories.length > 0
                                    "
                                    @click="
                                        remove(
                                            destroy.url(category.id),
                                            `${category.code} ${category.name}`,
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
