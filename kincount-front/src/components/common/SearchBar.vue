<template>
  <Search
    v-model="keyword"
    :placeholder="placeholder"
    show-action
    @search="onSearch"
    @cancel="$emit('cancel')"
  >
    <template #action>
      <div @click="onSearch">搜索</div>
    </template>
  </Search>
</template>

<script setup>
import { ref } from 'vue'
import { Search } from 'vant'

const props = defineProps({
  placeholder: { type: String, default: '请输入关键词' }
})

const emit = defineEmits(['search', 'cancel'])

const keyword = ref('')

function onSearch() {
  emit('search', keyword.value)
}
</script>