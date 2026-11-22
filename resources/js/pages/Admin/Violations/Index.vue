<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ban, CalendarCog, Search, X } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { useQueryFilters } from '@/composables/useQueryFilters';
import { todayInTaipei } from '@/lib/format';
import { adjust, lift } from '@/routes/admin/blocks';
import { show as showBorrow } from '@/routes/admin/borrows';
import { index } from '@/routes/admin/violations';
import type { Option, Paginator } from '@/types';

type Violation = {
    id: number;
    kind: string;
    label: string;
    borrower_name: string;
    student_id: string | null;
    phone: string;
    borrow_request_id: number | null;
    occurred_at: string;
    note: string | null;
};

type Block = {
    id: number;
    borrower_name: string;
    student_id: string | null;
    phone: string;
    reason: string;
    blocked_until: string;
    until_date: string;
    automatic: boolean;
    adjusted: boolean;
    adjust_reason: string | null;
};

defineOptions({
    layout: { breadcrumbs: [{ title: '違規紀錄', href: index() }] },
});

const props = defineProps<{
    filters: { kind?: string; q?: string };
    violations: Paginator<Violation>;
    blocks: Block[];
    kinds: Option[];
}>();

const { filters, reset } = useQueryFilters(index.url(), {
    kind: props.filters.kind ?? '',
    q: props.filters.q ?? '',
});

const today = todayInTaipei();

const lifting = ref<Block | null>(null);
const liftForm = useForm({ reason: '' });

const adjusting = ref<Block | null>(null);
const adjustForm = useForm({ until: '', reason: '' });

function startAdjust(block: Block) {
    adjustForm.reset();
    adjustForm.until = block.until_date;
    adjusting.value = block;
}

function confirmAdjust() {
    if (!adjusting.value) return;

    adjustForm.patch(adjust.url(adjusting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            adjusting.value = null;
            adjustForm.reset();
        },
    });
}

function confirmLift() {
    if (!lifting.value) return;

    liftForm.post(lift.url(lifting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            lifting.value = null;
            liftForm.reset();
        },
    });
}

/** 點名字就把搜尋框填成那個人，快速看他全部的紀錄。 */
function focusOn(person: Violation | Block) {
    filters.q = person.student_id || person.phone;
}
</script>

