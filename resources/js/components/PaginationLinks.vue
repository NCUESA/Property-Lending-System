<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginator } from '@/types';

defineProps<{
    paginator: Paginator<unknown>;
}>();

// Laravel 的上一頁／下一頁標籤是 HTML entity，換成中文
function label(raw: string): string {
    if (raw.includes('Previous') || raw.includes('&laquo;')) return '上一頁';
    if (raw.includes('Next') || raw.includes('&raquo;')) return '下一頁';

    return raw;
}
</script>

<template>
    <nav
        v-if="paginator.last_page > 1 || paginator.total > 0"
        class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm"
        aria-label="分頁"
    >
        <p class="text-muted-foreground">
            第 {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} 筆，共
            {{ paginator.total }} 筆
        </p>
        <div v-if="paginator.last_page > 1" class="flex flex-wrap gap-1">
            <template v-for="(link, i) in paginator.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="rounded-md border px-3 py-1.5 transition-colors hover:bg-accent"
                    :class="
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground hover:bg-primary'
                            : ''
                    "
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ label(link.label) }}
                </Link>
                <span
                    v-else
                    class="rounded-md border px-3 py-1.5 text-muted-foreground opacity-50"
                >
                    {{ label(link.label) }}
                </span>
            </template>
        </div>
    </nav>
</template>
