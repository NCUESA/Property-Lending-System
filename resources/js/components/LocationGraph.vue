<script setup lang="ts">
import { computed } from 'vue';

/*
 * 地點互連的有向圖。
 *
 * 左欄是取用地點、右欄是它借得到的存放地點，箭頭由左指向右。
 * 同一個地點可能兩邊都出現（例如進德自己也放器材、又借得到 307）。
 * 自己借自己是隱含的，不畫成邊，否則每個取用點都會多一條沒有資訊量的線。
 */

type GraphLocation = {
    id: number;
    name: string;
    is_pickup_point: boolean;
    is_active: boolean;
    serves: number[];
};

const props = defineProps<{ locations: GraphLocation[] }>();

const ROW = 46;
const PAD = 14;
const BOX_W = 150;
const BOX_H = 32;
const LEFT_X = 8;
const RIGHT_X = 300;
const WIDTH = RIGHT_X + BOX_W + 8;

/** 左欄：有連出任何一條邊的取用地點。沒有邊的不畫，圖才不會一堆孤點。 */
const sources = computed(() =>
    props.locations.filter((l) => l.is_pickup_point && l.serves.length > 0),
);

/** 右欄：被指到的地點，依第一次出現的順序排。 */
const targets = computed(() => {
    const seen: GraphLocation[] = [];

    for (const source of sources.value) {
        for (const id of source.serves) {
            const target = props.locations.find((l) => l.id === id);

            if (target && !seen.includes(target)) {
                seen.push(target);
            }
        }
    }

    return seen;
});

const height = computed(
    () => Math.max(sources.value.length, targets.value.length, 1) * ROW + PAD,
);

const y = (index: number) => PAD + index * ROW;
const midY = (index: number) => y(index) + BOX_H / 2;

type Edge = { key: string; d: string };

const edges = computed<Edge[]>(() => {
    const list: Edge[] = [];

    sources.value.forEach((source, si) => {
        for (const id of source.serves) {
            const ti = targets.value.findIndex((t) => t.id === id);

            if (ti === -1) continue;

            const x1 = LEFT_X + BOX_W;
            const x2 = RIGHT_X;
            const y1 = midY(si);
            const y2 = midY(ti);
            const mid = (x1 + x2) / 2;

            list.push({
                key: `${source.id}-${id}`,
                d: `M ${x1} ${y1} C ${mid} ${y1}, ${mid} ${y2}, ${x2 - 8} ${y2}`,
            });
        }
    });

    return list;
});
</script>

<template>
    <div
        v-if="!sources.length"
        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
    >
        還沒有設定任何互連關係。每個取用地點目前只借得到自己的器材。
    </div>

    <div v-else class="overflow-x-auto">
        <svg
            :viewBox="`0 0 ${WIDTH} ${height}`"
            :width="WIDTH"
            :height="height"
            role="img"
            aria-label="地點互連關係圖"
            class="max-w-full"
        >
            <defs>
                <marker
                    id="location-arrow"
                    viewBox="0 0 10 10"
                    refX="9"
                    refY="5"
                    markerWidth="6"
                    markerHeight="6"
                    orient="auto-start-reverse"
                >
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor" />
                </marker>
            </defs>

            <g class="text-muted-foreground/60">
                <path
                    v-for="edge in edges"
                    :key="edge.key"
                    :d="edge.d"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    marker-end="url(#location-arrow)"
                />
            </g>

            <!-- 取用地點 -->
            <g v-for="(source, i) in sources" :key="`s-${source.id}`">
                <rect
                    :x="LEFT_X"
                    :y="y(i)"
                    :width="BOX_W"
                    :height="BOX_H"
                    rx="6"
                    class="fill-primary/10 stroke-primary/40"
                    stroke-width="1"
                />
                <text
                    :x="LEFT_X + 10"
                    :y="midY(i) + 4"
                    class="fill-foreground text-[12px]"
                    :class="source.is_active ? '' : 'opacity-50'"
                >
                    {{ source.name }}
                </text>
            </g>

            <!-- 存放地點 -->
            <g v-for="(target, i) in targets" :key="`t-${target.id}`">
                <rect
                    :x="RIGHT_X"
                    :y="y(i)"
                    :width="BOX_W"
                    :height="BOX_H"
                    rx="6"
                    class="fill-muted stroke-border"
                    stroke-width="1"
                />
                <text
                    :x="RIGHT_X + 10"
                    :y="midY(i) + 4"
                    class="fill-foreground text-[12px]"
                    :class="target.is_active ? '' : 'opacity-50'"
                >
                    {{ target.name }}
                </text>
            </g>
        </svg>

        <p class="mt-2 text-xs text-muted-foreground">
            左邊取件 → 右邊的器材借得到
        </p>
    </div>
</template>