<template>
    <Head title="違規紀錄" />

    <div class="space-y-6 p-4 md:p-6">
        <Heading
            title="違規紀錄"
            description="自行取消、逾期未取與逾期未還；達上限會自動暫停借用資格"
        />

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Ban class="size-4" />封鎖中
                    <Badge v-if="blocks.length" variant="destructive">
                        {{ blocks.length }}
                    </Badge>
                </CardTitle>
                <CardDescription>
                    被封鎖的人無法送出借用申請。
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!blocks.length"
                    class="py-4 text-center text-sm text-muted-foreground"
                >
                    目前沒有人被封鎖。
                </p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>借用人</TableHead>
                            <TableHead>原因</TableHead>
                            <TableHead>解除時間</TableHead>
                            <TableHead class="w-48" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="block in blocks" :key="block.id">
                            <TableCell>
                                <button
                                    type="button"
                                    class="font-medium underline-offset-4 hover:underline"
                                    @click="focusOn(block)"
                                >
                                    {{ block.borrower_name }}
                                </button>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ block.student_id ?? '—' }}ㆍ{{
                                        block.phone
                                    }}
                                </span>
                            </TableCell>
                            <TableCell class="space-x-2 text-sm">
                                <span>{{ block.reason }}</span>
                                <Badge
                                    v-if="block.automatic"
                                    variant="secondary"
                                >
                                    系統自動
                                </Badge>
                            </TableCell>
                            <TableCell class="text-sm">
                                {{ block.blocked_until }}
                                <span
                                    v-if="block.adjusted"
                                    class="block text-xs text-muted-foreground"
                                >
                                    已調整：{{ block.adjust_reason }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="startAdjust(block)"
                                    >
                                        <CalendarCog class="size-4" />改期限
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="lifting = block"
                                    >
                                        解封
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>所有違規紀錄</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative max-w-xs flex-1">
                        <Search
                            class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="filters.q"
                            class="pl-8"
                            placeholder="姓名、學號或手機"
                            aria-label="搜尋違規紀錄"
                        />
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        :variant="filters.kind === '' ? 'default' : 'outline'"
                        @click="filters.kind = ''"
                    >
                        全部
                    </Button>
                    <Button
                        v-for="kind in kinds"
                        :key="kind.value"
                        type="button"
                        size="sm"
                        :variant="
                            filters.kind === kind.value ? 'default' : 'outline'
                        "
                        @click="filters.kind = kind.value"
                    >
                        {{ kind.label }}
                    </Button>

                    <Button
                        v-if="filters.q || filters.kind"
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="reset()"
                    >
                        <X class="size-4" />清除
                    </Button>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>時間</TableHead>
                            <TableHead>種類</TableHead>
                            <TableHead>借用人</TableHead>
                            <TableHead>借用單</TableHead>
                            <TableHead>說明</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="v in violations.data" :key="v.id">
                            <TableCell class="text-sm whitespace-nowrap">
                                {{ v.occurred_at }}
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary">{{ v.label }}</Badge>
                            </TableCell>
                            <TableCell>
                                <button
                                    type="button"
                                    class="font-medium underline-offset-4 hover:underline"
                                    @click="focusOn(v)"
                                >
                                    {{ v.borrower_name }}
                                </button>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ v.student_id ?? '—' }}ㆍ{{ v.phone }}
                                </span>
                            </TableCell>
                            <TableCell class="text-sm">
                                <button
                                    v-if="v.borrow_request_id"
                                    type="button"
                                    class="underline-offset-4 hover:underline"
                                    @click="
                                        router.visit(
                                            showBorrow.url(v.borrow_request_id),
                                        )
                                    "
                                >
                                    #{{ v.borrow_request_id }}
                                </button>
                                <span v-else>—</span>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ v.note ?? '—' }}
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!violations.data.length">
                            <TableCell
                                colspan="5"
                                class="py-8 text-center text-muted-foreground"
                            >
                                沒有符合條件的違規紀錄。
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <PaginationLinks :paginator="violations" />
            </CardContent>
        </Card>
    </div>

    <Dialog
        :open="adjusting !== null"
        @update:open="!$event && (adjusting = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    調整 {{ adjusting?.borrower_name }} 的封鎖期限
                </DialogTitle>
                <DialogDescription>
                    封鎖會持續到所選日期結束。要立刻放行請改用「解封」。
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="until">封鎖至（含當天）</Label>
                    <Input
                        id="until"
                        v-model="adjustForm.until"
                        type="date"
                        :min="today"
                        required
                    />
                    <p
                        v-if="adjustForm.errors.until"
                        class="text-sm text-destructive"
                    >
                        {{ adjustForm.errors.until }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="adjust_reason">調整原因</Label>
                    <Input
                        id="adjust_reason"
                        v-model="adjustForm.reason"
                        maxlength="200"
                        placeholder="例如：情節嚴重，延長至週末"
                        required
                    />
                    <p
                        v-if="adjustForm.errors.reason"
                        class="text-sm text-destructive"
                    >
                        {{ adjustForm.errors.reason }}
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="adjusting = null"
                >
                    取消
                </Button>
                <Button
                    type="button"
                    :disabled="
                        adjustForm.processing ||
                        !adjustForm.until ||
                        !adjustForm.reason
                    "
                    @click="confirmAdjust"
                >
                    儲存
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="lifting !== null" @update:open="!$event && (lifting = null)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    解除 {{ lifting?.borrower_name }} 的封鎖
                </DialogTitle>
                <DialogDescription>
                    解封後這個人馬上就能再送借用申請，之前的違規不再計入下一次的封鎖判斷。
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="lift_reason">解封原因</Label>
                <Input
                    id="lift_reason"
                    v-model="liftForm.reason"
                    maxlength="200"
                    placeholder="例如：已當面說明，系統誤判"
                    required
                />
                <p
                    v-if="liftForm.errors.reason"
                    class="text-sm text-destructive"
                >
                    {{ liftForm.errors.reason }}
                </p>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="lifting = null">
                    取消
                </Button>
                <Button
                    type="button"
                    :disabled="liftForm.processing || !liftForm.reason"
                    @click="confirmLift"
                >
                    確認解封
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
