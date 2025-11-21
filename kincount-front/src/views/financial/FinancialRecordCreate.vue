<template>
  <div class="financial-record-create-page">
    <van-nav-bar 
      title="新建收支记录"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <van-cell-group title="基本信息">
        <van-field
          v-model="typeDisplayText"
          label="收支类型"
          placeholder="请选择收支类型"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择收支类型' }]"
          @click="showTypePicker = true"
        />
        <van-field
          v-model="form.category_name"
          label="收支分类"
          placeholder="请选择分类"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择收支分类' }]"
          @click="showCategoryPicker = true"
        />
        <van-field
          v-model="form.amount"
          label="金额"
          type="number"
          placeholder="请输入金额"
          required
          :rules="[
            { required: true, message: '请输入金额' },
            { validator: validateAmount, message: '金额必须大于0' }
          ]"
        >
          <template #extra>元</template>
        </van-field>
        <van-field
          v-model="form.record_date"
          label="收支日期"
          placeholder="请选择日期"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择收支日期' }]"
          @click="showDatePicker = true"
        />
      </van-cell-group>

      <van-cell-group title="详细信息">
        <van-field
          v-model="form.remark"
          label="备注说明"
          type="textarea"
          placeholder="请输入备注信息（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
        <van-field
          v-model="form.payment_method"
          label="支付方式"
          placeholder="请选择支付方式"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择支付方式' }]"
          @click="showPaymentPicker = true"
        />
        <van-field
          v-model="form.related_order"
          label="关联单号"
          placeholder="请输入关联订单号（可选）"
        />
      </van-cell-group>

      <van-cell-group title="附件上传">
        <div class="upload-section">
          <van-uploader
            v-model="fileList"
            :max-count="3"
            :max-size="5 * 1024 * 1024"
            @oversize="onOverSize"
            :before-read="beforeRead"
            :after-read="afterRead"
          />
          <div class="upload-tips">支持上传图片，最多3张，每张不超过5M</div>
        </div>
      </van-cell-group>

      <van-cell-group title="金额预览" v-if="form.amount">
        <van-cell title="收支类型" :value="typeDisplayText" />
        <van-cell title="金额" :value="getAmountDisplay(form.type, form.amount)" 
                  :value-class="getAmountClass(form.type)" />
      </van-cell-group>
    </van-form>

    <van-popup v-model:show="showTypePicker" position="bottom">
      <van-picker :columns="typeOptions" @confirm="onTypeConfirm" @cancel="showTypePicker = false" />
    </van-popup>

    <van-popup v-model:show="showCategoryPicker" position="bottom">
      <van-picker :columns="categoryOptions" @confirm="onCategoryConfirm" @cancel="showCategoryPicker = false" />
    </van-popup>

    <van-popup v-model:show="showDatePicker" position="bottom">
      <van-date-picker :min-date="minDate" :max-date="maxDate" @confirm="onDateConfirm" @cancel="showDatePicker = false" />
    </van-popup>

    <van-popup v-model:show="showPaymentPicker" position="bottom">
      <van-picker :columns="paymentOptions" @confirm="onPaymentConfirm" @cancel="showPaymentPicker = false" />
    </van-popup>

    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { useFinancialStore } from '@/store/modules/financial'
import { addFinancialRecord } from '@/api/financial'
import { uploadImage } from '@/api/utils'
import dayjs from 'dayjs'

const router = useRouter()
const financialStore = useFinancialStore()
const formRef = ref()

const form = reactive({
  type: '',
  category_id: '',
  category_name: '',
  amount: '',
  record_date: dayjs().format('YYYY-MM-DD'),
  remark: '',
  payment_method: '',
  related_order: '',
  attachments: []
})

const showTypePicker = ref(false)
const showCategoryPicker = ref(false)
const showDatePicker = ref(false)
const showPaymentPicker = ref(false)
const fileList = ref([])
const loading = ref(false)

const typeOptions = ref([
  { text: '收入', value: 'income' },
  { text: '支出', value: 'expense' }
])

const categoryOptions = ref([])
const incomeCategories = ref([])
const expenseCategories = ref([])

const paymentOptions = ref([
  { text: '现金', value: '现金' },
  { text: '银行卡', value: '银行卡' },
  { text: '微信支付', value: '微信支付' },
  { text: '支付宝', value: '支付宝' },
  { text: '转账', value: '转账' },
  { text: '其他', value: '其他' }
])

const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

const typeDisplayText = computed(() => {
  if (!form.type) return ''
  return form.type === 'income' ? '收入' : '支出'
})

