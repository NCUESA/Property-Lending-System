<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    PackageCheck,
    PackageOpen,
    Undo2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
import { formatDate, formatDateTime } from '@/lib/format';
import {
    cancel,
    extend,
    index,
    lend,
    returnMethod,
    update,
} from '@/routes/admin/borrows';
import type { BorrowItem, BorrowOverview } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '借用單', href: index() },
            { title: '借用單明細', href: '#' },
        ],
    },
});

const props = defineProps<{
    borrow: BorrowOverview & { items: BorrowItem[] };
    defaultDepositAmount: number;
    depositBoxCount: number;
}>();

const items = computed(() => props.borrow.items);
const pendingItems = computed(() =>
    items.value.filter((i) => i.status === 'pending'),
);
const lentItems = computed(() =>
    items.value.filter((i) => i.status === 'lent'),
);

const canLend = computed(
    () => props.borrow.lent_at === null && pendingItems.value.length > 0,
);
const canReturnItems = computed(() => lentItems.value.length > 0);
const owesDeposit = computed(
    () =>
        props.borrow.lent_at !== null &&
        ((props.borrow.id_card_taken && !props.borrow.id_card_returned) ||
            (props.borrow.deposit_taken && !props.borrow.deposit_returned)),
);

const itemLabel = (i: BorrowItem) =>
    i.property ? `${i.property.name}` : (i.pool?.name ?? '—');
const outstanding = (i: BorrowItem) => i.quantity - i.returned_quantity;

// ── 出借 ─────────────────────────────────────────
type Selection = Record<number, { checked: boolean; quantity: number }>;

const lendSelection = reactive<Selection>({});

const lendForm = useForm({
    items: [] as { id: number; quantity?: number }[],
    // 預設未勾：和器材一樣要值班人員主動確認收到，未勾時欄位會顯示警示色
    id_card_taken: false,
    deposit_taken: false,
    deposit_amount: props.defaultDepositAmount as number | undefined,
    deposit_box_no: undefined as number | undefined,
});

const lendCount = computed(
    () => Object.values(lendSelection).filter((s) => s.checked).length,
);

// ── 條碼掃描 ──────────────────────────────────────
// 掃描器的行為等同鍵盤輸入後按 Enter，所以只要監聽 Enter 就好。
// 掃到只是「標記已交付」，實際出借仍要按下方的確認按鈕。
const scanInput = ref('');
const scanFeedback = ref<{ ok: boolean; message: string } | null>(null);
const scanBox = ref<HTMLInputElement | null>(null);

function handleScan() {
    const code = scanInput.value.trim().toUpperCase();
    scanInput.value = '';

    if (!code) return;

    const item = pendingItems.value.find(
        (i) => i.property?.ssid?.toUpperCase() === code,
    );

    if (!item) {
        const elsewhere = items.value.find(
            (i) => i.property?.ssid?.toUpperCase() === code,
        );

        scanFeedback.value = {
            ok: false,
            message: elsewhere
                ? `${code} 已經處理過了（${elsewhere.status === 'lent' ? '已出借' : '不在待出借清單'}）`
                : `${code} 不在這張借用單裡`,
        };

        return;
    }

    const already = lendSelection[item.id]?.checked;
    lendSelection[item.id].checked = true;

    scanFeedback.value = {
        ok: true,
        message: already
            ? `${code} ${itemLabel(item)}（已掃過）`
            : `${code} ${itemLabel(item)} 已標記交付`,
    };
}

function submitLend() {
    lendForm
        .transform((data) => ({
            ...data,
            items: pendingItems.value
                .filter((i) => lendSelection[i.id]?.checked)
                .map((i) =>
                    i.property_pool_id
                        ? { id: i.id, quantity: lendSelection[i.id].quantity }
                        : { id: i.id },
                ),
        }))
        .post(lend.url(props.borrow.id), { preserveScroll: true });
}

// ── 歸還 ─────────────────────────────────────────
const returnSelection = reactive<Selection>({});

/**
 * 出借、歸還後 Inertia 只更新 props、不會重跑 setup，
 * 所以明細一變就重建勾選狀態（預設全選、數量為上限）。
 */
function resetSelection(
    target: Selection,
    list: BorrowItem[],
    quantity: (i: BorrowItem) => number,
    checked = true,
) {
    for (const key of Object.keys(target)) {
        delete target[Number(key)];
    }

    for (const item of list) {
        target[item.id] = { checked, quantity: quantity(item) };
    }
}

