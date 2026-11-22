<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    Info,
    LogIn,
    Minus,
    Plus,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DateRangeCalendar from '@/components/DateRangeCalendar.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PropertyPicker, {
    type PickableProperty,
} from '@/components/PropertyPicker.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { todayInTaipei } from '@/lib/format';
import { login } from '@/routes';
import { create, store } from '@/routes/borrow';

type Pool = {
    id: number;
    name: string;
    available: number;
    image_url: string | null;
    restricted: boolean;
};

const props = defineProps<{
    isOpen: boolean;
    rules: {
        maxDays: number;
        depositAmount: number;
        allowAdvance: boolean;
        flexibleDuration: boolean;
        notices: string[];
    };
    calendar: {
        today: string;
        lastBorrowDate: string;
        lastDate: string;
        closedDates: string[];
    };
    locations: { id: number; code: string; name: string }[];
    selectedLocationId: number | null;
    properties: PickableProperty[];
    pools: Pool[];
    prefill: { borrower_name: string; email: string | null } | null;
}>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
// 後端算好的今天優先；SSR 與瀏覽器同一個值，不會因為使用者的時區而對不上
const today = props.calendar.today || todayInTaipei();

const form = useForm({
    location_id: props.selectedLocationId,
    borrower_department: '',
    borrower_name: props.prefill?.borrower_name ?? '',
    phone: '',
    email: props.prefill?.email ?? '',
    borrow_date: today,
    due_date: today,
    agreed_terms: false,
    property_ids: [] as number[],
    pools: [] as { id: number; quantity: number }[],
});

/*
 * 日曆用「取件日已選、歸還日還沒選」來決定下一次點擊的意義，
 * 所以挑日子的過程中 due_date 會是 null；送出前再補回和取件日同一天。
 */
const pickedDue = ref<string | null>(form.due_date);

watch(pickedDue, (value) => {
    form.due_date = value ?? form.borrow_date;
});

/*
 * 送出與否看「選到的取件日」而不是今天：開放預借時，今天暫停借用
 * 不代表下週也不能借，否則使用者會選得到日期卻按不下送出。
 * 日曆本來就不讓人選到暫停借用的日子，這裡只擋預設值還停在今天的情況。
 */
const selectedDayClosed = computed(() =>
    props.calendar.closedDates.includes(form.borrow_date),
);

/** 含取件當天的天數，給摘要列顯示。 */
const borrowDays = computed(() => {
    const from = new Date(`${form.borrow_date}T00:00:00Z`).getTime();
    const to = new Date(`${form.due_date}T00:00:00Z`).getTime();

    return Math.round((to - from) / 86_400_000) + 1;
});

function pickBorrowDate(date: string | null) {
    if (date === null) return;

    form.borrow_date = date;

    // 彈性借用開啟時歸還日由規定決定，日曆會同時送 update:end，這裡不留空等人點
    if (props.rules.flexibleDuration) return;

    pickedDue.value = null;
    form.due_date = date;
}

/** 彈性借用關閉時才讓使用者自選歸還日；開啟時日曆一點就選滿規定天數。 */
const fixedDays = computed(() =>
    props.rules.flexibleDuration ? props.rules.maxDays : null,
);

/** 超過規定天數：不擋，但告訴借用人借用單上會被標示。 */
const overRuleDays = computed(
    () =>
        !props.rules.flexibleDuration && borrowDays.value > props.rules.maxDays,
);

