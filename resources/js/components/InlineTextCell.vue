<script setup lang="ts">
import { ref, watch } from 'vue';

/**
 * 表格內直接編輯的文字格：平常看起來像純文字，聚焦才顯示輸入框。
 * 失焦或按 Enter 時若內容有變才送出；Esc 還原。
 * 送出失敗時由父層遞增 resetKey，把顯示值還原成伺服器的值。
 */
const props = defineProps<{
    value: string;
    /** 無障礙標籤。不叫 ariaLabel 是因為會和原生 aria-label 屬性混淆。 */
    label: string;
    resetKey?: number;
    maxlength?: number;
    uppercase?: boolean;
    mono?: boolean;
    disabled?: boolean;
    placeholder?: string;
}>();

const emit = defineEmits<{ save: [value: string] }>();

const draft = ref(props.value);
const input = ref<HTMLInputElement | null>(null);

watch(
    () => [props.value, props.resetKey],
    () => {
        draft.value = props.value;
    },
);

function commit() {
    const next = props.uppercase
        ? draft.value.trim().toUpperCase()
        : draft.value.trim();

    draft.value = next;

    if (next === props.value) {
        return;
    }

    emit('save', next);
}
</script>

<template>
    <input
        ref="input"
        v-model="draft"
        :maxlength="maxlength"
        :disabled="disabled"
        :placeholder="placeholder"
        :aria-label="label"
        :title="disabled ? '已有器材使用此編號，不能修改' : undefined"
        class="w-full rounded-sm border border-transparent bg-transparent px-2 py-1 text-sm transition-colors outline-none hover:border-input focus:border-ring focus:bg-background focus:ring-2 focus:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-transparent"
        :class="[mono && 'font-mono', uppercase && 'uppercase']"
        @blur="commit"
        @keydown.enter.prevent="input?.blur()"
        @keydown.esc.prevent="
            draft = value;
            input?.blur();
        "
    />
</template>
