<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { edit, index, store } from '@/routes/admin/groups';

type Group = {
    id: number;
    name: string;
    description: string | null;
    members_count: number;
    subgroups_count: number;
    grants_count: number;
    parent_groups: { id: number; name: string }[];
};

defineOptions({
    layout: { breadcrumbs: [{ title: '群組與授權', href: index() }] },
});

defineProps<{
    groups: Group[];
}>();

const open = ref(false);
const form = useForm({ name: '', description: '' });
const submit = () => form.post(store.url());
</script>

<template>
    <Head title="群組與授權" />

    <div class="space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="群組與授權"
                description="借用權限等級大於 0 的器材，只有被授權的群組成員能借。群組可以包含其他群組，子群組的成員會繼承上層群組的授權。"
            />
            <Button @click="open = true"
                ><Plus class="mr-1 size-4" />新增群組</Button
            >
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>群組</TableHead>
                        <TableHead>屬於</TableHead>
                        <TableHead class="text-right">成員</TableHead>
                        <TableHead class="text-right">子群組</TableHead>
                        <TableHead class="text-right">授權</TableHead>
                        <TableHead class="w-16"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="g in groups" :key="g.id">
                        <TableCell>
                            <div class="font-medium">{{ g.name }}</div>
                            <div
                                v-if="g.description"
                                class="text-xs text-muted-foreground"
                            >
                                {{ g.description }}
                            </div>
                        </TableCell>
                        <TableCell class="space-x-1">
                            <Badge
                                v-for="p in g.parent_groups"
                                :key="p.id"
                                variant="outline"
                                >{{ p.name }}</Badge
                            >
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            g.members_count
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            g.subgroups_count
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            g.grants_count
                        }}</TableCell>
                        <TableCell>
                            <Button size="sm" variant="ghost" as-child
                                ><Link :href="edit(g.id)">設定</Link></Button
                            >
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="!groups.length">
                        <TableCell
                            colspan="6"
                            class="py-10 text-center text-muted-foreground"
                            >還沒有任何群組</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>新增群組</DialogTitle>
                <DialogDescription>建立後再設定成員與授權</DialogDescription>
            </DialogHeader>
            <form id="group-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">群組名稱</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="例如：熱舞社幹部"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="description">說明</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        rows="2"
                    />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">取消</Button>
                <Button
                    type="submit"
                    form="group-form"
                    :disabled="form.processing"
                    >建立</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
