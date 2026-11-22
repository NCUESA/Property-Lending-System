<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Ban, CalendarClock, Inbox, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import { cancel } from '@/routes/my/borrows';
import type { BorrowItemStatus, BorrowRequestStatus, Paginator } from '@/types';

type MyItem = {
    id: number;
    name: string;
    ssid: string | null;
    quantity: number;
    status: BorrowItemStatus;
};

type MyBorrow = {
    id: number;
    status: BorrowRequestStatus;
    location: string;
    borrow_date: string;
    due_date: string;
    returned_at: string | null;
    item_count: number;
    cancellable: boolean;
    items: MyItem[];
};

defineProps<{
    active: MyBorrow[];
    history: Paginator<MyBorrow>;
    block: { reason: string; blocked_until: string } | null;
    violations: {
        kind: string;
        label: string;
        occurred_at: string;
        borrow_request_id: number | null;
        note: string | null;
    }[];
}>();

const cancelling = ref<MyBorrow | null>(null);
const form = useForm({ reason: '' });

function confirmCancel() {
    if (!cancelling.value) return;

    form.post(cancel.url(cancelling.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelling.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="我的借用" />

    <Heading title="我的借用" description="查看借用進度與歷史紀錄" />

    <div class="space-y-6">
        <Alert v-if="block" variant="destructive">
            <Ban class="size-4" />
            <AlertTitle>目前暫停借用</AlertTitle>
            <AlertDescription>
                {{ block.reason }}，將於 {{ block.blocked_until }}
                解除。如有疑問請洽學生會。
            </AlertDescription>
        </Alert>

        <!-- 進行中 -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CalendarClock class="size-4" />進行中
                </CardTitle>
                <CardDescription>
                    尚未取件的申請可以自行取消；取消次數過多會暫停借用資格。
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!active.length"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    目前沒有進行中的借用。
                </p>

                <ul v-else class="space-y-3">
                    <li
                        v-for="borrow in active"
                        :key="borrow.id"
                        class="rounded-lg border p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium"
                                        >#{{ borrow.id }}</span
                                    >
                                    <StatusBadge
                                        kind="request"
                                        :status="borrow.status"
                                    />
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ borrow.location }}ㆍ{{
                                        borrow.borrow_date
                                    }}
                                    ~ {{ borrow.due_date }}ㆍ{{
                                        borrow.item_count
                                    }}
                                    件
                                </p>
                            </div>

                            <Button
                                v-if="borrow.cancellable"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="cancelling = borrow"
                            >
                                取消申請
                            </Button>
                        </div>

                        <ul
                            class="mt-3 grid gap-1 text-sm text-muted-foreground sm:grid-cols-2"
                        >
                            <li
                                v-for="item in borrow.items"
                                :key="item.id"
                                class="flex items-center gap-2"
                            >
                                <StatusBadge
                                    kind="item"
                                    :status="item.status"
                                />
                                <span>
                                    {{ item.name
                                    }}<template v-if="item.ssid">
                                        （{{ item.ssid }}）</template
                                    ><template v-else-if="item.quantity > 1">
                                        × {{ item.quantity }}</template
                                    >
                                </span>
                            </li>
                        </ul>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- 違規紀錄 -->
        <Card v-if="violations.length">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <TriangleAlert class="size-4" />我的違規紀錄
                </CardTitle>
            </CardHeader>
            <CardContent>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="(v, i) in violations"
                        :key="i"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span class="font-medium">{{ v.label }}</span>
                        <span class="text-muted-foreground">
                            {{ v.occurred_at }}
                            <template v-if="v.borrow_request_id">
                                ㆍ#{{ v.borrow_request_id }}
                            </template>
                            <template v-if="v.note">ㆍ{{ v.note }}</template>
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- 歷史 -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Inbox class="size-4" />歷史紀錄
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!history.data.length"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    還沒有已結束的借用紀錄。
                </p>

                <ul v-else class="divide-y">
                    <li
                        v-for="borrow in history.data"
                        :key="borrow.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-medium">#{{ borrow.id }}</span>
                            <StatusBadge
                                kind="request"
                                :status="borrow.status"
                            />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ borrow.location }}ㆍ{{ borrow.borrow_date }} ~
                            {{ borrow.due_date }}ㆍ{{ borrow.item_count }} 件
                        </p>
                    </li>
                </ul>

                <PaginationLinks :paginator="history" />
            </CardContent>
        </Card>
    </div>

    <!-- 取消確認 -->
    <Dialog
        :open="cancelling !== null"
        @update:open="!$event && (cancelling = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>取消借用申請 #{{ cancelling?.id }}</DialogTitle>
                <DialogDescription>
                    取消後器材會立刻釋出給其他人借用，這個動作無法復原，而且會留下一筆違規紀錄。
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="reason">取消原因（選填）</Label>
                <Input
                    id="reason"
                    v-model="form.reason"
                    maxlength="200"
                    placeholder="例如：活動取消"
                />
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="cancelling = null"
                >
                    再想想
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="form.processing"
                    @click="confirmCancel"
                >
                    <Spinner v-if="form.processing" />
                    確認取消
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
