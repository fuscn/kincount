<!-- src/components/common/Upload.vue -->
<template>
  <div class="upload-wrap">
    <Uploader
      v-model:file-list="fileList"
      :max-count="maxCount"
      :max-size="maxSize"
      @oversize="onOverSize"
      :before-read="beforeRead"
      :after-read="afterRead"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Uploader, Toast } from 'vant'
import { uploadImage } from '@/api/utils'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  maxCount: { type: Number, default: 5 },
  maxSize: { type: Number, default: 5 * 1024 * 1024 } // 5M
})

const emit = defineEmits(['update:modelValue'])

const fileList = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val)
})

function beforeRead(file) {
  if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) {
    Toast('请上传 jpg/png 格式图片')
    return false
  }
  return true
}

async function afterRead(item) {
  const formData = new FormData()
  formData.append('file', item.file)
  try {
    const { url } = await uploadImage(item.file)
    item.url = url
  } catch (e) {
    Toast('上传失败')
    fileList.value = fileList.value.filter(f => f !== item)
  }
}

function onOverSize() {
  Toast(`图片大小不能超过 ${props.maxSize / 1024 / 1024}M`)
}
</script>

<style scoped>
.upload-wrap {
  padding: 8px 0;
}
</style>