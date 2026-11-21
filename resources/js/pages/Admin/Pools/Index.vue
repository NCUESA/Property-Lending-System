<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ListChecks, Lock, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PoolPropertyPicker from '@/components/PoolPropertyPicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { destroy, index, store, update } from '@/routes/admin/pools';
import { index as propertiesIndex } from '@/routes/admin/properties';

type Pool = {
    id: number;
    name: string;
    location_id: number;
    auth_level: number;
    remark: string | null;
    location: { id: number; name: string };
    properties_count: number;
    lendable_count: number;
    available_quantity: number;
};

defineOptions({
    layout: { breadcrumbs: [{ title: '大量借用品項', href: index() }] },
});

const props = defineProps<{
    pools: Pool[];
    locations: { id: number; name: string }[];
}>();

const open = ref(false);
const editing = ref<Pool | null>(null);
const form = useForm({ name: '', location_id: '', auth_level: 0, remark: '' });

function openDialog(pool: Pool | null) {
    editing.value = pool;
    form.clearErrors();
    form.defaults({
        name: pool?.name ?? '',
        location_id: pool
            ? String(pool.location_id)
            : String(props.locations[0]?.id ?? ''),
        auth_level: pool?.auth_level ?? 0,
        remark: pool?.remark ?? '',
    });
    form.reset();
    open.value = true;
}

const pickerOpen = ref(false);
const pickerPool = ref<Pool | null>(null);

function openPicker(pool: Pool) {
    pickerPool.value = pool;
    pickerOpen.value = true;
}

function submit() {
    const isNew = editing.value === null;

    form.submit(
        isNew ? 'post' : 'put',
        isNew ? store.url() : update.url(editing.value!.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;

                // 新增完直接接著挑器材：剛建立的品項會出現在重新載入後的 props 裡
                if (!isNew) return;

                const created = props.pools.find(
                    (p) =>
                        p.name === form.name &&
                        String(p.location_id) === String(form.location_id),
                );

                if (created) openPicker(created);
            },
        },
    );
}

function remove(pool: Pool) {
    if (!window.confirm(`確定刪除「${pool.name}」？池內器材會改回逐件借用。`))
        return;
    router.delete(destroy.url(pool.id), {
        preserveScroll: true,
        onError: (errors) =>
            toast.error(Object.values(errors)[0] ?? '刪除失敗'),
    });
}
</script>

<template>
    <Head title="大量借用品項" />

    <div class="space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="大量借用品項"
                description="不需要特別指定編號的器材種類"
            />
            <Button @click="openDialog(null)"
                ><Plus class="mr-1 size-4" />新增品項</Button
            >
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>品項</TableHead>
                        <TableHead>取用地點</TableHead>
                        <TableHead class="text-right"
                            >可借 / 開放 / 總數</TableHead
                        >
                        <TableHead>權限</TableHead>
                        <TableHead class="w-28"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="pool in pools" :key="pool.id">
                        <TableCell>
                            <div class="font-medium">{{ pool.name }}</div>
                            <div
                                v-if="pool.remark"
                                class="text-xs text-muted-foreground"
                            >
                                {{ pool.remark }}
                            </div>
                        </TableCell>
                        <TableCell>{{ pool.location.name }}</TableCell>
                        <TableCell class="text-right tabular-nums">
                            <Link
                                :href="
                                    propertiesIndex({
                                        query: { pool_id: pool.id },
                                    })
                                "
                                class="hover:underline"
                            >
                                <span class="font-semibold">{{
                                    Math.max(0, pool.available_quantity)
                                }}</span>
                                / {{ pool.lendable_count }} /
                                {{ pool.properties_count }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <Badge
                                v-if="pool.auth_level > 0"
                                variant="outline"
                                class="gap-1"
                                ><Lock class="size-3" />等級
                                {{ pool.auth_level }}</Badge
                            >
                            <span v-else class="text-xs text-muted-foreground"
                                >公開</span
                            >
                        </TableCell>
                        <TableCell class="text-right">
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="挑選池內器材"
                                title="挑選池內器材"
                                @click="openPicker(pool)"
                                ><ListChecks class="size-4"
                            /></Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="編輯"
                                @click="openDialog(pool)"
                                ><Pencil class="size-4"
                            /></Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="刪除"
                                @click="remove(pool)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="!pools.length">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                            >還沒有大量借用品項</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <PoolPropertyPicker v-model:open="pickerOpen" :pool="pickerPool" />

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? '編輯品項' : '新增品項'
                }}</DialogTitle>
                <DialogDescription
                    >同一地點的品項名稱不能重複</DialogDescription
                >
            </DialogHeader>
            <form id="pool-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">品項名稱</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="折疊椅"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="location_id">取用地點</Label>
                    <select
                        id="location_id"
                        v-model="form.location_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="l in locations"
                            :key="l.id"
                            :value="String(l.id)"
                        >
                            {{ l.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.location_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="auth_level">借用權限等級</Label>
                    <Input
                        id="auth_level"
                        v-model.number="form.auth_level"
                        type="number"
                        min="0"
                    />
                    <p class="text-xs text-muted-foreground">0 = 訪客可借</p>
                    <InputError :message="form.errors.auth_level" />
                </div>
                <div class="grid gap-2">
                    <Label for="remark">備註</Label>
                    <Textarea id="remark" v-model="form.remark" rows="2" />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">取消</Button>
                <Button
                    type="submit"
                    form="pool-form"
                    :disabled="form.processing"
                    >儲存</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
