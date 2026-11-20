<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { itemStatusMeta, requestStatusMeta, toneClasses } from '@/lib/format';
import type { BorrowItemStatus, BorrowRequestStatus } from '@/types';

const props = defineProps<
    | { kind: 'request'; status: BorrowRequestStatus }
    | { kind: 'item'; status: BorrowItemStatus }
>();

const meta = computed(() =>
    props.kind === 'request'
        ? requestStatusMeta[props.status]
        : itemStatusMeta[props.status],
);
</script>

<template>
    <Badge :class="toneClasses[meta.tone]">{{ meta.label }}</Badge>
</template>
