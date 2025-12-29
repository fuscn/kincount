<!-- src/components/common/Upload.vue -->
<template>
  <div class="upload-wrap">
    <div class="upload-area" @click="handleUploadClick">
      <van-icon name="plus" size="24" />
      <span>上传图片</span>
    </div>
    <div v-if="fileList.length > 0" class="preview-list">
      <div v-for="(file, index) in fileList" :key="index" class="preview-item">
        <img :src="file.url || file" alt="预览图片" class="preview-image" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { showToast } from 'vant'
import { Icon } from 'vant'

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

function handleUploadClick() {
  showToast('功能正在开发中')
}
</script>

<style scoped>
.upload-wrap {
  padding: 8px 0;
}

.upload-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 80px;
  height: 80px;
  background-color: #f7f8fa;
  border: 1px dashed #dcdee0;
  border-radius: 4px;
  color: #969799;
  cursor: pointer;
}

.upload-area span {
  margin-top: 8px;
  font-size: 12px;
}

.preview-list {
  display: flex;
  flex-wrap: wrap;
  margin-top: 8px;
  gap: 8px;
}

.preview-item {
  width: 80px;
  height: 80px;
  border-radius: 4px;
  overflow: hidden;
}

.preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>