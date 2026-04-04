

<template>
    <div class="p-5 shadow-sm rounded-3 bg-body-tertiary">
        <h1 class="text-primary">Hello NCUE SA!</h1>
        <p class="lead">Vue is successfully integrated with Bootstrap 5.</p>
        <button @click="count++" class="btn btn-success">
            Count is: {{ count }}
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue';

// Using ref for reactive state
const count = ref(0);
</script>