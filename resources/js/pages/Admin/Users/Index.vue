<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { index, store, update } from '@/routes/admin/users';
import type { Option, Paginator, Role } from '@/types';

type Row = {
    id: number;
    student_id: string | null;
    name: string;
    email: string | null;
    role: Role;
    is_active: boolean;
    groups: { id: number; name: string }[];
};

defineOptions({
    layout: { breadcrumbs: [{ title: '人員', href: index() }] },
});

const props = defineProps<{
    filters: { q?: string; role?: Role };
    users: Paginator<Row>;
    roles: Option<Role>[];
}>();

const me = computed(() => usePage().props.auth.user.id);
const roleLabel = (role: Role) =>
    props.roles.find((r) => r.value === role)?.label ?? role;

const { filters } = useQueryFilters(index.url(), {
    q: props.filters.q ?? '',
    role: props.filters.role ?? '',
});

const open = ref(false);
const editing = ref<Row | null>(null);
const form = useForm({
    student_id: '',
    name: '',
    email: '',
    role: 'borrower' as Role,
    is_active: true,
});

function openDialog(user: Row | null) {
    editing.value = user;
    form.clearErrors();
    form.defaults({
        student_id: user?.student_id ?? '',
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? 'staff',
        is_active: user?.is_active ?? true,
    });
    form.reset();
    open.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };
    if (editing.value) form.put(update.url(editing.value.id), options);
    else form.post(store.url(), options);
}
</script>

<template>
    <Head title="人員" />

    <div class="space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="人員"
                description="SSO 第一次登入會自動建立借用人帳號；在這裡升級為值班人員或管理員，或停用帳號"
            />
            <Button @click="openDialog(null)"
                ><Plus class="mr-1 size-4" />預先建立帳號</Button
            >
        </div>

        <div class="flex flex-col gap-3 md:flex-row">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.q"
                    placeholder="姓名、學號、email"
                    class="pl-9"
                />
            </div>
            <div class="flex gap-2">
                <Button
                    size="sm"
                    :variant="filters.role === '' ? 'default' : 'outline'"
                    @click="filters.role = ''"
                    >全部</Button
                >
                <Button
                    v-for="r in roles"
                    :key="r.value"
                    size="sm"
                    :variant="filters.role === r.value ? 'default' : 'outline'"
                    @click="filters.role = r.value"
                >
                    {{ r.label }}
                </Button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>姓名</TableHead>
                        <TableHead>學號</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>角色</TableHead>
                        <TableHead>群組</TableHead>
                        <TableHead class="w-16"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="u in users.data"
                        :key="u.id"
                        :class="!u.is_active ? 'opacity-50' : ''"
                    >
                        <TableCell class="font-medium">
                            {{ u.name }}
                            <Badge
                                v-if="!u.is_active"
                                variant="outline"
                                class="ml-1"
                                >已停用</Badge
                            >
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{
                            u.student_id ?? '—'
                        }}</TableCell>
                        <TableCell class="text-sm">{{
                            u.email ?? '—'
                        }}</TableCell>
                        <TableCell>
                            <Badge
                                :variant="
                                    u.role === 'admin'
                                        ? 'default'
                                        : u.role === 'staff'
                                          ? 'secondary'
                                          : 'outline'
                                "
                                >{{ roleLabel(u.role) }}</Badge
                            >
                        </TableCell>
                        <TableCell class="space-x-1">
                            <Badge
                                v-for="g in u.groups"
                                :key="g.id"
                                variant="outline"
                                >{{ g.name }}</Badge
                            >
                        </TableCell>
                        <TableCell>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="編輯"
                                @click="openDialog(u)"
                                ><Pencil class="size-4"
                            /></Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="!users.data.length">
                        <TableCell
                            colspan="6"
                            class="py-10 text-center text-muted-foreground"
                            >沒有符合條件的帳號</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <PaginationLinks :paginator="users" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? `編輯 ${editing.name}` : '預先建立帳號'
                }}</DialogTitle>
                <DialogDescription v-if="!editing"
                    >對方第一次用 SSO 登入時，會以 email
                    或學號對應到這個帳號並保留角色。</DialogDescription
                >
            </DialogHeader>
            <form id="user-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">姓名</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="student_id">學號</Label>
                    <Input
                        id="student_id"
                        v-model="form.student_id"
                        class="font-mono"
                    />
                    <InputError :message="form.errors.student_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="role">角色</Label>
                    <select
                        id="role"
                        v-model="form.role"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        :disabled="editing?.id === me"
                    >
                        <option
                            v-for="r in roles"
                            :key="r.value"
                            :value="r.value"
                        >
                            {{ r.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>
                <label
                    class="flex items-center justify-between rounded-md border p-3 text-sm"
                >
                    啟用
                    <Switch
                        v-model="form.is_active"
                        :disabled="editing?.id === me"
                    />
                </label>
                <InputError :message="form.errors.is_active" />
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">取消</Button>
                <Button
                    type="submit"
                    form="user-form"
                    :disabled="form.processing"
                    >儲存</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
