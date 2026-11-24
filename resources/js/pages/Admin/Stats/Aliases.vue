<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { destroy, index, rename, store } from '@/routes/admin/stats/aliases';

type Row = {
    spelling: string;
    canonical: string | null;
    times: number;
    last_borrow_date: string;
};

type Member = Row & { implicit: boolean };
type Group = {
    key: string;
    name: string;
    members: Member[];
    total: number;
    pending: boolean;
};

const props = defineProps<{ rows: Row[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: '單位同義詞設定', href: index() }] },
});

/**
 * 群組＝統計時的單位名稱。成員是對應到它的寫法；和群組同名的那種寫法本來就算在裡面（implicit）。
 * 新增的空群組只存在畫面上，移進第一個寫法後才會寫入資料庫。
 */
const pending = ref<{ key: string; name: string }[]>([]);

const labels = computed(
    () =>
        new Set(
            props.rows.map((r) => r.canonical).filter((c): c is string => !!c),
        ),
);

const groupNames = computed(
    () => new Set([...labels.value, ...pending.value.map((p) => p.name)]),
);

function membersOf(name: string): Member[] {
    return props.rows
        .filter(
            (r) =>
                r.canonical === name || (!r.canonical && r.spelling === name),
        )
        .map((r) => ({ ...r, implicit: !r.canonical }))
        .sort(
            (a, b) =>
                Number(b.implicit) - Number(a.implicit) || b.times - a.times,
        );
}

const groups = computed<Group[]>(() => {
    const saved = [...labels.value].map((name) => {
        const members = membersOf(name);

        return {
            key: `g:${name}`,
            name,
            members,
            total: members.reduce((s, m) => s + m.times, 0),
            pending: false,
        };
    });

    const drafts = pending.value
        .filter((p) => !labels.value.has(p.name))
        .map((p) => {
            const members = membersOf(p.name);

            return {
                key: p.key,
                name: p.name,
                members,
                total: members.reduce((s, m) => s + m.times, 0),
                pending: true,
            };
        });

    return [...drafts, ...saved.sort((a, b) => b.total - a.total)];
});

const ungrouped = computed(() =>
    props.rows
        .filter((r) => !r.canonical && !groupNames.value.has(r.spelling))
        .sort((a, b) => b.times - a.times),
);

// ── 搜尋（只在畫面上篩選） ─────────────────────────
const q = ref('');
const matches = (text: string) =>
    !q.value.trim() ||
    text.toLowerCase().includes(q.value.trim().toLowerCase());

const visibleUngrouped = computed(() =>
    ungrouped.value.filter((r) => matches(r.spelling)),
);
const visibleGroups = computed(() =>
    groups.value.filter(
        (g) =>
            g.pending ||
            matches(g.name) ||
            g.members.some((m) => matches(m.spelling)),
    ),
);

// ── 寫入 ───────────────────────────────────────────
const error = ref('');
const busy = ref(false);

const visitOptions = {
    preserveScroll: true,
    preserveState: true,
    onStart: () => {
        busy.value = true;
        error.value = '';
    },
    onFinish: () => (busy.value = false),
    onError: (errors: Record<string, string>) => {
        error.value = Object.values(errors)[0] ?? '儲存失敗';
    },
};

const rowOf = (spelling: string) =>
    props.rows.find((r) => r.spelling === spelling);

function moveTo(spelling: string, group: string) {
    const row = rowOf(spelling);

    if (
        !row ||
        row.canonical === group ||
        (!row.canonical && spelling === group)
    ) {
        return;
    }

    router.post(
        store.url(),
        { aliases: [spelling], canonical: group },
        {
            ...visitOptions,
            onSuccess: () => {
                pending.value = pending.value.filter((p) => p.name !== group);
            },
        },
    );
}

function ungroup(spelling: string) {
    const row = rowOf(spelling);

    if (!row) {
        return;
    }

    if (!row.canonical) {
        if (labels.value.has(spelling)) {
            error.value = `「${spelling}」就是群組名稱本身，要拆開請改群組名稱，或把其他寫法移出`;
        }

        return;
    }

    router.delete(destroy.url(), {
        data: { aliases: [spelling] },
        ...visitOptions,
    });
}

let seq = 0;

async function addGroup(name = '') {
    const key = `p:${++seq}`;
    pending.value = [{ key, name: name || `新群組 ${seq}` }, ...pending.value];
    await nextTick();
    startEdit(key, name || `新群組 ${seq}`);
}

/** 把寫法拖到「新增群組」：以這個寫法的名稱開一個新群組 */
function newGroupFrom(spelling: string) {
    const row = rowOf(spelling);

    if (!row) {
        return;
    }

    if (row.canonical) {
        router.delete(destroy.url(), {
            data: { aliases: [spelling] },
            ...visitOptions,
        });
    }

    if (!groupNames.value.has(spelling)) {
        pending.value = [
            { key: `p:${++seq}`, name: spelling },
            ...pending.value,
        ];
    }
}

