<script setup lang="ts">
// TODO(第三步)：佔位頁面。未宣告的 props 會落在 $attrs，先印出來方便檢查後端資料。
defineOptions({ inheritAttrs: false });
</script>

<template>
    <main class="p-6">
        <h1 class="mb-4 text-xl font-semibold">Admin/Groups/Index</h1>
        <pre class="overflow-x-auto rounded bg-muted p-4 text-xs">{{ $attrs }}</pre>
    </main>
</template>
