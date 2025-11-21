<template>
  <div class="supplier-form-page">
    <van-nav-bar
      :title="isEdit ? '编辑供应商' : '新增供应商'"
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
          label="供应商名称"
          placeholder="请输入供应商名称"
          maxlength="50"
          show-word-limit
          required
          :rules="[{ required: true, message: '请输入供应商名称' }]"
        />
        <van-field
          v-model="form.contact_person"
          label="联系人"
          placeholder="请输入联系人"
          maxlength="20"
        />
        <van-field
          v-model="form.phone"
          label="联系电话"
          placeholder="请输入联系电话"
          type="tel"
          maxlength="11"
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

      <van-cell-group title="状态">
        <van-cell center title="启用供应商">
          <template #right-icon>
            <van-switch v-model="statusBool" />
          </template>
        </van-cell>
      </van-cell-group>

      <van-cell-group title="备注">
        <van-field
          v-model="form.remark"
          label="备注说明"
          type="textarea"
          placeholder="请输入备注（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
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
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { useSupplierStore } from '@/store/modules/supplier'
import { addSupplier, updateSupplier, getSupplierDetail } from '@/api/supplier'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const supplierStore = useSupplierStore()
const formRef = ref()

const loading = ref(false)
const isEdit = computed(() => !!route.params.id)

const form = reactive({
  id: '',
  name: '',
  contact_person: '',
  phone: '',
  address: '',
  status: 1,
  remark: '',
  created_at: '',
  updated_at: ''
})

const statusBool = computed({
  get: () => form.status === 1,
  set: (val) => (form.status = val ? 1 : 0)
})

const formatDate = (d) => (d ? dayjs(d).format('YYYY-MM-DD HH:mm') : '')

const loadDetail = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await getSupplierDetail(route.params.id)
    Object.assign(form, res.data || res)
  } catch {
    showToast('加载详情失败')
    router.back()
  } finally {
    loading.value = false
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
  try {
    await formRef.value.validate()
    if (loading.value) return
    loading.value = true

    const payload = {
      name: form.name.trim(),
      contact_person: form.contact_person.trim(),
      phone: form.phone.trim(),
      address: form.address.trim(),
      status: form.status,
      remark: form.remark.trim()
    }

    if (isEdit.value) {
      await updateSupplier(form.id, payload)
      showSuccessToast('供应商更新成功')
    } else {
      await addSupplier(payload)
      showSuccessToast('供应商创建成功')
    }
    router.replace('/supplier')
  } catch (e) {
    if (e?.name !== 'ValidateError') showToast(e?.message || '保存失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => loadDetail())
</script>

<style scoped lang="scss">
.supplier-form-page {
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