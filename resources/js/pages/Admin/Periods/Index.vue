<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, TriangleAlert, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, todayInTaipei } from '@/lib/format';
import { destroy, index, store, update } from '@/routes/admin/periods';

type Period = {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    is_open: boolean;
    note: string | null;
};

defineOptions({
    layout: { breadcrumbs: [{ title: '借用期間', href: index() }] },
});

defineProps<{
    periods: Period[];
    openMode: { value: string; label: string };
    effective: boolean;
    settingsUrl: string;
}>();

const today = todayInTaipei();
const isCurrent = (p: Period) =>
    formatDate(p.starts_on) <= today && today <= formatDate(p.ends_on);

const open = ref(false);
const editing = ref<Period | null>(null);
const form = useForm({
    name: '',
    starts_on: '',
    ends_on: '',
    is_open: false,
    note: '',
});

function openDialog(period: Period | null) {
    editing.value = period;
    form.clearErrors();
    form.defaults({
        name: period?.name ?? '',
        starts_on: period ? formatDate(period.starts_on) : today,
        ends_on: period ? formatDate(period.ends_on) : today,
        is_open: period?.is_open ?? false,
        note: period?.note ?? '',
    });
    form.reset();
    open.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };
    if (editing.value) form.put(update.url(editing.value.id), options);
    else form.post(store.url(), options);
}

function remove(period: Period) {
    if (window.confirm(`確定刪除「${period.name}」？`))
        router.delete(destroy.url(period.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="借用期間" />

    <div class="space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="借用期間"
                description="設定寒暑假等暫停借用（或特別開放）的期間"
            />
            <Button @click="openDialog(null)"
                ><Plus class="mr-1 size-4" />新增期間</Button
            >
        </div>

        <!-- 開放方式是「預設開放借用」時，這一頁設什麼都不會生效 -->
        <Alert v-if="!effective" variant="destructive">
            <TriangleAlert class="size-4" />
            <AlertTitle>此頁目前不生效</AlertTitle>
            <AlertDescription>
                開放方式是「{{ openMode.label }}」，所有日期一律開放借用，
                以下期間設定不會套用。要讓它生效，請到
                <a
                    :href="settingsUrl"
                    class="font-medium underline underline-offset-4"
                    >系統設定 → 借用規則</a
                >
                改選「設定內時間開放借用」。
            </AlertDescription>
        </Alert>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>名稱</TableHead>
                        <TableHead>期間</TableHead>
                        <TableHead>借用</TableHead>
                        <TableHead>說明</TableHead>
                        <TableHead class="w-28"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="p in periods" :key="p.id">
                        <TableCell class="font-medium">
                            {{ p.name }}
                            <Badge
                                v-if="isCurrent(p)"
                                variant="secondary"
                                class="ml-1"
                                >進行中</Badge
                            >
                        </TableCell>
                        <TableCell class="tabular-nums"
                            >{{ formatDate(p.starts_on) }} ～
                            {{ formatDate(p.ends_on) }}</TableCell
                        >
                        <TableCell>
                            <Badge
                                :class="
                                    p.is_open
                                        ? 'border-transparent bg-emerald-100 text-emerald-800'
                                        : 'border-transparent bg-red-100 text-red-800'
                                "
                            >
                                {{ p.is_open ? '開放' : '暫停' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-sm text-muted-foreground">{{
                            p.note
                        }}</TableCell>
                        <TableCell class="text-right">
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="編輯"
                                @click="openDialog(p)"
                                ><Pencil class="size-4"
                            /></Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="刪除"
                                @click="remove(p)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="!periods.length">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                            >尚未設定任何期間</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? '編輯期間' : '新增期間'
                }}</DialogTitle>
                <DialogDescription>期間不能互相重疊</DialogDescription>
            </DialogHeader>
            <form id="period-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">名稱</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="寒假"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="starts_on">開始</Label>
                        <Input
                            id="starts_on"
                            v-model="form.starts_on"
                            type="date"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ends_on">結束</Label>
                        <Input
                            id="ends_on"
                            v-model="form.ends_on"
                            type="date"
                            :min="form.starts_on"
                            required
                        />
                    </div>
                </div>
                <InputError
                    :message="form.errors.starts_on || form.errors.ends_on"
                />
                <label
                    class="flex items-center justify-between rounded-md border p-3 text-sm"
                >
                    這段期間開放借用
                    <Switch v-model="form.is_open" />
                </label>
                <div class="grid gap-2">
                    <Label for="note">說明</Label>
                    <Textarea id="note" v-model="form.note" rows="2" />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">取消</Button>
                <Button
                    type="submit"
                    form="period-form"
                    :disabled="form.processing"
                    >儲存</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