watch(
    items,
    () => {
        // 出借預設不勾：有沒有交付出去要靠掃條碼（或人工勾選）確認
        resetSelection(
            lendSelection,
            pendingItems.value,
            (i) => i.quantity,
            false,
        );
        scanFeedback.value = null;
        resetSelection(returnSelection, lentItems.value, outstanding);
    },
    { immediate: true },
);

const returnForm = useForm({
    items: [] as { id: number; quantity?: number }[],
    id_card_returned: false,
    deposit_returned: false,
});

const returnCount = computed(
    () => lentItems.value.filter((i) => returnSelection[i.id]?.checked).length,
);
const returnsEverything = computed(
    () =>
        lentItems.value.length > 0 &&
        lentItems.value.every(
            (i) =>
                returnSelection[i.id]?.checked &&
                returnSelection[i.id].quantity === outstanding(i),
        ),
);

function submitReturn() {
    returnForm
        .transform((data) => ({
            ...data,
            items: lentItems.value
                .filter((i) => returnSelection[i.id]?.checked)
                .map((i) =>
                    i.property_pool_id
                        ? { id: i.id, quantity: returnSelection[i.id].quantity }
                        : { id: i.id },
                ),
        }))
        .post(returnMethod.url(props.borrow.id), { preserveScroll: true });
}

// ── 退回、備註 ────────────────────────────────────
const cancelForm = useForm({ reason: '' });
const submitCancel = () =>
    cancelForm.post(cancel.url(props.borrow.id), { preserveScroll: true });

const updateForm = useForm({ remark: props.borrow.remark ?? '' });
const submitUpdate = () =>
    updateForm.patch(update.url(props.borrow.id), { preserveScroll: true });

// ── 展延 ─────────────────────────────────────────
const extendForm = useForm({
    due_date: formatDate(props.borrow.due_date),
    reason: '',
});
const submitExtend = () =>
    extendForm.post(extend.url(props.borrow.id), {
        preserveScroll: true,
        onSuccess: () => extendForm.reset('reason'),
    });
</script>

