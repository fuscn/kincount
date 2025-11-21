<template>
  <div class="brand-form-page">
    <van-nav-bar
      :title="isEdit ? '编辑品牌' : '新增品牌'"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <van-cell-group title="基本信息">
        <van-field
          v-model="form.name"
          label="品牌名称"
          placeholder="请输入品牌名称"
          maxlength="20"
          show-word-limit
          required
          :rules="[{ required: true, message: '请输入品牌名称' }]"
        />
        <van-field
          v-model="form.code"
          label="英文名称"
          placeholder="请输入英文名称（可选）"
          maxlength="50"
        />
        <van-field
          v-model.number="form.sort"
          label="排序"
          type="digit"
          placeholder="数字越小越靠前"
          required
          :rules="[{ required: true, message: '请输入排序' }]"
        />
      </van-cell-group>

      <van-cell-group title="品牌 Logo">
        <Upload v-model="form.logo" :max-count="1" />
      </van-cell-group>

      <van-cell-group title="品牌描述">
        <van-field
          v-model="form.description"
          label="品牌描述"
          type="textarea"
          placeholder="请输入品牌描述（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
      </van-cell-group>

      <van-cell-group title="状态">
        <van-cell center title="启用品牌">
          <template #right-icon>
            <van-switch v-model="statusBool" />
          </template>
        </van-cell>
      </van-cell-group>

      <van-cell-group title="时间信息" v-if="isEdit">
        <van-cell title="创建时间" :value="formatDate(form.created_at)" />
        <van-cell title="更新时间" :value="formatDate(form.updated_at)" />
      </van-cell-group>
    </van-form>

    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { useBrandStore } from '@/store/modules/brand'
import { addBrand, updateBrand, getBrandDetail } from '@/api/brand'
import Upload from '@/components/common/Upload.vue'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const brandStore = useBrandStore()
const formRef = ref()

const loading = ref(false)
const isEdit = computed(() => !!route.params.id)

// 添加防重复请求标志
let isRequesting = false
let hasLoaded = false

// 根据API响应结构调整表单字段
const form = reactive({
  id: '',
  name: '',
  code: '',           // 英文名称
  logo: [],           // Logo图片
  sort: 99,
  description: '',    // 品牌描述
  status: 1,
  created_at: '',
  updated_at: ''
})

const statusBool = computed({
  get: () => form.status === 1,
  set: (val) => (form.status = val ? 1 : 0)
})

const formatDate = (d) => (d ? dayjs(d).format('YYYY-MM-DD HH:mm') : '')

const loadDetail = async () => {
  // 防止重复请求
  if (isRequesting || hasLoaded) return
  
  if (!isEdit.value) return
  
  isRequesting = true
  loading.value = true
  
  try {
    const res = await getBrandDetail(route.params.id)
    const data = res.data || res
    
    console.log('加载品牌详情:', data) // 调试信息
    
    // 根据API响应数据正确映射字段
    Object.assign(form, {
      id: data.id,
      name: data.name,
      code: data.code || '',                    // 英文名称
      logo: data.logo ? [data.logo] : [],       // Logo处理为数组格式
      sort: data.sort || 99,
      description: data.description || '',      // 品牌描述
      status: data.status,
      created_at: data.created_at,
      updated_at: data.updated_at
    })
    
    hasLoaded = true
  } catch (error) {
    console.error('加载详情失败:', error)
    showToast('加载详情失败')
    router.back()
  } finally {
    loading.value = false
    isRequesting = false
  }
}

const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '未保存的内容将丢失，是否继续？'
  })
    .then(() => router.back())
    .catch(() => {})
}

const handleSubmit = async () => {
  // 防止重复提交
  if (loading.value) return
  
  try {
    await formRef.value.validate()
    loading.value = true

    // 准备提交数据，与API期望的字段匹配
    const payload = {
      name: form.name.trim(),
      code: form.code.trim(),                    // 英文名称
      logo: form.logo.length > 0 ? form.logo[0] : '',  // Logo取数组第一个元素
      sort: Number(form.sort),
      description: form.description.trim(),      // 品牌描述
      status: form.status
    }

    console.log('提交数据:', payload) // 调试信息

    if (isEdit.value) {
      await updateBrand(form.id, payload)
      showSuccessToast('品牌更新成功')
    } else {
      await addBrand(payload)
      showSuccessToast('品牌创建成功')
    }
    router.replace('/brand')
  } catch (e) {
    if (e?.name !== 'ValidateError') {
      console.error('保存失败:', e)
      showToast(e?.message || '保存失败')
    }
  } finally {
    loading.value = false
  }
}

// 组件挂载时加载数据
onMounted(() => {
  console.log('品牌表单组件挂载')
  loadDetail()
})

// 组件卸载时重置状态
onUnmounted(() => {
  console.log('品牌表单组件卸载')
  isRequesting = false
  hasLoaded = false
})
</script>

<style scoped lang="scss">
.brand-form-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.form-container {
  padding: 12px;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 9999;
}

:deep(.van-cell-group__title) {
  font-size: 14px;
  color: #323233;
  font-weight: 500;
  padding: 16px 16px 8px;
  background: #f7f8fa;
}

:deep(.van-field__label) {
  width: 80px;
  flex: none;
}
</style>