const getAmountDisplay = (type, amount) => {
  if (!amount) return ''
  const sign = type === 'income' ? '+' : '-'
  return `${sign}¥${parseFloat(amount).toFixed(2)}`
}

const getAmountClass = (type) => type === 'income' ? 'income-amount' : 'expense-amount'

const validateAmount = (value) => {
  const num = Number(value)
  return !isNaN(num) && num > 0
}

const loadCategories = async () => {
  loading.value = true
  try {
    await financialStore.loadCategories()
    
    const incomeData = financialStore.categories.income || {}
    incomeCategories.value = Object.keys(incomeData).map(key => ({
      text: incomeData[key],
      value: key
    }))
    
    const expenseData = financialStore.categories.expense || {}
    expenseCategories.value = Object.keys(expenseData).map(key => ({
      text: expenseData[key],
      value: key
    }))
    
    updateCategoryOptions()
  } catch (error) {
    console.error('加载分类数据失败:', error)
    showToast('加载分类数据失败')
  } finally {
    loading.value = false
  }
}

const updateCategoryOptions = () => {
  if (form.type === 'income') {
    categoryOptions.value = incomeCategories.value
  } else if (form.type === 'expense') {
    categoryOptions.value = expenseCategories.value
  } else {
    categoryOptions.value = []
  }
  
  if (form.category_id) {
    form.category_id = ''
    form.category_name = ''
  }
}

const beforeRead = (file) => {
  if (!['image/jpeg', 'image/png', 'image/jpg', 'image/gif'].includes(file.type)) {
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
  } catch (error) {
    console.error('文件上传失败:', error)
    file.status = 'failed'
    file.message = '上传失败'
    showToast('文件上传失败')
  }
}

const onOverSize = () => showToast('图片大小不能超过 5M')

const onTypeConfirm = (value) => {
  const selectedValue = value.selectedOptions[0].value
  form.type = selectedValue
  showTypePicker.value = false
  updateCategoryOptions()
}

const onCategoryConfirm = (value) => {
  const selectedOption = value.selectedOptions[0]
  form.category_id = selectedOption.value
  form.category_name = selectedOption.text
  showCategoryPicker.value = false
}

const onDateConfirm = (value) => {
  const selectedDate = new Date(
    value.selectedValues.year,
    value.selectedValues.month - 1,
    value.selectedValues.day
  )
  form.record_date = dayjs(selectedDate).format('YYYY-MM-DD')
  showDatePicker.value = false
}

const onPaymentConfirm = (value) => {
  form.payment_method = value.selectedOptions[0].value
  showPaymentPicker.value = false
}

const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '确定要取消新建收支记录吗？所有未保存的数据将会丢失。'
  }).then(() => router.back()).catch(() => {})
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    if (!form.type) {
      showToast('请选择收支类型')
      return
    }
    
    if (!form.category_name) {
      showToast('请选择收支分类')
      return
    }
    
    if (!form.amount || Number(form.amount) <= 0) {
      showToast('请输入有效的金额')
      return
    }

    if (!form.payment_method) {
      showToast('请选择支付方式')
      return
    }
    
    const submitData = {
      type: form.type === 'income' ? 1 : 2,
      category: form.category_name,
      amount: Number(form.amount),
      record_date: form.record_date,
      payment_method: form.payment_method,
      description: form.remark || '',
    }

    loading.value = true
    const result = await addFinancialRecord(submitData)
    showSuccessToast('收支记录创建成功')
    
    if (result && result.id) {
      router.push(`/financial/record/detail/${result.id}`)
    } else {
      router.back()
    }
    
  } catch (error) {
    console.error('保存失败:', error)
    if (error.message && error.message !== 'cancel') {
      if (error.message.includes('category')) {
        showToast('分类字段错误，请重新选择')
      } else if (error.message.includes('type')) {
        showToast('收支类型错误，请重新选择')
      } else {
        showToast(error.message || '保存失败，请检查表单数据')
      }
    }
  } finally {
    loading.value = false
  }
}

watch(() => form.type, updateCategoryOptions)

onMounted(() => {
  loadCategories()
})
</script>

<style lang="scss" scoped>
.financial-record-create-page {
  min-height: 100vh;
  background: #f5f5f5;
}

.form-container {
  padding: 12px;
}

.upload-section {
  padding: 16px;
  
  .upload-tips {
    font-size: 12px;
    color: #969799;
    margin-top: 8px;
  }
}

.income-amount {
  color: #ee0a24;
  font-weight: bold;
}

.expense-amount {
  color: #07c160;
  font-weight: bold;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

:deep(.van-cell-group__title) {
  font-weight: bold;
  color: #323233;
}

:deep(.van-field__label) {
  font-weight: 500;
}
</style>