// ── 改名 ───────────────────────────────────────────
const editing = ref<string | null>(null);
const draftName = ref('');

function startEdit(key: string, name: string) {
    editing.value = key;
    draftName.value = name;
    nextTick(() => {
        const input = document.getElementById(
            `group-name-${key}`,
        ) as HTMLInputElement | null;
        input?.focus();
        input?.select();
    });
}

function commitEdit(group: Group) {
    if (editing.value !== group.key) {
        return;
    }

    editing.value = null;
    const to = draftName.value.trim();

    if (!to || to === group.name) {
        return;
    }

    const hasSaved = group.members.some((m) => !m.implicit);

    if (group.pending && !hasSaved) {
        // 還沒寫入資料庫的群組：同名寫法（若有）要真的移進新名稱，其餘只改畫面
        const implicit = group.members.find((m) => m.implicit);
        pending.value = pending.value.map((p) =>
            p.key === group.key ? { ...p, name: to } : p,
        );

        if (implicit) {
            moveTo(implicit.spelling, to);
        }

        return;
    }

    router.patch(rename.url(), { from: group.name, to }, visitOptions);
}

/** 解散群組：所有寫法回到未分組（同名的那種寫法本來就是原文，不用動） */
function disband(group: Group) {
    const aliases = group.members
        .filter((m) => !m.implicit)
        .map((m) => m.spelling);
    pending.value = pending.value.filter((p) => p.key !== group.key);

    if (!aliases.length) {
        return;
    }

    if (
        !window.confirm(
            `解散「${group.name}」？${aliases.length} 種寫法會回到未分組。`,
        )
    ) {
        return;
    }

    router.delete(destroy.url(), { data: { aliases }, ...visitOptions });
}

// ── 拖放（滑鼠）與點選（觸控） ──────────────────────
const dragging = ref<string | null>(null);
const over = ref<string | null>(null);
const selected = ref<string | null>(null);