<template>
    <Head :title="`借用單 #${borrow.id}`" />

    <div class="space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-3">
            <Button variant="ghost" size="icon" as-child>
                <a :href="index().url" aria-label="回列表"
                    ><ArrowLeft class="size-4"
                /></a>
            </Button>
            <h1 class="text-xl font-semibold">借用單 #{{ borrow.id }}</h1>
            <StatusBadge kind="request" :status="borrow.status" />
            <span class="ml-auto text-sm text-muted-foreground"
                >送出於 {{ formatDateTime(borrow.created_at) }}</span
            >
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader
                    ><CardTitle class="text-base">借用人</CardTitle></CardHeader
                >
                <CardContent class="space-y-1 text-sm">
                    <div class="font-medium">
                        {{ borrow.borrower_department }}
                    </div>
                    <div>{{ borrow.borrower_name }}</div>
                    <div class="tabular-nums">{{ borrow.phone }}</div>
                    <div class="break-all text-muted-foreground">
                        {{ borrow.email }}
                    </div>
                    <div
                        v-if="borrow.borrower"
                        class="pt-1 text-xs text-muted-foreground"
                    >
                        登入帳號：{{ borrow.borrower.name }}
                        {{ borrow.borrower.student_id ?? '' }}
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader
                    ><CardTitle class="text-base">日期</CardTitle></CardHeader
                >
                <CardContent class="space-y-1 text-sm">
                    <div>取用地點：{{ borrow.location?.name }}</div>
                    <div>
                        借用日：<span class="tabular-nums">{{
                            formatDate(borrow.borrow_date)
                        }}</span>
                    </div>
                    <div
                        :class="
                            borrow.status === 'overdue'
                                ? 'font-medium text-red-600'
                                : ''
                        "
                    >
                        歸還日：<span class="tabular-nums">{{
                            formatDate(borrow.due_date)
                        }}</span>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader
                    ><CardTitle class="text-base"
                        >出借與歸還</CardTitle
                    ></CardHeader
                >
                <CardContent class="space-y-1 text-sm">
                    <template v-if="borrow.lent_at">
                        <div>
                            出借：{{ formatDateTime(borrow.lent_at) }}（{{
                                borrow.lender?.name ??
                                borrow.legacy_lender_name ??
                                '—'
                            }}）
                        </div>
                        <div>
                            證件：{{
                                borrow.id_card_taken
                                    ? borrow.id_card_returned
                                        ? '已退還'
                                        : '保管中'
                                    : '未收'
                            }}； 押金：{{
                                borrow.deposit_taken
                                    ? `NT$ ${borrow.deposit_amount ?? ''}（${borrow.deposit_returned ? '已退還' : '保管中'}）`
                                    : '未收'
                            }}
                        </div>
                        <div v-if="borrow.deposit_box_no !== null">
                            押金箱號：{{ borrow.deposit_box_no }}
                        </div>
                        <div v-if="borrow.returned_at">
                            結案：{{ formatDateTime(borrow.returned_at) }}（{{
                                borrow.returner?.name ??
                                borrow.legacy_returner_name ??
                                '—'
                            }}）
                        </div>
                    </template>
                    <div v-else class="text-muted-foreground">尚未出借</div>
                </CardContent>
            </Card>
        </div>

        <!-- 明細 -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">借用明細</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>財產編號／品項</TableHead>
                            <TableHead>名稱</TableHead>
                            <TableHead class="text-right">數量</TableHead>
                            <TableHead>狀態</TableHead>
                            <TableHead>歸還</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in items" :key="item.id">
                            <TableCell class="font-mono text-xs">{{
                                item.property?.ssid ?? '大量借用'
                            }}</TableCell>
                            <TableCell>
                                {{ itemLabel(item) }}
                                <span
                                    v-if="item.property?.location"
                                    class="text-xs text-muted-foreground"
                                    >（{{ item.property.location.name }}）</span
                                >
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                <template v-if="item.property_pool_id"
                                    >{{ item.returned_quantity }} /
                                    {{ item.quantity }}</template
                                >
                                <template v-else>1</template>
                            </TableCell>
                            <TableCell
                                ><StatusBadge kind="item" :status="item.status"
                            /></TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                <template v-if="item.returned_at"
                                    >{{ formatDateTime(item.returned_at) }}
                                    {{ item.returner?.name }}</template
                                >
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- 出借 -->
        <Card v-if="canLend" class="border-primary/40">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base"
                    ><PackageOpen class="size-4" />出借</CardTitle
                >
                <CardDescription
                    >勾選實際交給借用人的器材；沒勾到的會退回並釋出給其他人借</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="scan">掃描財產條碼</Label>
                    <Input
                        id="scan"
                        ref="scanBox"
                        v-model="scanInput"
                        autofocus
                        autocomplete="off"
                        placeholder="掃描器讀取後會自動標記；也可以手動勾選"
                        class="font-mono"
                        @keydown.enter.prevent="handleScan"
                    />
                    <p
                        v-if="scanFeedback"
                        class="text-sm"
                        :class="
                            scanFeedback.ok
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-destructive'
                        "
                        role="status"
                    >
                        {{ scanFeedback.message }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        已標記 {{ lendCount }} / {{ pendingItems.length }} 項
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        v-for="item in pendingItems"
                        :key="item.id"
                        class="flex items-center gap-3 rounded-md border p-2 text-sm transition-colors"
                        :class="
                            lendSelection[item.id].checked
                                ? 'border-emerald-500/60 bg-emerald-50 dark:bg-emerald-950/30'
                                : ''
                        "
                    >
                        <Checkbox
                            :model-value="lendSelection[item.id].checked"
                            @update:model-value="
                                lendSelection[item.id].checked = $event === true
                            "
                        />
                        <span class="font-mono text-xs text-muted-foreground">{{
                            item.property?.ssid ?? '大量借用'
                        }}</span>
                        <span class="flex-1">{{ itemLabel(item) }}</span>
                        <template v-if="item.property_pool_id">
                            <Input
                                v-model.number="lendSelection[item.id].quantity"
                                type="number"
                                min="1"
                                :max="item.quantity"
                                class="w-20"
                                :aria-label="`${itemLabel(item)} 出借數量`"
                            />
                            <span class="text-xs text-muted-foreground"
                                >/ {{ item.quantity }}</span
                            >
                        </template>
                    </label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- 沒收到證件／押金是最常見的疏漏，所以未勾選時用警示色提醒 -->
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-md border-2 p-3 text-sm transition-colors"
                        :class="
                            lendForm.id_card_taken
                                ? 'border-emerald-500/60 bg-emerald-50 dark:bg-emerald-950/30'
                                : 'border-amber-500/60 bg-amber-50 dark:bg-amber-950/30'
                        "
                    >
                        <Checkbox
                            :model-value="lendForm.id_card_taken"
                            @update:model-value="
                                lendForm.id_card_taken = $event === true
                            "
                        />
                        <span>
                            <span class="font-medium">已收取證件</span>
                            <span
                                v-if="!lendForm.id_card_taken"
                                class="block text-xs text-amber-700 dark:text-amber-400"
                                >尚未收取</span
                            >
                        </span>
                    </label>
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-md border-2 p-3 text-sm transition-colors"
                        :class="
                            lendForm.deposit_taken
                                ? 'border-emerald-500/60 bg-emerald-50 dark:bg-emerald-950/30'
                                : 'border-amber-500/60 bg-amber-50 dark:bg-amber-950/30'
                        "
                    >
                        <Checkbox
                            :model-value="lendForm.deposit_taken"
                            @update:model-value="
                                lendForm.deposit_taken = $event === true
                            "
                        />
                        <span>
                            <span class="font-medium">已收取押金</span>
                            <span
                                v-if="!lendForm.deposit_taken"
                                class="block text-xs text-amber-700 dark:text-amber-400"
                                >尚未收取</span
                            >
                        </span>
                    </label>
                    <div v-if="lendForm.deposit_taken" class="grid gap-2">
                        <Label for="deposit_amount">押金金額</Label>
                        <Input
                            id="deposit_amount"
                            v-model.number="lendForm.deposit_amount"
                            type="number"
                            min="0"
                        />
                        <InputError :message="lendForm.errors.deposit_amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="deposit_box_no">押金箱號</Label>
                        <select
                            id="deposit_box_no"
                            v-model="lendForm.deposit_box_no"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option :value="undefined">未使用</option>
                            <option
                                v-for="n in depositBoxCount"
                                :key="n"
                                :value="n"
                            >
                                {{ n }} 號
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            箱數可在「系統設定 → 借用規則」調整
                        </p>
                        <InputError :message="lendForm.errors.deposit_box_no" />
                    </div>
                </div>
                <InputError :message="lendForm.errors.items" />
                <div class="flex flex-wrap gap-2">
                    <Button
                        :disabled="lendForm.processing || lendCount === 0"
                        @click="submitLend"
                    >
                        <Spinner v-if="lendForm.processing" />確認出借（{{
                            lendCount
                        }}
                        項）
                    </Button>

                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="outline"
                                ><Undo2 class="mr-1 size-4" />整張退回</Button
                            >
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle
                                    >退回借用單 #{{ borrow.id }}</DialogTitle
                                >
                                <DialogDescription
                                    >所有申請中的器材會釋出給其他人借用，這個動作無法復原。</DialogDescription
                                >
                            </DialogHeader>
                            <div class="grid gap-2">
                                <Label for="reason">退回原因（選填）</Label>
                                <Textarea
                                    id="reason"
                                    v-model="cancelForm.reason"
                                    placeholder="例如：未帶證件"
                                />
                                <InputError
                                    :message="cancelForm.errors.reason"
                                />
                            </div>
                            <DialogFooter>
                                <DialogClose as-child
                                    ><Button variant="outline"
                                        >取消</Button
                                    ></DialogClose
                                >
                                <Button
                                    variant="destructive"
                                    :disabled="cancelForm.processing"
                                    @click="submitCancel"
                                    >確認退回</Button
                                >
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardContent>
        </Card>

        <!-- 歸還 -->
        <Card
            v-if="canReturnItems || owesDeposit"
            class="border-emerald-500/40"
        >
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base"
                    ><PackageCheck class="size-4" />歸還</CardTitle
                >
                <CardDescription
                    >可以只還一部分；全部還完時借用單自動結案</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="canReturnItems" class="space-y-2">
                    <label
                        v-for="item in lentItems"
                        :key="item.id"
                        class="flex items-center gap-3 rounded-md border p-2 text-sm"
                    >
                        <Checkbox
                            :model-value="returnSelection[item.id].checked"
                            @update:model-value="
                                returnSelection[item.id].checked =
                                    $event === true
                            "
                        />
                        <span class="font-mono text-xs text-muted-foreground">{{
                            item.property?.ssid ?? '大量借用'
                        }}</span>
                        <span class="flex-1">{{ itemLabel(item) }}</span>
                        <template v-if="item.property_pool_id">
                            <Input
                                v-model.number="
                                    returnSelection[item.id].quantity
                                "
                                type="number"
                                min="1"
                                :max="outstanding(item)"
                                class="w-20"
                                :aria-label="`${itemLabel(item)} 歸還數量`"
                            />
                            <span class="text-xs text-muted-foreground"
                                >/ 未還 {{ outstanding(item) }}</span
                            >
                        </template>
                    </label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label
                        v-if="borrow.id_card_taken && !borrow.id_card_returned"
                        class="flex items-center justify-between rounded-md border p-3 text-sm"
                    >
                        退還證件
                        <Switch v-model="returnForm.id_card_returned" />
                    </label>
                    <label
                        v-if="borrow.deposit_taken && !borrow.deposit_returned"
                        class="flex items-center justify-between rounded-md border p-3 text-sm"
                    >
                        退還押金 NT$ {{ borrow.deposit_amount }}
                        <Switch v-model="returnForm.deposit_returned" />
                    </label>
                </div>
                <p
                    v-if="
                        returnsEverything &&
                        owesDeposit &&
                        !(
                            returnForm.id_card_returned ||
                            returnForm.deposit_returned
                        )
                    "
                    class="text-sm text-amber-600"
                >
                    器材全部歸還，記得一併退還證件與押金。
                </p>
                <InputError :message="returnForm.errors.items" />
                <Button
                    variant="secondary"
                    :disabled="
                        returnForm.processing ||
                        (returnCount === 0 &&
                            !returnForm.id_card_returned &&
                            !returnForm.deposit_returned)
                    "
                    @click="submitReturn"
                >
                    <Spinner v-if="returnForm.processing" />登記歸還{{
                        returnCount ? `（${returnCount} 項）` : ''
                    }}
                </Button>
            </CardContent>
        </Card>

        <!-- 展延 -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base"
                    ><CalendarClock class="size-4" />展延歸還日</CardTitle
                >
                <CardDescription>
                    目前歸還日
                    {{ formatDate(borrow.due_date) }}。展延需填寫理由，
                    只能往後延；延後後提醒信會依新的日期重新寄出。
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-if="borrow.extended_at"
                    class="rounded-md border bg-muted/40 p-3 text-sm"
                >
                    <span class="text-muted-foreground"
                        >最近一次展延：{{ formatDateTime(borrow.extended_at) }}
                        <template v-if="borrow.extender"
                            >（{{ borrow.extender.name }}）</template
                        ></span
                    >
                    <p class="mt-1 whitespace-pre-line">
                        {{ borrow.extension_reason }}
                    </p>
                </div>

                <form
                    class="grid gap-4 sm:grid-cols-[12rem_1fr]"
                    @submit.prevent="submitExtend"
                >
                    <div class="grid content-start gap-2">
                        <Label for="extend_due_date">新的歸還日</Label>
                        <Input
                            id="extend_due_date"
                            v-model="extendForm.due_date"
                            type="date"
                            :min="formatDate(borrow.due_date)"
                        />
                        <InputError :message="extendForm.errors.due_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="extend_reason">展延理由</Label>
                        <Textarea
                            id="extend_reason"
                            v-model="extendForm.reason"
                            rows="3"
                            placeholder="例如：活動延期至下週"
                            required
                        />
                        <InputError :message="extendForm.errors.reason" />
                    </div>
                    <div class="sm:col-span-2">
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="extendForm.processing"
                            >確認展延</Button
                        >
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- 備註 -->
        <Card>
            <CardHeader
                ><CardTitle class="text-base">備註</CardTitle></CardHeader
            >
            <CardContent>
                <form class="grid gap-4" @submit.prevent="submitUpdate">
                    <div class="grid gap-2">
                        <Textarea
                            id="remark"
                            v-model="updateForm.remark"
                            rows="3"
                            aria-label="備註"
                        />
                        <InputError :message="updateForm.errors.remark" />
                    </div>
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="
                                updateForm.processing || !updateForm.isDirty
                            "
                            >儲存</Button
                        >
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