/** 換地點：重新載入該地點的器材，並清空已選（避免跨地點借用）。 */
function selectLocation(location: { id: number; code: string }) {
    if (form.location_id === location.id) return;

    form.location_id = location.id;
    form.property_ids = [];
    form.pools = [];
    router.get(
        create.url({ query: { location: location.code } }),
        {},
        {
            only: ['properties', 'pools', 'selectedLocationId'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function poolQuantity(id: number): number {
    return form.pools.find((p) => p.id === id)?.quantity ?? 0;
}

function setPoolQuantity(pool: Pool, quantity: number) {
    const q = Math.max(0, Math.min(pool.available, Math.floor(quantity) || 0));
    const others = form.pools.filter((p) => p.id !== pool.id);
    form.pools = q > 0 ? [...others, { id: pool.id, quantity: q }] : others;
}

const totalSelected = computed(
    () =>
        form.property_ids.length +
        form.pools.reduce((n, p) => n + p.quantity, 0),
);

function submit() {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () =>
            form.reset(
                'borrower_department',
                'phone',
                'agreed_terms',
                'property_ids',
                'pools',
            ),
    });
}
</script>

<template>
    <Head title="借用申請" />

    <Heading title="器材借用申請" description="國立彰化師範大學學生會" />

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <form class="space-y-6" @submit.prevent="submit">
            <Alert v-if="!isOpen" variant="destructive">
                <CalendarClock class="size-4" />
                <AlertTitle>今天暫停借用</AlertTitle>
                <AlertDescription>
                    寒暑假或特殊期間暫停借用，請留意學生會公告。{{
                        rules.allowAdvance
                            ? '你仍可在日曆上改選其他開放的日期。'
                            : ''
                    }}
                </AlertDescription>
            </Alert>

            <Alert v-if="isGuest">
                <LogIn class="size-4" />
                <AlertTitle>部分器材需要登入才能借用</AlertTitle>
                <AlertDescription>
                    <span>
                        社團或單位專用的器材需先
                        <a
                            :href="login().url"
                            class="font-medium underline underline-offset-4"
                            >登入</a
                        >
                        才會顯示。
                    </span>
                </AlertDescription>
            </Alert>

            <!-- 1. 地點 -->
            <Card>
                <CardHeader>
                    <CardTitle>1. 借用地點</CardTitle>
                    <CardDescription>不同地點的器材需分開申請</CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        class="flex flex-wrap gap-2"
                        role="radiogroup"
                        aria-label="借用地點"
                    >
                        <Button
                            v-for="loc in locations"
                            :key="loc.id"
                            type="button"
                            role="radio"
                            :aria-checked="form.location_id === loc.id"
                            :variant="
                                form.location_id === loc.id
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="selectLocation(loc)"
                        >
                            {{ loc.name }}
                        </Button>
                    </div>
                    <InputError
                        class="mt-2"
                        :message="form.errors.location_id"
                    />
                </CardContent>
            </Card>

            <!-- 2. 器材 -->
            <Card v-if="form.location_id">
                <CardHeader>
                    <CardTitle>2. 選擇器材</CardTitle>
                    <CardDescription
                        >可複選；搜尋或切換分類時已選的器材會保留</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-6">
                    <div v-if="pools.length" class="space-y-3">
                        <h3 class="text-sm font-medium">依數量借用</h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="pool in pools"
                                :key="pool.id"
                                class="flex items-center justify-between gap-3 rounded-lg border p-3"
                            >
                                <div>
                                    <div class="font-medium">
                                        {{ pool.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        剩餘 {{ pool.available }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        class="size-8"
                                        :disabled="poolQuantity(pool.id) <= 0"
                                        aria-label="減少"
                                        @click="
                                            setPoolQuantity(
                                                pool,
                                                poolQuantity(pool.id) - 1,
                                            )
                                        "
                                    >
                                        <Minus class="size-4" />
                                    </Button>
                                    <Input
                                        type="number"
                                        class="w-16 text-center"
                                        min="0"
                                        :max="pool.available"
                                        :model-value="poolQuantity(pool.id)"
                                        :aria-label="`${pool.name} 數量`"
                                        @update:model-value="
                                            setPoolQuantity(
                                                pool,
                                                Number($event),
                                            )
                                        "
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        class="size-8"
                                        :disabled="
                                            poolQuantity(pool.id) >=
                                            pool.available
                                        "
                                        aria-label="增加"
                                        @click="
                                            setPoolQuantity(
                                                pool,
                                                poolQuantity(pool.id) + 1,
                                            )
                                        "
                                    >
                                        <Plus class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.pools" />
                    </div>

                    <div class="space-y-3">
                        <h3 v-if="pools.length" class="text-sm font-medium">
                            指定器材
                        </h3>
                        <PropertyPicker
                            v-model="form.property_ids"
                            :properties="properties"
                        />
                        <InputError :message="form.errors.property_ids" />
                    </div>
                </CardContent>
            </Card>

            <!-- 3. 借用人 -->
            <Card v-if="form.location_id">
                <CardHeader>
                    <CardTitle>3. 借用資料</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="borrower_department">單位／系所</Label>
                        <Input
                            id="borrower_department"
                            v-model="form.borrower_department"
                            placeholder="社團借用請填社團名稱"
                            required
                        />
                        <InputError
                            :message="form.errors.borrower_department"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="borrower_name">聯絡人姓名</Label>
                        <Input
                            id="borrower_name"
                            v-model="form.borrower_name"
                            autocomplete="name"
                            required
                        />
                        <InputError :message="form.errors.borrower_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">聯絡電話</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            inputmode="numeric"
                            maxlength="10"
                            placeholder="0912345678"
                            autocomplete="tel"
                            required
                        />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="email">電子郵件</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                </CardContent>
            </Card>

            <!-- 4. 日期 -->
            <Card v-if="form.location_id">
                <CardHeader>
                    <CardTitle>4. 借用日期</CardTitle>
                    <CardDescription>
                        {{
                            rules.flexibleDuration
                                ? `依規定一律借用 ${rules.maxDays} 天，點選取件日即可。`
                                : '先點取件日，再點歸還日；點已選範圍外的日子可重新選擇。'
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-3 text-sm">
                        <div class="rounded-md border px-3 py-2">
                            <div class="text-xs text-muted-foreground">
                                取件日
                            </div>
                            <div class="font-medium">
                                {{ form.borrow_date }}
                            </div>
                        </div>
                        <div class="rounded-md border px-3 py-2">
                            <div class="text-xs text-muted-foreground">
                                歸還日
                            </div>
                            <div
                                class="font-medium"
                                :class="
                                    pickedDue ? '' : 'text-muted-foreground'
                                "
                            >
                                {{ pickedDue ?? '請選擇' }}
                            </div>
                        </div>
                        <div
                            v-if="pickedDue"
                            class="rounded-md border px-3 py-2"
                        >
                            <div class="text-xs text-muted-foreground">
                                共計
                            </div>
                            <div class="font-medium">{{ borrowDays }} 天</div>
                        </div>
                    </div>

                    <p
                        v-if="!rules.allowAdvance"
                        class="text-xs text-muted-foreground"
                    >
                        目前不開放預借，取件日為今天
                    </p>

                    <Alert v-if="overRuleDays">
                        <TriangleAlert class="size-4" />
                        <AlertTitle>超過規定借用天數</AlertTitle>
                        <AlertDescription>
                            規定最長 {{ rules.maxDays }} 天，你選了
                            {{ borrowDays }}
                            天。還是可以送出，但借用單上會標示給值班人員確認。
                        </AlertDescription>
                    </Alert>

                    <DateRangeCalendar
                        :start="form.borrow_date"
                        :end="pickedDue"
                        :min-date="calendar.today"
                        :last-borrow-date="calendar.lastBorrowDate"
                        :last-date="calendar.lastDate"
                        :closed-dates="calendar.closedDates"
                        :max-days="fixedDays === null ? null : rules.maxDays"
                        :fixed-days="fixedDays"
                        @update:start="pickBorrowDate"
                        @update:end="pickedDue = $event"
                    />

                    <InputError :message="form.errors.borrow_date" />
                    <InputError :message="form.errors.due_date" />
                </CardContent>
            </Card>

            <Card v-if="form.location_id">
                <CardContent class="space-y-4 pt-6">
                    <label class="flex items-start gap-3 text-sm">
                        <Checkbox
                            class="mt-0.5"
                            :model-value="form.agreed_terms"
                            @update:model-value="
                                form.agreed_terms = $event === true
                            "
                        />
                        <span
                            >我已閱讀並同意借用注意事項，借用時會押證件乙張與押金
                            NT$ {{ rules.depositAmount }}。</span
                        >
                    </label>
                    <InputError :message="form.errors.agreed_terms" />

                    <Button
                        type="submit"
                        size="lg"
                        class="w-full"
                        :disabled="
                            form.processing ||
                            selectedDayClosed ||
                            totalSelected === 0
                        "
                    >
                        <Spinner v-if="form.processing" />
                        送出申請{{
                            totalSelected ? `（${totalSelected} 件）` : ''
                        }}
                    </Button>
                </CardContent>
            </Card>
        </form>

        <aside class="lg:sticky lg:top-20 lg:self-start">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Info class="size-4" />注意事項</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <ol
                        class="list-decimal space-y-2 pl-5 text-sm leading-relaxed"
                    >
                        <li v-for="(notice, i) in rules.notices" :key="i">
                            {{ notice }}
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </aside>
    </div>
</template>
