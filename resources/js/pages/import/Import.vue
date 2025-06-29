<script setup lang="ts">
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const apiData = page.props.apiData;
const error = page.props.error;

console.log('ページのプロップス：', page.props);
console.log('apiData：', apiData);
</script>

<template>
    <div>
        <h1>Importページです</h1>
        <div v-if="error" style="color: red;"> {{ error }}</div>
        <div v-else-if="apiData">
            <h2>APIから取得したデータ</h2>
            <pre>{{ apiData }}</pre>
        </div>
        <div v-else>
            データがありません。
        </div>
    </div>
</template>