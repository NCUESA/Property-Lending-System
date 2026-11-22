<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/admin/settings/section';

defineOptions({
    layout: { breadcrumbs: [{ title: '違規與封鎖', href: edit('penalty') }] },
});

type Kind = { value: string; label: string; description: string };

const props = defineProps<{
    settings: Record<string, number | null>;
    kinds: Kind[];
}>();

/*
 * 欄位跟著後端的 BorrowViolationKind 走，所以表單資料是動態組出來的：
 * 新增一種違規時這一頁不用改。
 */
const form = useForm<Record<string, number>>(
    Object.fromEntries(
        props.kinds.flatMap((kind) => [
            [`${kind.value}_limit`, props.settings[`${kind.value}_limit`] ?? 0],
            [
                `${kind.value}_block_days`,
                props.settings[`${kind.value}_block_days`] ?? 1,
            ],
        ]),
    ),
);

const submit = () => form.put(update.url('penalty'), { preserveScroll: true });

const limitOf = (kind: Kind) => form[`${kind.value}_limit`] as number;
</script>

<template>
    <Head title="違規與封鎖" />

    <form class="max-w-4xl space-y-6 p-4 md:p-6" @submit.prevent="submit">
        <Heading
            title="違規與封鎖"
            description="每一種違規各自設定要累積幾次才封鎖、封鎖多久"
        />

        <div class="space-y-4">
            <div
                v-for="kind in kinds"
                :key="kind.value"
                class="rounded-lg border p-4"
            >
                <div class="mb-3">
                    <h3 class="font-medium">{{ kind.label }}</h3>
                    <p class="text-xs text-muted-foreground">
                        {{ kind.description }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="`${kind.value}_limit`">
                            次數上限（每天）
                        </Label>
                        <Input
                            :id="`${kind.value}_limit`"
                            v-model.number="form[`${kind.value}_limit`]"
                            type="number"
                            min="0"
                            max="50"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                limitOf(kind) === 0
                                    ? '0 = 只記錄違規，不封鎖'
                                    : `同一天累積 ${limitOf(kind)} 次就自動封鎖`
                            }}
                        </p>
                        <InputError
                            :message="form.errors[`${kind.value}_limit`]"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`${kind.value}_block_days`">
                            預設封鎖天數
                        </Label>
                        <Input
                            :id="`${kind.value}_block_days`"
                            v-model.number="form[`${kind.value}_block_days`]"
                            type="number"
                            min="1"
                            max="365"
                            :disabled="limitOf(kind) === 0"
                        />
                        <p class="text-xs text-muted-foreground">
                            1 = 封到當天結束，2 = 封到隔天結束，依此類推
                        </p>
                        <InputError
                            :message="form.errors[`${kind.value}_block_days`]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <p class="text-sm text-muted-foreground">
            這裡設定的是<strong>預設值</strong>；個別封鎖的期限可以到「違規紀錄」頁面單獨調整或提前解除。
            值班人員代為退回的借用單不算違規。
        </p>

        <Button type="submit" :disabled="form.processing || !form.isDirty">
            儲存設定
        </Button>
    </form>
</template>
