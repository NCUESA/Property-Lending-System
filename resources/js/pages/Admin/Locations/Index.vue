<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocationGraph from '@/components/LocationGraph.vue';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { destroy, index, store, update } from '@/routes/admin/locations';
import { update as updateLinks } from '@/routes/admin/locations/links';

export type AdminLocation = {
    id: number;
    code: string;
    name: string;
    ssid_prefix: string;
    is_pickup_point: boolean;
    is_active: boolean;
    sort_order: number;
    properties_count: number;
    pools_count: number;
    prefix_locked: boolean;
    deletable: boolean;
    serves: number[];
};

defineOptions({
    layout: { breadcrumbs: [{ title: '地點', href: index() }] },
});

const props = defineProps<{ locations: AdminLocation[] }>();

/* ── 新增／編輯 ──────────────────────────────────────── */

const editing = ref<AdminLocation | null>(null);
const creating = ref(false);

const form = useForm({
    code: '',
    name: '',
    ssid_prefix: '',
    is_pickup_point: true,
    is_active: true,
    sort_order: 0,
});

function startCreate() {
    form.reset();
    form.clearErrors();
    form.sort_order = (props.locations.at(-1)?.sort_order ?? 0) + 1;
    editing.value = null;
    creating.value = true;
}

function startEdit(location: AdminLocation) {
    form.clearErrors();
    form.defaults({
        code: location.code,
        name: location.name,
        ssid_prefix: location.ssid_prefix,
        is_pickup_point: location.is_pickup_point,
        is_active: location.is_active,
        sort_order: location.sort_order,
    });
    form.reset();
    editing.value = location;
    creating.value = false;
}

function closeDialog() {
    editing.value = null;
    creating.value = false;
    form.defaults({
        code: '',
        name: '',
        ssid_prefix: '',
        is_pickup_point: true,
        is_active: true,
        sort_order: 0,
    });
}

function submit() {
    const options = { preserveScroll: true, onSuccess: closeDialog };

    if (editing.value) {
        form.put(update.url(editing.value.id), options);
    } else {
        form.post(store.url(), options);
    }
}

/** 停用／啟用：只翻一個欄位，其餘沿用原值。 */
function toggleActive(location: AdminLocation) {
    useForm({
        code: location.code,
        name: location.name,
        ssid_prefix: location.ssid_prefix,
        is_pickup_point: location.is_pickup_point,
        is_active: !location.is_active,
        sort_order: location.sort_order,
    }).put(update.url(location.id), { preserveScroll: true });
}

const removing = ref<AdminLocation | null>(null);
const removeForm = useForm({});

function confirmRemove() {
    if (!removing.value) return;

    removeForm.delete(destroy.url(removing.value.id), {
        preserveScroll: true,
        onSuccess: () => (removing.value = null),
    });
}

/* ── 互連關係 ────────────────────────────────────────── */

const pickupPoints = computed(() =>
    props.locations.filter((l) => l.is_pickup_point),
);

/** 一個取用地點可以勾選的對象：自己以外的所有地點。 */
function linkCandidates(pickup: AdminLocation) {
    return props.locations.filter((l) => l.id !== pickup.id);
}

