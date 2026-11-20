<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: '外觀',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="外觀" />

    <h1 class="sr-only">外觀</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="外觀"
            description="選擇淺色、深色或跟隨系統"
        />
        <AppearanceTabs />
    </div>
</template>
