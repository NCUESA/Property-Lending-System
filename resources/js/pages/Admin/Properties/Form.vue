<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ImageOff, Trash2, WandSparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { destroy, index, store, update } from '@/routes/admin/properties';
import { next as nextCode } from '@/routes/admin/property-codes';
import type { Category, LocationRef, PoolRef, Property } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '器材', href: index() },
            { title: '器材資料', href: '#' },
        ],
    },
});

const props = defineProps<{
    property: Property | null;
    locations: LocationRef[];
    categories: Category[];
    pools: PoolRef[];
}>();

const isEdit = computed(() => props.property !== null);
const p = props.property;

const form = useForm({
    ssid: p?.ssid ?? '',
    property_subcategory_id: p?.property_subcategory_id
        ? String(p.property_subcategory_id)
        : '',
    sequence_no: p?.sequence_no ?? undefined,
    name: p?.name ?? '',
    second_name: p?.second_name ?? '',
    spec: p?.spec ?? '',
    price: p?.price ?? undefined,
    department: p?.department ?? '',
    custodian: p?.custodian ?? '',
    location_id: p?.location_id ? String(p.location_id) : '',
    property_pool_id: p?.property_pool_id ? String(p.property_pool_id) : '',
    acquired_on: p?.acquired_on ? formatDate(p.acquired_on) : '',
    is_school_owned: p?.is_school_owned ?? false,
    auth_level: p?.auth_level ?? 0,
    is_lendable: p?.is_lendable ?? true,
    remark: p?.remark ?? '',
    image: null as File | null,
    remove_image: false,
});

const imagePreview = ref<string | null>(p?.image_url ?? null);

function onImage(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.image = file;
    form.remove_image = false;
    imagePreview.value = file
        ? URL.createObjectURL(file)
        : (p?.image_url ?? null);
}

function removeImage() {
    form.image = null;
    form.remove_image = true;
    imagePreview.value = null;
}

// ── 依地點與類別帶入下一個財產編號 ─────────────────────
const fetchingCode = ref(false);
const canSuggest = computed(
    () => form.location_id !== '' && form.property_subcategory_id !== '',
);

async function suggestCode() {
    fetchingCode.value = true;
    try {
        const url = nextCode.url({
            query: {
                location_id: form.location_id,
                property_subcategory_id: form.property_subcategory_id,
            },
        });
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });
        const body = await response.json();

        if (!response.ok) {
            const message = Object.values(body.errors ?? {}).flat()[0] as
                | string
                | undefined;
            toast.error(message ?? '無法產生財產編號');
            return;
        }

        form.ssid = body.ssid;
        form.clearErrors('ssid');
    } finally {
        fetchingCode.value = false;
    }
}

const poolsForLocation = computed(() => props.pools);

function submit() {
    const options = { forceFormData: true, preserveScroll: true };

    if (isEdit.value) {
        // 有檔案時必須用 POST + _method=PUT
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            update.url(p!.id),
            options,
        );
    } else {
        form.post(store.url(), options);
    }
}

function remove() {
    router.delete(destroy.url(p!.id), {
        onError: (errors) => toast.error(errors.property ?? '刪除失敗'),
    });
}
</script>

