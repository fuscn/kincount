<template>
  <van-cell-group title="SKU明细">
    <van-swipe-cell v-for="(row,idx) in localItems" :key="idx">
      <van-cell
        :title="row.sku_code"
        :label="row.spec_text"
        :value="`×${row.quantity}`"
      />
      <template #right>
        <van-button square type="danger" text="删除" @click="del(idx)" />
      </template>
    </van-swipe-cell>
    <van-button plain type="primary" size="small" icon="plus" @click="add">添加SKU</van-button>
  </van-cell-group>

  <!-- 选择器 -->
  <sku-select v-model="selectId" @change="onAddRow" />
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import SkuSelect from './SkuSelect.vue'
import { getSkuDetail } from '@/api/product'

const props = defineProps({
  items: { type: Array, default: () => [] },
  editable: { type: Boolean, default: true },
  warehouseId: [String, Number]
})
const emit = defineEmits(['update:items'])

const localItems = ref([...props.items])
watch(() => props.items, val => localItems.value = [...val], { deep: true })

const selectId = ref('')
const onAddRow = async (sku) => {
  const exist = localItems.value.find(i => i.sku_id === sku.id)
  if (exist) return showFailToast('已存在')
  const full = await getSkuDetail(sku.id)
  localItems.value.push({
    sku_id: full.id,
    sku_code: full.sku_code,
    spec_text: full.spec_text,
    quantity: 1,
    price: full.cost_price
  })
  emit('update:items', localItems.value)
}
const del = (idx) => {
  localItems.value.splice(idx, 1)
  emit('update:items', localItems.value)
}
const add = () => { selectId.value = '' } // 触发选择器
</script>