function onDragStart(event: DragEvent, spelling: string) {
    dragging.value = spelling;
    selected.value = null;
    event.dataTransfer?.setData('text/plain', spelling);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragEnd() {
    dragging.value = null;
    over.value = null;
}

function onDrop(event: DragEvent, action: (spelling: string) => void) {
    const spelling =
        event.dataTransfer?.getData('text/plain') || dragging.value;
    over.value = null;
    dragging.value = null;

    if (spelling) {
        action(spelling);
    }
}

/** 觸控裝置不能拖：先點寫法，再點要放的群組 */
function onTargetClick(action: (spelling: string) => void) {
    if (selected.value) {
        action(selected.value);
        selected.value = null;
    }
}

const zoneClass = (key: string) =>
    cn(
        'rounded-xl border-2 border-dashed transition-colors',
        over.value === key
            ? 'border-primary bg-primary/5'
            : dragging.value || selected.value
              ? 'border-muted-foreground/40'
              : 'border-transparent',
    );
</script>

<template>
    <Head title="單位同義詞設定" />

    <div class="space-y-4 p-4 md:p-6">
        <Heading
            title="單位同義詞設定"
            description="把不同種寫法的單位合併成同一個項目。拖進群組泡泡，統計時就會算在一起；點群組名稱可以改名。借用單上的原文不會被修改。"
        />

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input v-model="q" placeholder="搜尋寫法或群組" class="pl-9" />
            </div>
            <Button variant="outline" @click="addGroup()"
                ><Plus />新增群組</Button
            >
            <span class="text-xs text-muted-foreground md:ml-auto">
                觸控裝置：先點寫法，再點要放進去的群組
            </span>
        </div>

        <div
            v-if="error"
            class="flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
        >
            <span class="flex-1">{{ error }}</span>
            <button type="button" aria-label="關閉" @click="error = ''">
                <X class="size-4" />
            </button>
        </div>

        <div
            class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
            :class="busy ? 'pointer-events-none opacity-70' : ''"
        >
            <!-- 未分組 -->
            <section
                :class="
                    cn(
                        zoneClass('ungrouped'),
                        'bg-muted/40 p-3 md:sticky md:top-4 md:max-h-[calc(100vh-6rem)] md:self-start md:overflow-y-auto',
                    )
                "
                @dragover.prevent="over = 'ungrouped'"
                @dragleave="over = over === 'ungrouped' ? null : over"
                @drop.prevent="onDrop($event, ungroup)"
                @click.self="onTargetClick(ungroup)"
            >
                <h2 class="mb-2 text-sm font-semibold">
                    未分組
                    <span class="font-normal text-muted-foreground"
                        >{{ ungrouped.length }} 種寫法</span
                    >
                </h2>
                <div
                    class="flex flex-wrap gap-2"
                    @click.self="onTargetClick(ungroup)"
                >
                    <button
                        v-for="r in visibleUngrouped"
                        :key="r.spelling"
                        type="button"
                        draggable="true"
                        :class="
                            cn(
                                'inline-flex cursor-grab items-center gap-1.5 rounded-full border bg-background px-3 py-1 text-sm shadow-xs active:cursor-grabbing',
                                selected === r.spelling &&
                                    'ring-2 ring-primary',
                                dragging === r.spelling && 'opacity-40',
                            )
                        "
                        :title="`${r.times} 張借用單，最近 ${r.last_borrow_date}`"
                        @dragstart="onDragStart($event, r.spelling)"
                        @dragend="onDragEnd"
                        @click="
                            selected =
                                selected === r.spelling ? null : r.spelling
                        "
                    >
                        {{ r.spelling }}
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ r.times }}</span
                        >
                    </button>
                    <p
                        v-if="!visibleUngrouped.length"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ q ? '沒有符合的寫法' : '全部都分好組了' }}
                    </p>
                </div>
            </section>

            <!-- 群組泡泡 -->
            <section class="flex flex-wrap content-start gap-3">
                <div
                    :class="
                        cn(
                            zoneClass('new'),
                            'flex min-h-24 w-full items-center justify-center border-muted-foreground/30 p-3 text-sm text-muted-foreground sm:w-[calc(50%-0.375rem)]',
                        )
                    "
                    @dragover.prevent="over = 'new'"
                    @dragleave="over = over === 'new' ? null : over"
                    @drop.prevent="onDrop($event, newGroupFrom)"
                    @click="selected ? onTargetClick(newGroupFrom) : addGroup()"
                >
                    <Plus class="mr-1 size-4" />拖到這裡建立新群組
                </div>

                <div
                    v-for="g in visibleGroups"
                    :key="g.key"
                    :class="
                        cn(
                            'w-full rounded-2xl border bg-card p-3 shadow-xs transition-colors sm:w-[calc(50%-0.375rem)]',
                            (dragging || selected) &&
                                'border-dashed border-muted-foreground/40',
                            over === g.key &&
                                'border-solid border-primary bg-primary/5',
                        )
                    "
                    @dragover.prevent="over = g.key"
                    @dragleave="over = over === g.key ? null : over"
                    @drop.prevent="onDrop($event, (s) => moveTo(s, g.name))"
                    @click.self="onTargetClick((s) => moveTo(s, g.name))"
                >
                    <div class="mb-2 flex items-center gap-2">
                        <Input
                            v-if="editing === g.key"
                            :id="`group-name-${g.key}`"
                            v-model="draftName"
                            class="h-8 font-semibold"
                            aria-label="群組名稱"
                            @keydown.enter.prevent="commitEdit(g)"
                            @keydown.esc="editing = null"
                            @blur="commitEdit(g)"
                        />
                        <button
                            v-else
                            type="button"
                            class="group/name flex min-w-0 items-center gap-1.5 text-left font-semibold"
                            title="點一下改名"
                            @click="startEdit(g.key, g.name)"
                        >
                            <span class="truncate">{{ g.name }}</span>
                            <Pencil
                                class="size-3.5 shrink-0 text-muted-foreground opacity-0 group-hover/name:opacity-100"
                            />
                        </button>
                        <span
                            class="ml-auto shrink-0 text-xs text-muted-foreground tabular-nums"
                            >{{ g.total }} 張</span
                        >
                        <button
                            type="button"
                            class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            :title="
                                g.members.some((m) => !m.implicit)
                                    ? '解散群組，寫法回到未分組'
                                    : '移除群組'
                            "
                            :aria-label="`解散群組 ${g.name}`"
                            @click="disband(g)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <div
                        class="flex min-h-10 flex-wrap gap-2"
                        @click.self="onTargetClick((s) => moveTo(s, g.name))"
                    >
                        <button
                            v-for="m in g.members"
                            :key="m.spelling"
                            type="button"
                            draggable="true"
                            :class="
                                cn(
                                    'inline-flex cursor-grab items-center gap-1.5 rounded-full border px-3 py-1 text-sm active:cursor-grabbing',
                                    m.implicit
                                        ? 'border-primary/40 bg-primary/10'
                                        : 'bg-background',
                                    matches(m.spelling) &&
                                        q &&
                                        'ring-2 ring-amber-400',
                                    selected === m.spelling &&
                                        'ring-2 ring-primary',
                                    dragging === m.spelling && 'opacity-40',
                                )
                            "
                            :title="
                                m.implicit
                                    ? '和群組同名的寫法'
                                    : `${m.times} 張借用單，最近 ${m.last_borrow_date}`
                            "
                            @dragstart="onDragStart($event, m.spelling)"
                            @dragend="onDragEnd"
                            @click="
                                selected =
                                    selected === m.spelling ? null : m.spelling
                            "
                        >
                            {{ m.spelling }}
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                >{{ m.times }}</span
                            >
                        </button>
                        <p
                            v-if="!g.members.length"
                            class="w-full py-2 text-center text-xs text-muted-foreground"
                        >
                            把寫法拖進來
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