<template>
    <Head :title="isEdit ? `編輯 ${p?.ssid}` : '新增器材'" />

    <div class="space-y-6 p-4 md:p-6">
        <Heading :title="isEdit ? `編輯 ${p?.ssid} ${p?.name}` : '新增器材'" />

        <form
            class="grid gap-6 lg:grid-cols-[1fr_18rem]"
            @submit.prevent="submit"
        >
            <div class="space-y-6">
                <Card>
                    <CardHeader
                        ><CardTitle class="text-base"
                            >編號與分類</CardTitle
                        ></CardHeader
                    >
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="location_id">存放地點</Label>
                            <select
                                id="location_id"
                                v-model="form.location_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                required
                            >
                                <option value="" disabled>請選擇</option>
                                <option
                                    v-for="l in locations"
                                    :key="l.id"
                                    :value="String(l.id)"
                                >
                                    {{ l.ssid_prefix }} — {{ l.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.location_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="property_subcategory_id">類別</Label>
                            <select
                                id="property_subcategory_id"
                                v-model="form.property_subcategory_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                required
                            >
                                <option value="" disabled>請選擇</option>
                                <optgroup
                                    v-for="c in categories"
                                    :key="c.id"
                                    :label="`${c.code} ${c.name}`"
                                >
                                    <option
                                        v-for="s in c.subcategories"
                                        :key="s.id"
                                        :value="String(s.id)"
                                    >
                                        {{ c.code }}{{ s.code }} {{ s.name }}
                                    </option>
                                </optgroup>
                            </select>
                            <InputError
                                :message="form.errors.property_subcategory_id"
                            />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="ssid">財產編號</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="ssid"
                                    v-model="form.ssid"
                                    class="font-mono uppercase"
                                    maxlength="8"
                                    placeholder="J2000001"
                                    required
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="!canSuggest || fetchingCode"
                                    @click="suggestCode"
                                >
                                    <Spinner v-if="fetchingCode" /><WandSparkles
                                        v-else
                                        class="mr-1 size-4"
                                    />帶入編號
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                地點 1 碼 + 主類 3 碼 + 次類 1 碼 + 流水號 3
                                碼；選好地點與類別後可自動帶入下一號
                            </p>
                            <InputError :message="form.errors.ssid" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="sequence_no">財產序</Label>
                            <Input
                                id="sequence_no"
                                v-model.number="form.sequence_no"
                                type="number"
                                min="1"
                            />
                            <InputError :message="form.errors.sequence_no" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="acquired_on">取得日期</Label>
                            <Input
                                id="acquired_on"
                                v-model="form.acquired_on"
                                type="date"
                            />
                            <InputError :message="form.errors.acquired_on" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        ><CardTitle class="text-base"
                            >財產資料</CardTitle
                        ></CardHeader
                    >
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">財產名稱</Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="second_name">第二名稱</Label>
                            <Input
                                id="second_name"
                                v-model="form.second_name"
                            />
                            <InputError :message="form.errors.second_name" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="spec">規格</Label>
                            <Input id="spec" v-model="form.spec" />
                            <InputError :message="form.errors.spec" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="price">金額</Label>
                            <Input
                                id="price"
                                v-model.number="form.price"
                                type="number"
                                min="0"
                            />
                            <InputError :message="form.errors.price" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="department">使用單位</Label>
                            <Input id="department" v-model="form.department" />
                            <InputError :message="form.errors.department" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="custodian">保管人</Label>
                            <Input id="custodian" v-model="form.custodian" />
                            <InputError :message="form.errors.custodian" />
                        </div>
                        <label
                            class="flex items-center justify-between self-end rounded-md border p-2.5 text-sm"
                        >
                            校方資產
                            <Switch v-model="form.is_school_owned" />
                        </label>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="remark">備註</Label>
                            <Textarea
                                id="remark"
                                v-model="form.remark"
                                rows="2"
                            />
                            <InputError :message="form.errors.remark" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        ><CardTitle class="text-base"
                            >借用設定</CardTitle
                        ></CardHeader
                    >
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <label
                            class="flex items-center justify-between rounded-md border p-2.5 text-sm sm:col-span-2"
                        >
                            開放借用
                            <Switch v-model="form.is_lendable" />
                        </label>
                        <div class="grid gap-2">
                            <Label for="property_pool_id">Bulk 品項</Label>
                            <select
                                id="property_pool_id"
                                v-model="form.property_pool_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">不屬於（逐件借用）</option>
                                <option
                                    v-for="pool in poolsForLocation"
                                    :key="pool.id"
                                    :value="String(pool.id)"
                                >
                                    {{ pool.name }}（{{ pool.location?.name }}）
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                屬於 bulk 品項時，借用人只選數量、不挑編號
                            </p>
                            <InputError
                                :message="form.errors.property_pool_id"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="auth_level">借用權限等級</Label>
                            <Input
                                id="auth_level"
                                v-model.number="form.auth_level"
                                type="number"
                                min="0"
                                :disabled="form.property_pool_id !== ''"
                            />
                            <p class="text-xs text-muted-foreground">
                                0 = 訪客可借；大於 0 需在「群組與授權」開放
                            </p>
                            <InputError :message="form.errors.auth_level" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-4 lg:sticky lg:top-4 lg:self-start">
                <Card>
                    <CardHeader
                        ><CardTitle class="text-base"
                            >財產照片</CardTitle
                        ></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <div
                            class="flex aspect-square items-center justify-center overflow-hidden rounded-md bg-muted"
                        >
                            <img
                                v-if="imagePreview"
                                :src="imagePreview"
                                alt="財產照片"
                                class="size-full object-cover"
                            />
                            <ImageOff
                                v-else
                                class="size-8 text-muted-foreground"
                            />
                        </div>
                        <Input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="onImage"
                        />
                        <Button
                            v-if="imagePreview"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="removeImage"
                            >移除照片</Button
                        >
                        <InputError :message="form.errors.image" />
                    </CardContent>
                </Card>

                <div class="flex flex-col gap-2">
                    <Button type="submit" :disabled="form.processing"
                        ><Spinner v-if="form.processing" />{{
                            isEdit ? '儲存變更' : '新增器材'
                        }}</Button
                    >
                    <Button variant="outline" as-child
                        ><Link :href="index()">取消</Link></Button
                    >

                    <Dialog v-if="isEdit">
                        <DialogTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                class="text-destructive"
                                ><Trash2 class="mr-1 size-4" />刪除器材</Button
                            >
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>刪除 {{ p?.ssid }}？</DialogTitle>
                                <DialogDescription
                                    >只有從未被借用過的器材可以刪除；有借用紀錄的請改為「不開放借用」。</DialogDescription
                                >
                            </DialogHeader>
                            <DialogFooter>
                                <DialogClose as-child
                                    ><Button variant="outline"
                                        >取消</Button
                                    ></DialogClose
                                >
                                <Button variant="destructive" @click="remove"
                                    >確認刪除</Button
                                >
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </form>
    </div>
</template>
