<template>
  <div class="account-payable-create-page">
    <van-nav-bar title="新增应付记录" left-text="取消" right-text="保存" left-arrow
      @click-left="handleCancel" @click-right="handleSubmit" />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <van-cell-group title="基本信息">
        <van-field v-model="supplierDisplayText" label="供应商" placeholder="请选择供应商" readonly is-link required
          :rules="[{ required: true, message: '请选择供应商' }]" @click="showSupplierPicker = true" />
        <van-field v-model="form.amount" label="应付金额" type="number" placeholder="请输入应付金额" required
          :rules="[{ required: true, message: '请输入应付金额' }, { validator: validateAmount, message: '金额必须大于0' }]">
          <template #extra>元</template>
        </van-field>
        <van-field v-model="form.due_date" label="应付日期" placeholder="请选择应付日期" readonly is-link required
          :rules="[{ required: true, message: '请选择应付日期' }]" @click="showDueDatePicker = true" />
        <van-field v-model="form.bill_no" label="单据编号" placeholder="请输入单据编号（可选）" />
      </van-cell-group>

      <van-cell-group title="详细信息">
        <van-field v-model="form.remark" label="备注说明" type="textarea" placeholder="请输入备注信息（可选）" rows="2"
          autosize maxlength="200" show-word-limit />
        <van-field v-model="form.payment_terms" label="付款条件" placeholder="请输入付款条件（可选）" />
        <van-field v-model="form.related_order" label="关联订单" placeholder="请输入关联订单号（可选）" />
      </van-cell-group>

      <van-cell-group title="附件上传">
        <div class="upload-section">
          <van-uploader v-model="fileList" :max-count="3" :max-size="5 * 1024 * 1024" @oversize="onOverSize"
            :before-read="beforeRead" :after-read="afterRead" />
          <div class="upload-tips">支持上传图片，最多3张，每张不超过5M</div>
        </div>
      </van-cell-group>

      <van-cell-group title="金额预览" v-if="form.amount">
        <van-cell title="应付金额" :value="`¥${formatAmount(form.amount)}`" value-class="expense-amount" />
      </van-cell-group>
    </van-form>

    <van-popup v-model:show="showSupplierPicker" position="bottom">
      <van-picker :columns="supplierOptions" @confirm="onSupplierConfirm" @cancel="showSupplierPicker = false" />
    </van-popup>

    <van-popup v-model:show="showDueDatePicker" position="bottom">
      <van-date-picker :min-date="minDate" :max-date="maxDate" @confirm="onDueDateConfirm" 
        @cancel="showDueDatePicker = false" />
    </van-popup>

    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { searchSupplierSelect } from '@/api/supplier'
import { addAccountRecord } from '@/api/account'
import { uploadImage } from '@/api/utils'
import dayjs from 'dayjs'

const router = useRouter()
const formRef = ref()

const form = reactive({
  supplier_id: '', supplier_name: '', amount: '', 
  due_date: dayjs().add(30, 'day').format('YYYY-MM-DD'),
  bill_no: '', remark: '', payment_terms: '', related_order: '', attachments: []
})

const showSupplierPicker = ref(false)
const showDueDatePicker = ref(false)
const fileList = ref([])
const loading = ref(false)
const supplierOptions = ref([])
const supplierDisplayText = ref('')

const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

const formatAmount = (amount) => {
  const num = parseFloat(amount)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const validateAmount = (value) => !isNaN(Number(value)) && Number(value) > 0

const loadSuppliers = async () => {
  loading.value = true
  try {
    const result = await searchSupplierSelect({ keyword: '' })
    let listData = result?.list || result?.data?.list || Array.isArray(result) ? result : result || []
    
    supplierOptions.value = listData.map(supplier => ({
      text: supplier.name, value: supplier.id
    }))
  } catch {
    showToast('加载供应商数据失败')
    supplierOptions.value = [
      { text: '供应商A', value: '1' }, { text: '供应商B', value: '2' }, { text: '供应商C', value: '3' }
    ]
  } finally {
    loading.value = false
  }
}

const beforeRead = (file) => {
  const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']
  if (!allowedTypes.includes(file.type)) {
    showToast('请上传 jpg/png/gif 格式图片')
    return false
  }
  return true
}

const afterRead = async (file) => {
  file.status = 'uploading'
  file.message = '上传中...'
  try {
    const result = await uploadImage(file.file)
    file.status = 'done'
    file.message = '上传成功'
    file.url = result.url || result.data || result
    if (file.url && !form.attachments.includes(file.url)) {
      form.attachments.push(file.url)
    }
  } catch {
    file.status = 'failed'
    file.message = '上传失败'
    showToast('文件上传失败')
  }
}

const onOverSize = () => showToast('图片大小不能超过 5M')

const onSupplierConfirm = (value) => {
  const selected = value.selectedOptions[0]
  form.supplier_id = selected.value
  form.supplier_name = selected.text
  supplierDisplayText.value = selected.text
  showSupplierPicker.value = false
}

const onDueDateConfirm = (value) => {
  const selectedDate = new Date(value.selectedValues.year, value.selectedValues.month - 1, value.selectedValues.day)
  form.due_date = dayjs(selectedDate).format('YYYY-MM-DD')
  showDueDatePicker.value = false
}

const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '确定要取消新增应付记录吗？所有未保存的数据将会丢失。'
  }).then(() => router.back())
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    if (!form.supplier_id) return showToast('请选择供应商')
    if (!form.amount || Number(form.amount) <= 0) return showToast('请输入有效的应付金额')

    const submitData = {
      type: 2, supplier_id: form.supplier_id, amount: Number(form.amount), due_date: form.due_date,
      bill_no: form.bill_no || '', remark: form.remark || '', payment_terms: form.payment_terms || '',
      related_order: form.related_order || '', attachments: form.attachments
    }

    loading.value = true
    await addAccountRecord(submitData)
    showSuccessToast('应付记录创建成功')
    router.push('/account/payable')
  } catch (error) {
    if (error.message && error.message !== 'cancel') {
      showToast(error.message || '保存失败，请检查表单数据')
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => loadSuppliers())
</script>

<style scoped lang="scss">
.account-payable-create-page { min-height: 100vh; }

.page-loading {
  position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999;
}

.upload-section {
  padding: 12px 16px;
  .upload-tips { font-size: 12px; color: #969799; margin-top: 8px; }
}

:deep(.van-field__label) { width: 80px; flex: none; }
:deep(.van-field__value) { text-align: right; }
:deep(input[type="number"]) { text-align: right; font-size: 16px; }
:deep(.van-picker) { background: #fff; }
:deep(.van-picker__confirm) { color: #1989fa; }
:deep(.van-picker__cancel) { color: #969799; }
:deep(.van-uploader) { width: 100%; }
:deep(.van-uploader__upload), :deep(.van-uploader__preview) { width: 80px; height: 80px; margin: 0 8px 8px 0; }
:deep(.van-uploader__preview-image) { width: 100%; height: 100%; object-fit: cover; }
:deep(.van-field__body textarea) { min-height: 60px; resize: none; }
:deep(.expense-amount) { color: #ee0a24; font-weight: bold; }
</style>