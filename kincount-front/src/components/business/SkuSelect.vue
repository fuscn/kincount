<template>
  <div>
    <van-field
      v-model="displayText"
      readonly
      is-link
      label="选择SKU"
      placeholder="点击选择SKU"
      @click="show = true"
    />
    <van-popup v-model:show="show" position="bottom" round>
      <van-search v-model="keyword" placeholder="输入编码/规格搜索" />
      <van-list
        :finished="finished"
        finished-text="没有更多"
        @load="onLoad"
      >
        <van-cell
          v-for="item in list"
          :key="item.id"
          :title="item.sku_code"
          :label="item.spec_text"
          @click="onPick(item)"
        />
      </van-list>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { getSkuSelectList } from '@/api/product'

const props = defineProps({
  modelValue: [String, Number],   // skuId
  warehouseId: [String, Number],
  multiple: Boolean               // 后续可扩展多选
})
const emit = defineEmits(['update:modelValue', 'change'])

const show = ref(false)
const keyword = ref('')
const list = ref([])
const finished = ref(false)
const displayText = computed(() => list.value.find(i => i.id === props.modelValue)?.sku_code || '')

watch(keyword, () => { list.value = []; finished.value = false })

const onLoad = async () => {
  const res = await getSkuSelectList({ keyword: keyword.value, limit: 20, page: Math.ceil(list.value.length / 20) + 1 })
  list.value.push(...res.list)
  finished.value = res.list.length < 20
}

const onPick = (item) => {
  emit('update:modelValue', item.id)
  emit('change', item)
  show.value = false
}
</script>