function toggleLink(pickup: AdminLocation, target: AdminLocation) {
    const next = pickup.serves.includes(target.id)
        ? pickup.serves.filter((id) => id !== target.id)
        : [...pickup.serves, target.id];

    useForm({ storage_location_ids: next }).put(updateLinks.url(pickup.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="地點" />

    <div class="space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="地點"
                description="取用地點、存放地點，以及彼此的互連關係"
            />
            <Button type="button" @click="startCreate">
                <Plus class="size-4" />新增地點
            </Button>
        </div>

        <!-- 地點表格 -->
        <Card>
            <CardHeader>
                <CardTitle>地點</CardTitle>
                <CardDescription>
                    「取用地點」會出現在借用申請頁讓使用者選；停用的地點不會出現，但既有資料保留。
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-16">排序</TableHead>
                            <TableHead>名稱</TableHead>
                            <TableHead>代碼</TableHead>
                            <TableHead class="w-20">字首</TableHead>
                            <TableHead>類型</TableHead>
                            <TableHead>器材</TableHead>
                            <TableHead class="w-24">啟用</TableHead>
                            <TableHead class="w-28" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="location in locations"
                            :key="location.id"
                            :class="location.is_active ? '' : 'opacity-50'"
                        >
                            <TableCell
                                class="text-muted-foreground tabular-nums"
                            >
                                {{ location.sort_order }}
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ location.name }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ location.code }}
                            </TableCell>
                            <TableCell>
                                <code class="rounded bg-muted px-1.5 py-0.5">{{
                                    location.ssid_prefix
                                }}</code>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        location.is_pickup_point
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        location.is_pickup_point
                                            ? '取用地點'
                                            : '存放地點'
                                    }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ location.properties_count }} 件
                                <template v-if="location.pools_count">
                                    ／{{ location.pools_count }} 品項
                                </template>
                            </TableCell>
                            <TableCell>
                                <Switch
                                    :model-value="location.is_active"
                                    :aria-label="`啟用 ${location.name}`"
                                    @update:model-value="toggleActive(location)"
                                />
                            </TableCell>
                            <TableCell>
                                <div class="flex gap-1">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        :aria-label="`編輯 ${location.name}`"
                                        @click="startEdit(location)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        :disabled="!location.deletable"
                                        :title="
                                            location.deletable
                                                ? '刪除'
                                                : '還有器材或品項，不能刪除'
                                        "
                                        :aria-label="`刪除 ${location.name}`"
                                        @click="removing = location"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- 互連關係 -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <MapPin class="size-4" />互連關係
                </CardTitle>
                <CardDescription>
                    箭頭代表「在這裡取件，可以借出那裡的器材」。借用申請頁直接讀這張圖，不是寫死的。
                    每個取用地點一定借得到自己的器材，所以不畫自己連自己。
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <LocationGraph :locations="locations" />

                <div class="space-y-3">
                    <h3 class="text-sm font-medium">編輯</h3>
                    <p
                        v-if="!pickupPoints.length"
                        class="text-sm text-muted-foreground"
                    >
                        還沒有任何取用地點。
                    </p>
                    <div
                        v-for="pickup in pickupPoints"
                        :key="pickup.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="mb-2 text-sm font-medium">
                            在「{{ pickup.name }}」取件，可以借出：
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {{ pickup.name }}（自己）
                            </Badge>
                            <Button
                                v-for="target in linkCandidates(pickup)"
                                :key="target.id"
                                type="button"
                                size="sm"
                                :variant="
                                    pickup.serves.includes(target.id)
                                        ? 'default'
                                        : 'outline'
                                "
                                :aria-pressed="
                                    pickup.serves.includes(target.id)
                                "
                                @click="toggleLink(pickup, target)"
                            >
                                {{ target.name }}
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- 新增／編輯 -->
    <Dialog
        :open="creating || editing !== null"
        @update:open="!$event && closeDialog()"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? `編輯「${editing.name}」` : '新增地點' }}
                </DialogTitle>
                <DialogDescription>
                    財產編號字首是編號的第一碼，每個地點不能重複。
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">名稱</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="code">代碼</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            placeholder="jinde"
                            required
                        />
                        <InputError :message="form.errors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ssid_prefix">財產編號字首</Label>
                        <Input
                            id="ssid_prefix"
                            v-model="form.ssid_prefix"
                            maxlength="1"
                            class="uppercase"
                            :disabled="editing?.prefix_locked"
                            required
                        />
                        <p
                            v-if="editing?.prefix_locked"
                            class="text-xs text-muted-foreground"
                        >
                            已經有器材使用這個字首，不能更改
                        </p>
                        <InputError :message="form.errors.ssid_prefix" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sort_order">排序</Label>
                        <Input
                            id="sort_order"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            max="999"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>
                </div>

                <label
                    class="flex items-start justify-between gap-4 rounded-md border p-3 text-sm"
                >
                    <span>
                        取用地點
                        <span class="block text-xs text-muted-foreground">
                            會出現在借用申請頁讓使用者選擇取件地點
                        </span>
                    </span>
                    <Switch v-model="form.is_pickup_point" />
                </label>

                <label
                    class="flex items-start justify-between gap-4 rounded-md border p-3 text-sm"
                >
                    <span>
                        啟用
                        <span class="block text-xs text-muted-foreground">
                            停用後不出現在借用頁，既有的器材與紀錄保留
                        </span>
                    </span>
                    <Switch v-model="form.is_active" />
                </label>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="closeDialog"
                    >
                        取消
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ editing ? '儲存' : '新增' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- 刪除 -->
    <Dialog
        :open="removing !== null"
        @update:open="!$event && (removing = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>刪除「{{ removing?.name }}」</DialogTitle>
                <DialogDescription>
                    這個動作無法復原，相關的互連關係也會一起移除。
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="removing = null"
                >
                    取消
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="removeForm.processing"
                    @click="confirmRemove"
                >
                    確認刪除
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
