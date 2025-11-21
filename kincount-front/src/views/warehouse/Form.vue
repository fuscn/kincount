<template>
  <div class="warehouse-form-page">
    <!-- 顶部导航 -->
    <van-nav-bar
      :title="isEdit ? '编辑仓库' : '新增仓库'"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <!-- 表单 -->
    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <!-- 基本信息 -->
      <van-cell-group title="基本信息">
        <van-field
          v-model="form.name"
          label="仓库名称"
          placeholder="请输入仓库名称"
          maxlength="20"
          show-word-limit
          required
          :rules="[{ required: true, message: '请输入仓库名称' }]"
        />
        <van-field
          v-model="form.code"
          label="仓库编码"
          placeholder="请输入仓库编码"
          maxlength="20"
          show-word-limit
          required
          :rules="[{ required: true, message: '请输入仓库编码' }]"
          :readonly="isEdit"
        />
        <van-field
          v-model="form.manager"
          label="负责人"
          placeholder="请输入负责人"
          maxlength="20"
          show-word-limit
        />
        <van-field
          v-model="form.phone"
          label="联系电话"
          placeholder="请输入联系电话"
          type="tel"
          maxlength="20"
          :rules="[{ validator: validatePhone, message: '请输入正确的手机号码' }]"
        />
        <van-field
          v-model="form.address"
          label="详细地址"
          placeholder="请输入详细地址"
          type="textarea"
          rows="2"
          autosize
          maxlength="100"
          show-word-limit
        />
      </van-cell-group>

      <!-- 状态 -->
      <van-cell-group title="状态设置">
        <van-cell center title="启用仓库">
          <template #right-icon>
            <van-switch v-model="statusBool" />
          </template>
        </van-cell>
      </van-cell-group>

      <!-- 备注 -->
      <van-cell-group title="备注信息">
        <van-field
          v-model="form.remark"
          label="备注"
          type="textarea"
          placeholder="请输入备注（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
      </van-cell-group>

      <!-- 时间信息（编辑时展示） -->
      <van-cell-group title="时间信息" v-if="isEdit">
        <van-cell title="创建时间" :value="formatDate(form.created_at)" />
        <van-cell title="更新时间" :value="formatDate(form.updated_at)" />
      </van-cell-group>
    </van-form>

    <!-- 加载遮罩 -->
    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  showToast,
  showConfirmDialog,
  showSuccessToast
} from 'vant'
import { useWarehouseStore } from '@/store/modules/warehouse'
import { addWarehouse, updateWarehouse, getWarehouseDetail } from '@/api/warehouse'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const warehouseStore = useWarehouseStore()
const formRef = ref()

/* ================= 响应式数据 ================= */
const loading = ref(false)
const isEdit = computed(() => !!route.params.id)

// 表单数据结构与API保持一致
const form = reactive({
  id: '',
  name: '',
  code: '',
  manager: '',
  phone: '',
  address: '',
  status: 1,
  remark: '',
  capacity: null,
  created_at: '',
  updated_at: ''
})

const statusBool = computed({
  get: () => form.status === 1,
  set: (val) => (form.status = val ? 1 : 0)
})

/* ================= 工具函数 ================= */
const formatDate = (d) => (d ? dayjs(d).format('YYYY-MM-DD HH:mm') : '')

// 手机号验证
const validatePhone = (value) => {
  if (!value) return true // 允许为空
  return /^1[3-9]\d{9}$/.test(value)
}

/* ================= 数据加载 ================= */
const loadDetail = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await getWarehouseDetail(route.params.id)
    const data = res.data || res
    
    // 将API数据直接赋给表单
    Object.keys(form).forEach(key => {
      if (data[key] !== undefined) {
        form[key] = data[key]
      }
    })
  } catch (e) {
    console.error('加载详情失败:', e)
    showToast('加载详情失败')
    router.back()
  } finally {
    loading.value = false
  }
}

/* ================= 事件处理 ================= */
const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '未保存的内容将丢失，是否继续？'
  })
    .then(() => router.back())
    .catch(() => {})
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    if (loading.value) return
    loading.value = true

    // 准备提交数据
    const payload = {
      name: form.name.trim(),
      code: form.code.trim(),
      manager: form.manager.trim(),
      phone: form.phone.trim(),
      address: form.address.trim(),
      status: form.status,
      remark: form.remark.trim(),
      capacity: form.capacity
    }

    if (isEdit.value) {
      await updateWarehouse(form.id, payload)
      showSuccessToast('仓库更新成功')
    } else {
      await addWarehouse(payload)
      showSuccessToast('仓库创建成功')
    }
    
    // 返回仓库列表页面
    router.replace('/warehouse')
  } catch (e) {
    if (e?.name !== 'ValidateError') {
      console.error('保存失败:', e)
      showToast(e?.message || '保存失败')
    }
  } finally {
    loading.value = false
  }
}

/* ================= 生命周期 ================= */
onMounted(() => loadDetail())
</script>

<style scoped lang="scss">
.warehouse-form-page {
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