<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { edit, update } from '@/routes/admin/settings/section';

defineOptions({
    layout: { breadcrumbs: [{ title: '注意事項', href: edit('notices') }] },
});

const props = defineProps<{
    settings: { notices: string[] | null };
}>();

const form = useForm({
    notices: [...(props.settings.notices ?? [])],
});

function move(i: number, delta: number) {
    const j = i + delta;
    if (j < 0 || j >= form.notices.length) return;
    [form.notices[i], form.notices[j]] = [form.notices[j], form.notices[i]];
}

const submit = () => form.put(update.url('notices'), { preserveScroll: true });
</script>

<template>
    <Head title="注意事項" />

    <form class="max-w-3xl space-y-6 p-4 md:p-6" @submit.prevent="submit">
        <Heading
            title="注意事項"
            description="顯示在借用申請頁，借用人送出前需勾選同意"
        />

        <div class="space-y-3">
            <div v-for="(_, i) in form.notices" :key="i" class="flex gap-2">
                <span
                    class="w-6 pt-2 text-right text-sm text-muted-foreground tabular-nums"
                    >{{ i + 1 }}.</span
                >
                <div class="flex-1">
                    <Textarea v-model="form.notices[i]" rows="2" />
                    <InputError
                        :message="
                            form.errors[
                                `notices.${i}` as keyof typeof form.errors
                            ]
                        "
                    />
                </div>
                <div class="flex flex-col">
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        :disabled="i === 0"
                        aria-label="上移"
                        @click="move(i, -1)"
                        ><ArrowUp class="size-4"
                    /></Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        :disabled="i === form.notices.length - 1"
                        aria-label="下移"
                        @click="move(i, 1)"
                        ><ArrowDown class="size-4"
                    /></Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="size-7"
                        aria-label="刪除"
                        @click="form.notices.splice(i, 1)"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="form.notices.push('')"
                ><Plus class="mr-1 size-4" />新增一條</Button
            >
        </div>

        <Button type="submit" :disabled="form.processing || !form.isDirty"
            >儲存設定</Button
        >
    </form>
</template>
