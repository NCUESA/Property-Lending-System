<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    title: string;
    description?: string;
    url: string;
    method: 'post' | 'put';
    code?: string;
    name?: string;
    codeLabel: string;
    codeMaxLength: number;
    codePlaceholder?: string;
}>();

const open = ref(false);
const form = useForm({ code: props.code ?? '', name: props.name ?? '' });

function submit() {
    form.submit(props.method, props.url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            if (props.method === 'post') form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <form
                id="code-name-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="code">{{ codeLabel }}</Label>
                    <Input
                        id="code"
                        v-model="form.code"
                        class="font-mono uppercase"
                        :maxlength="codeMaxLength"
                        :placeholder="codePlaceholder"
                        required
                        @update:model-value="
                            form.code = String($event).toUpperCase()
                        "
                    />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="name">名稱</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">取消</Button>
                <Button
                    type="submit"
                    form="code-name-form"
                    :disabled="form.processing"
                    >儲存</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
