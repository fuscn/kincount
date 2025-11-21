<!-- src/components/common/Pagination.vue -->
<template>
  <Pagination
    v-model="currentPage"
    :page-count="pageCount"
    :total-items="total"
    :show-page-size="3"
    force-ellipses
    @change="onChange"
  />
</template>

<script setup>
import { computed } from 'vue'
import { Pagination } from 'vant'

const props = defineProps({
  total: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  pageSize: { type: Number, default: 15 }
})

const emit = defineEmits(['update:page', 'change'])

const currentPage = computed({
  get: () => props.page,
  set: val => emit('update:page', val)
})

const pageCount = computed(() =>
  Math.ceil(props.total / props.pageSize)
)

function onChange(page) {
  emit('change', page)
}
</script>