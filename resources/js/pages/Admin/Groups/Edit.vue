<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MultiSelectList from '@/components/MultiSelectList.vue';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { destroy, index, update } from '@/routes/admin/groups';
import type { Category } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: '群組與授權', href: index() },
            { title: '設定群組', href: '#' },
        ],
    },
});

type Grants = {
    property_ids: number[];
    category_ids: number[];
    subcategory_ids: number[];
    pool_ids: number[];
};

const props = defineProps<{
    group: {
        id: number;
        name: string;
        description: string | null;
        member_ids: number[];
        subgroup_ids: number[];
        grants: Grants;
    };
    options: {
        users: { id: number; name: string; student_id: string | null }[];
        groups: { id: number; name: string }[];
        categories: Category[];
        properties: {
            id: number;
            ssid: string;
            name: string;
            auth_level: number;
        }[];
        pools: {
            id: number;
            name: string;
            location: { name: string };
            auth_level: number;
        }[];
    };
}>();

const form = useForm({
    name: props.group.name,
    description: props.group.description ?? '',
    member_ids: [...props.group.member_ids],
    subgroup_ids: [...props.group.subgroup_ids],
    grants: {
        property_ids: [...props.group.grants.property_ids],
        category_ids: [...props.group.grants.category_ids],
        subcategory_ids: [...props.group.grants.subcategory_ids],
        pool_ids: [...props.group.grants.pool_ids],
    } as Grants,
});

const userOptions = computed(() =>
    props.options.users.map((u) => ({
        value: u.id,
        label: u.name,
        hint: u.student_id,
    })),
);
const groupOptions = computed(() =>
    props.options.groups.map((g) => ({ value: g.id, label: g.name })),
);
const propertyOptions = computed(() =>
    props.options.properties.map((p) => ({
        value: p.id,
        label: p.name,
        hint: `${p.ssid}・等級 ${p.auth_level}`,
    })),
);
const poolOptions = computed(() =>
    props.options.pools.map((p) => ({
        value: p.id,
        label: `${p.name}（${p.location.name}）`,
        hint: p.auth_level > 0 ? `等級 ${p.auth_level}` : '公開',
    })),
);

function toggleIn(
    list: number[],
    id: number,
    checked: boolean | 'indeterminate',
): number[] {
    return checked === true
        ? [...new Set([...list, id])]
        : list.filter((v) => v !== id);
}

const grantCount = computed(() =>
    Object.values(form.grants).reduce((n, ids) => n + ids.length, 0),
);

const submit = () =>
    form.put(update.url(props.group.id), { preserveScroll: true });

function remove() {
    if (
        window.confirm(
            `確定刪除群組「${props.group.name}」？成員會失去這個群組給的授權。`,
        )
    )
        router.delete(destroy.url(props.group.id));
}
</script>

<template>
    <Head :title="`群組：${group.name}`" />

    <form class="space-y-6 p-4 md:p-6" @submit.prevent="submit">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="group.name"
                description="成員、子群組與授權會一起儲存"
            />
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    class="text-destructive"
                    @click="remove"
                    ><Trash2 class="mr-1 size-4" />刪除群組</Button
                >
                <Button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    >儲存</Button
                >
            </div>
        </div>

        <Card>
            <CardContent class="grid gap-4 pt-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">群組名稱</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="description">說明</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        rows="1"
                    />
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">成員</CardTitle>
                    <CardDescription>直接加入這個群組的人</CardDescription>
                </CardHeader>
                <CardContent>
                    <MultiSelectList
                        v-model="form.member_ids"
                        :options="userOptions"
                        placeholder="搜尋姓名或學號"
                    />
                    <InputError :message="form.errors.member_ids" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">子群組</CardTitle>
                    <CardDescription
                        >子群組的成員也會得到這個群組的授權</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <MultiSelectList
                        v-model="form.subgroup_ids"
                        :options="groupOptions"
                        placeholder="搜尋群組"
                        empty-text="沒有其他群組"
                    />
                    <InputError
                        :message="
                            form.errors.subgroup_ids ||
                            form.errors['subgroup_ids.0']
                        "
                    />
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base"
                    >可借用（{{ grantCount }} 項授權）</CardTitle
                >
                <CardDescription
                    >授權主要類別時，該類別底下所有需授權的器材都可借</CardDescription
                >
            </CardHeader>
            <CardContent>
                <Tabs default-value="categories">
                    <TabsList>
                        <TabsTrigger value="categories">類別</TabsTrigger>
                        <TabsTrigger value="properties">單一器材</TabsTrigger>
                        <TabsTrigger value="pools">大量借用品項</TabsTrigger>
                    </TabsList>

                    <TabsContent value="categories" class="space-y-3 pt-3">
                        <div
                            v-for="c in options.categories"
                            :key="c.id"
                            class="rounded-md border p-3"
                        >
                            <label class="flex items-center gap-3 font-medium">
                                <Checkbox
                                    :model-value="
                                        form.grants.category_ids.includes(c.id)
                                    "
                                    @update:model-value="
                                        form.grants.category_ids = toggleIn(
                                            form.grants.category_ids,
                                            c.id,
                                            $event,
                                        )
                                    "
                                />
                                <span class="font-mono">{{ c.code }}</span>
                                {{ c.name }}（整個主要類別）
                            </label>
                            <div
                                v-if="c.subcategories.length"
                                class="mt-2 grid gap-2 pl-7 sm:grid-cols-2"
                            >
                                <label
                                    v-for="s in c.subcategories"
                                    :key="s.id"
                                    class="flex items-center gap-2 text-sm"
                                    :class="
                                        form.grants.category_ids.includes(c.id)
                                            ? 'opacity-50'
                                            : ''
                                    "
                                >
                                    <Checkbox
                                        :disabled="
                                            form.grants.category_ids.includes(
                                                c.id,
                                            )
                                        "
                                        :model-value="
                                            form.grants.category_ids.includes(
                                                c.id,
                                            ) ||
                                            form.grants.subcategory_ids.includes(
                                                s.id,
                                            )
                                        "
                                        @update:model-value="
                                            form.grants.subcategory_ids =
                                                toggleIn(
                                                    form.grants.subcategory_ids,
                                                    s.id,
                                                    $event,
                                                )
                                        "
                                    />
                                    <span class="font-mono text-xs"
                                        >{{ c.code }}{{ s.code }}</span
                                    >
                                    {{ s.name }}
                                </label>
                            </div>
                        </div>
                        <p
                            v-if="!options.categories.length"
                            class="text-sm text-muted-foreground"
                        >
                            還沒有任何類別
                        </p>
                    </TabsContent>

                    <TabsContent value="properties" class="pt-3">
                        <MultiSelectList
                            v-model="form.grants.property_ids"
                            :options="propertyOptions"
                            placeholder="搜尋器材"
                            empty-text="沒有需要授權的器材（借用權限等級皆為 0）"
                        />
                    </TabsContent>

                    <TabsContent value="pools" class="pt-3">
                        <MultiSelectList
                            v-model="form.grants.pool_ids"
                            :options="poolOptions"
                            placeholder="搜尋品項"
                            empty-text="沒有大量借用品項"
                        />
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>
    </form>
</template>
