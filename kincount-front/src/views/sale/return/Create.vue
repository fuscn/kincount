<template>
  <div class="sale-return-create-page">
    <van-nav-bar 
      title="新建退货"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <!-- 退货信息 -->
      <van-cell-group title="退货信息">
        <van-field
          v-model="form.return_no"
          label="退货单号"
          placeholder="系统自动生成"
          readonly
        />
        <van-field
          v-model="form.order_no"
          label="销售订单"
          placeholder="请选择销售订单"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择销售订单' }]"
          @click="showOrderPicker = true"
        />
        <van-field
          v-model="form.warehouse_name"
          label="退货仓库"
          placeholder="请选择仓库"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择仓库' }]"
          @click="showWarehousePicker = true"
        />
        <van-field
          v-model="form.return_date"
          label="退货日期"
          placeholder="请选择退货日期"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择退货日期' }]"
          @click="showDatePicker = true"
        />
        <van-field
          v-model="form.return_type"
          label="退货原因"
          placeholder="请选择退货原因"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择退货原因' }]"
          @click="showReasonPicker = true"
        />
      </van-cell-group>

      <!-- 退货说明 -->
      <van-cell-group title="退货说明">
        <van-field
          v-model="form.remark"
          label="备注说明"
          type="textarea"
          placeholder="请输入退货说明（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
      </van-cell-group>

      <!-- 退货商品 -->
      <van-cell-group title="退货商品">
        <div class="product-list">
          <div class="product-item" v-for="(item, index) in form.items" :key="index">
            <div class="product-header">
              <span class="product-name">{{ item.product_name }}</span>
              <van-button 
                size="mini" 
                type="danger" 
                plain 
                @click="removeProduct(index)"
              >
                删除
              </van-button>
            </div>
            <div class="product-info">
              <span>编号: {{ item.product_no }}</span>
              <span>规格: {{ item.spec || '无' }}</span>
              <span>原销售: {{ item.sale_quantity }}{{ item.unit }}</span>
            </div>
            <div class="return-fields">
              <van-field
                v-model="item.return_quantity"
                label="退货数量"
                type="number"
                placeholder="请输入退货数量"
                required
                :rules="[
                  { required: true, message: '请输入退货数量' },
                  { validator: validateQuantity, message: '退货数量不能超过销售数量' }
                ]"
              >
                <template #extra>{{ item.unit }}</template>
              </van-field>
              <van-field
                v-model="item.unit_price"
                label="退货单价"
                type="number"
                placeholder="请输入退货单价"
                required
                :rules="[
                  { required: true, message: '请输入退货单价' },
                  { validator: validatePrice, message: '退货单价必须大于0' }
                ]"
              >
                <template #extra>元</template>
              </van-field>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <van-empty
          v-if="form.items.length === 0"
          description="请先选择销售订单"
          image="search"
        />
      </van-cell-group>
    </van-form>

    <!-- 各种选择器 -->
    <!-- ... 类似销售出库的选择器实现 -->
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog
} from 'vant'
import { generateNumber } from '@/api/utils'
import { getWarehouseOptions } from '@/api/warehouse'
import { getSaleOrderList, getSaleOrderDetail, addSaleReturn } from '@/api/sale'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const formRef = ref()

// 表单数据
const form = reactive({
  return_no: '',
  order_id: '',
  order_no: '',
  warehouse_id: '',
  warehouse_name: '',
  return_date: dayjs().format('YYYY-MM-DD'),
  return_type: '',
  remark: '',
  items: []
})

// 退货原因选项
const returnReasonOptions = [
  { text: '质量问题', value: 'quality' },
  { text: '客户原因', value: 'customer' },
  { text: '发错货', value: 'wrong_delivery' },
  { text: '其他', value: 'other' }
]

// 核心逻辑与销售出库类似，主要区别：
// 1. 选择已完成的销售订单
// 2. 退货数量不能超过销售数量
// 3. 退货会减少库存

const validateQuantity = (value, item) => {
  const quantity = Number(value) || 0
  const saleQuantity = Number(item.sale_quantity) || 0
  return quantity > 0 && quantity <= saleQuantity
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    if (!form.order_id) {
      showToast('请选择销售订单')
      return
    }
    
    if (form.items.length === 0) {
      showToast('请添加退货商品')
      return
    }

    // 构建提交数据
    const submitData = {
      order_id: Number(form.order_id),
      warehouse_id: Number(form.warehouse_id),
      return_date: form.return_date,
      return_type: form.return_type,
      remark: form.remark || '',
      items: form.items.map(item => ({
        product_id: Number(item.product_id),
        return_quantity: Number(item.return_quantity),
        unit_price: Number(item.unit_price)
      }))
    }

    const result = await addSaleReturn(submitData)
    showToast('退货单创建成功')
    
    if (result.id) {
      router.push(`/sale/return/detail/${result.id}`)
    } else {
      router.back()
    }
  } catch (error) {
    console.error('保存失败:', error)
    if (error.message !== 'cancel') {
      showToast(error.message || '保存失败')
    }
  }
}

// ... 其他逻辑与销售出库类似
</script>