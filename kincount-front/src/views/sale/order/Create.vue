<template>
  <div class="sale-order-create-page">
    <van-nav-bar 
      title="新建销售订单"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <!-- 客户信息 -->
      <van-cell-group title="客户信息">
        <van-field
          v-model="form.customer_name"
          label="客户"
          placeholder="请选择客户"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择客户' }]"
          @click="showCustomerPicker = true"
        />
        <van-field
          v-model="form.warehouse_name"
          label="发货仓库"
          placeholder="请选择仓库"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择仓库' }]"
          @click="showWarehousePicker = true"
        />
        <van-field
          v-model="form.order_date"
          label="订单日期"
          placeholder="请选择订单日期"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择订单日期' }]"
          @click="showDatePicker = true"
        />
        <van-field
          v-model="form.expected_date"
          label="期望交货日期"
          placeholder="请选择交货日期"
          readonly
          is-link
          @click="showDeliveryDatePicker = true"
        />
      </van-cell-group>

      <!-- 订单信息 -->
      <van-cell-group title="订单信息">
        <van-field
          v-model="form.order_no"
          label="订单编号"
          placeholder="系统自动生成"
          readonly
        />
        <van-field
          v-model="form.remark"
          label="备注说明"
          type="textarea"
          placeholder="请输入订单备注（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
      </van-cell-group>

      <!-- 订单商品 -->
      <van-cell-group title="订单商品">
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
              <span>编号：{{ item.product_no }}</span>
              <span>规格：{{ item.spec || '无' }}</span>
              <span>库存：{{ item.stock }}{{ item.unit }}</span>
            </div>
            <div class="order-fields">
              <van-field
                v-model="item.quantity"
                label="销售数量"
                type="number"
                placeholder="请输入销售数量"
                required
                :rules="[
                  { required: true, message: '请输入销售数量' },
                  { validator: () => validateQuantity(item), message: '销售数量必须大于0' }
                ]"
              >
                <template #extra>{{ item.unit }}</template>
              </van-field>
              <van-field
                v-model="item.price"
                label="销售单价"
                type="number"
                placeholder="请输入销售单价"
                required
                :rules="[
                  { required: true, message: '请输入销售单价' },
                  { validator: () => validatePrice(item), message: '销售单价必须大于0' }
                ]"
              >
                <template #extra>元</template>
              </van-field>
              <van-field
                v-model="item.total_amount"
                label="金额"
                type="number"
                placeholder="自动计算"
                readonly
              >
                <template #extra>元</template>
              </van-field>
            </div>
          </div>

          <!-- 添加商品按钮 -->
          <div class="add-product">
            <van-button 
              block 
              type="primary" 
              plain 
              icon="plus" 
              @click="showProductPicker = true"
            >
              添加商品
            </van-button>
          </div>
        </div>
      </van-cell-group>

      <!-- 订单汇总 -->
      <van-cell-group title="订单汇总" v-if="form.items.length > 0">
        <van-cell title="商品数量" :value="totalQuantity" />
        <van-cell title="订单金额" :value="`¥${totalAmount}`" />
        <van-cell title="优惠金额" :value="`¥${form.discount_amount || 0}`" />
        <van-cell title="实付金额" :value="`¥${actualAmount}`" class="total-amount" />
      </van-cell-group>
    </van-form>

    <!-- 客户选择器 -->
    <van-popup 
      v-model:show="showCustomerPicker" 
      position="bottom" 
      :style="{ height: '60%' }"
    >
      <div class="customer-picker">
        <van-nav-bar
          title="选择客户"
          left-text="取消"
          right-text="确定"
          @click-left="showCustomerPicker = false"
          @click-right="onCustomerConfirm"
        />
        <van-search
          v-model="customerSearch"
          placeholder="搜索客户名称"
          @search="searchCustomers"
        />
        <van-radio-group v-model="selectedCustomer">
          <van-cell-group>
            <van-cell
              v-for="customer in filteredCustomers"
              :key="customer.id"
              clickable
              @click="selectedCustomer = customer.id"
            >
              <template #title>
                <div class="customer-title">
                  <van-radio :name="customer.id" />
                  <span class="customer-name">{{ customer.name }}</span>
                </div>
              </template>
              <template #label>
                <div class="customer-label">
                  <span>联系人：{{ customer.contact_person }}</span>
                  <span>电话：{{ customer.phone }}</span>
                  <span>类型：{{ customer.type === 1 ? '个人' : '企业' }}</span>
                </div>
              </template>
            </van-cell>
          </van-cell-group>
        </van-radio-group>
      </div>
    </van-popup>

    <!-- 仓库选择器 -->
    <van-popup 
      v-model:show="showWarehousePicker" 
      position="bottom" 
      :style="{ height: '60%' }"
    >
      <div class="warehouse-picker">
        <van-nav-bar
          title="选择仓库"
          left-text="取消"
          right-text="确定"
          @click-left="showWarehousePicker = false"
          @click-right="onWarehouseConfirm"
        />
        <van-search
          v-model="warehouseSearch"
          placeholder="搜索仓库名称"
          @search="searchWarehouses"
        />
        <van-radio-group v-model="selectedWarehouse">
          <van-cell-group>
            <van-cell
              v-for="warehouse in filteredWarehouses"
              :key="warehouse.id"
              clickable
              @click="selectedWarehouse = warehouse.id"
            >
              <template #title>
                <div class="warehouse-title">
                  <van-radio :name="warehouse.id" />
                  <span class="warehouse-name">{{ warehouse.name }}</span>
                </div>
              </template>
              <template #label>
                <div class="warehouse-label">
                  <span>联系人：{{ warehouse.contact_person || '无' }}</span>
                  <span>电话：{{ warehouse.phone || '无' }}</span>
                  <span>地址：{{ warehouse.address || '无' }}</span>
                </div>
              </template>
            </van-cell>
          </van-cell-group>
        </van-radio-group>
      </div>
    </van-popup>

    <!-- 日期选择器 -->
    <van-popup v-model:show="showDatePicker" position="bottom">
      <van-date-picker
        :min-date="minDate"
        :max-date="maxDate"
        @confirm="onDateConfirm"
        @cancel="showDatePicker = false"
      />
    </van-popup>

    <!-- 交货日期选择器 -->
    <van-popup v-model:show="showDeliveryDatePicker" position="bottom">
      <van-date-picker
        :min-date="minDate"
        :max-date="maxDeliveryDate"
        @confirm="onDeliveryDateConfirm"
        @cancel="showDeliveryDatePicker = false"
      />
    </van-popup>

    <!-- 商品选择器 -->
    <van-popup 
      v-model:show="showProductPicker" 
      position="bottom" 
      :style="{ height: '70%' }"
    >
      <div class="product-picker">
        <van-nav-bar
          title="选择商品"
          left-text="取消"
          right-text="确定"
          @click-left="showProductPicker = false"
          @click-right="onProductConfirm"
        />
        <van-search
          v-model="productSearch"
          placeholder="搜索商品名称/编号"
          @search="searchProducts"
        />
        <van-checkbox-group v-model="selectedProducts">
          <van-cell-group>
            <van-cell
              v-for="product in productList"
              :key="product.id"
              clickable
              @click="toggleProduct(product)"
            >
              <template #title>
                <div class="product-title">
                  <van-checkbox :name="product.id" />
                  <span class="name">{{ product.name }}</span>
                </div>
              </template>
              <template #label>
                <div class="product-label">
                  <span>编号：{{ product.product_no }}</span>
                  <span>规格：{{ product.spec || '无' }}</span>
                  <span>库存：{{ product.total_stock || 0 }}{{ product.unit }}</span>
                  <span>售价：¥{{ product.sale_price }}</span>
                </div>
              </template>
            </van-cell>
          </van-cell-group>
        </van-checkbox-group>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog
} from 'vant'
import { generateNumber } from '@/api/utils'
import { getCustomerList } from '@/api/customer'
import { getWarehouseList } from '@/api/warehouse'
import { getProductList } from '@/api/product'
import { addSaleOrder } from '@/api/sale'
import dayjs from 'dayjs'

const router = useRouter()
const formRef = ref()

// 表单数据
const form = reactive({
  order_no: '',
  customer_id: '',
  customer_name: '',
  warehouse_id: '',
  warehouse_name: '',
  order_date: dayjs().format('YYYY-MM-DD'),
  expected_date: '',
  remark: '',
  discount_amount: 0,
  items: []
})

// 客户选择器相关
const showCustomerPicker = ref(false)
const customerSearch = ref('')
const customerList = ref([])
const selectedCustomer = ref('')

// 仓库选择器相关
const showWarehousePicker = ref(false)
const warehouseSearch = ref('')
const warehouseList = ref([])
const selectedWarehouse = ref('')

// 其他选择器状态
const showDatePicker = ref(false)
const showDeliveryDatePicker = ref(false)
const showProductPicker = ref(false)

// 商品选择器相关
const productSearch = ref('')
const productList = ref([])
const selectedProducts = ref([])

// 日期范围
const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)
const maxDeliveryDate = new Date(2031, 11, 31)

// 计算属性
const totalQuantity = computed(() => {
  return form.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0)
})

const totalAmount = computed(() => {
  return form.items.reduce((sum, item) => {
    const quantity = Number(item.quantity) || 0
    const price = Number(item.price) || 0
    return sum + (quantity * price)
  }, 0).toFixed(2)
})

const actualAmount = computed(() => {
  const total = parseFloat(totalAmount.value)
  const discount = Number(form.discount_amount) || 0
  return (total - discount).toFixed(2)
})

// 过滤后的客户列表
const filteredCustomers = computed(() => {
  if (!customerSearch.value) {
    return customerList.value
  }
  const search = customerSearch.value.toLowerCase()
  return customerList.value.filter(customer => 
    customer.name.toLowerCase().includes(search) ||
    (customer.contact_person && customer.contact_person.toLowerCase().includes(search)) ||
    (customer.phone && customer.phone.includes(search))
  )
})

// 过滤后的仓库列表
const filteredWarehouses = computed(() => {
  if (!warehouseSearch.value) {
    return warehouseList.value
  }
  const search = warehouseSearch.value.toLowerCase()
  return warehouseList.value.filter(warehouse => 
    warehouse.name.toLowerCase().includes(search) ||
    (warehouse.contact_person && warehouse.contact_person.toLowerCase().includes(search)) ||
    (warehouse.address && warehouse.address.toLowerCase().includes(search))
  )
})

// 监听商品数量单价变化，计算金额
watch(() => form.items, (newItems) => {
  newItems.forEach(item => {
    const quantity = Number(item.quantity) || 0
    const price = Number(item.price) || 0
    item.total_amount = (quantity * price).toFixed(2)
  })
}, { deep: true })

// 加载客户列表
const loadCustomerList = async () => {
  try {
    const customers = await getCustomerList({ page: 1, limit: 100 })
    console.log('客户列表响应:', customers)
    
    // 处理不同的响应结构
    let customerData = []
    if (customers?.code === 200 && customers.data) {
      customerData = customers.data.list || customers.data || []
    } else {
      customerData = customers?.list || customers || []
    }
    
    customerList.value = customerData
    console.log('处理后的客户数据:', customerList.value)
  } catch (error) {
    console.error('加载客户列表失败:', error)
    showToast('加载客户列表失败')
  }
}

// 加载仓库列表
const loadWarehouseList = async () => {
  try {
    const warehouses = await getWarehouseList({ page: 1, limit: 100 })
    console.log('仓库列表响应:', warehouses)
    
    // 处理不同的响应结构
    let warehouseData = []
    if (warehouses?.code === 200 && warehouses.data) {
      warehouseData = warehouses.data.list || warehouses.data || []
    } else {
      warehouseData = warehouses?.list || warehouses || []
    }
    
    warehouseList.value = warehouseData
    console.log('处理后的仓库数据:', warehouseList.value)
  } catch (error) {
    console.error('加载仓库列表失败:', error)
    showToast('加载仓库列表失败')
  }
}

// 加载商品列表
const loadProductList = async () => {
  try {
    const params = {
      page: 1,
      limit: 50,
      keyword: productSearch.value
    }
    const response = await getProductList(params)
    
    let products = []
    if (response?.code === 200 && response.data) {
      products = response.data.list || response.data || []
    } else {
      products = response?.list || response || []
    }
    
    productList.value = products
  } catch (error) {
    console.error('加载商品列表失败:', error)
    showToast('加载商品列表失败')
  }
}

// 生成订单编号
const generateOrderNo = async () => {
  try {
    const result = await generateNumber('sale_order')
    // 处理不同的响应结构
    if (typeof result === 'string') {
      form.order_no = result
    } else if (result?.data) {
      form.order_no = result.data
    } else if (result?.number) {
      form.order_no = result.number
    } else if (result?.order_no) {
      form.order_no = result.order_no
    } else {
      form.order_no = `SO${dayjs().format('YYYYMMDDHHmmss')}`
    }
  } catch (error) {
    form.order_no = `SO${dayjs().format('YYYYMMDDHHmmss')}`
  }
}

// 验证函数
const validateQuantity = (item) => {
  return Number(item.quantity) > 0
}

const validatePrice = (item) => {
  return Number(item.price) > 0
}

// 事件处理
const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '确定要取消新建销售订单吗？所有未保存的数据将会丢失。'
  }).then(() => {
    router.back()
  }).catch(() => {
    // 取消操作
  })
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    if (!form.warehouse_id) {
      showToast('请选择发货仓库')
      return
    }
    
    if (form.items.length === 0) {
      showToast('请至少添加一个销售商品')
      return
    }

    // 验证所有商品的数量和单价
    for (const item of form.items) {
      if (!validateQuantity(item) || !validatePrice(item)) {
        showToast(`请检查商品"${item.product_name}"的数量和单价`)
        return
      }
    }

    // 构建提交数据 - 根据后端要求调整字段名
    const submitData = {
      customer_id: Number(form.customer_id),
      warehouse_id: Number(form.warehouse_id),
      remark: form.remark || '',
      expected_date: form.expected_date || form.order_date,
      items: form.items.map(item => ({
        product_id: Number(item.product_id),
        quantity: Number(item.quantity),
        price: Number(item.price)  // 注意：后端要求字段名为 price，不是 unit_price
      }))
    }

    console.log('提交的销售订单数据:', submitData)

    const result = await addSaleOrder(submitData)
    showToast('销售订单创建成功')
    
    if (result.id) {
      router.push(`/sale/order/detail/${result.id}`)
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

// 客户选择相关
const onCustomerConfirm = () => {
  if (!selectedCustomer.value) {
    showToast('请选择客户')
    return
  }

  const customer = customerList.value.find(c => c.id === selectedCustomer.value)
  if (customer) {
    form.customer_id = customer.id
    form.customer_name = customer.name
    showCustomerPicker.value = false
    selectedCustomer.value = ''
    customerSearch.value = ''
  }
}

const searchCustomers = () => {
  // 搜索逻辑已经在 computed 中处理
  console.log('搜索客户:', customerSearch.value)
}

// 仓库选择相关
const onWarehouseConfirm = () => {
  if (!selectedWarehouse.value) {
    showToast('请选择仓库')
    return
  }

  const warehouse = warehouseList.value.find(w => w.id === selectedWarehouse.value)
  if (warehouse) {
    form.warehouse_id = warehouse.id
    form.warehouse_name = warehouse.name
    showWarehousePicker.value = false
    selectedWarehouse.value = ''
    warehouseSearch.value = ''
  }
}

const searchWarehouses = () => {
  // 搜索逻辑已经在 computed 中处理
  console.log('搜索仓库:', warehouseSearch.value)
}

// 日期选择相关
const onDateConfirm = (value) => {
  form.order_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showDatePicker.value = false
}

const onDeliveryDateConfirm = (value) => {
  form.expected_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showDeliveryDatePicker.value = false
}

// 商品选择相关
const toggleProduct = (product) => {
  const index = selectedProducts.value.indexOf(product.id)
  if (index > -1) {
    selectedProducts.value.splice(index, 1)
  } else {
    selectedProducts.value.push(product.id)
  }
}

const onProductConfirm = () => {
  const selectedIds = [...selectedProducts.value]
  const selectedProductData = productList.value.filter(product => 
    selectedIds.includes(product.id)
  )

  // 添加选中的商品到订单项，避免重复添加
  selectedProductData.forEach(product => {
    const exists = form.items.find(item => item.product_id === product.id)
    if (!exists) {
      form.items.push({
        product_id: product.id,
        product_name: product.name,
        product_no: product.product_no,
        spec: product.spec,
        unit: product.unit,
        stock: product.total_stock || 0,
        quantity: '',
        price: product.sale_price || 0,  // 注意：改为 price 字段
        total_amount: '0.00'
      })
    }
  })

  selectedProducts.value = []
  showProductPicker.value = false
  productSearch.value = ''
}

const removeProduct = (index) => {
  form.items.splice(index, 1)
}

const searchProducts = () => {
  loadProductList()
}

onMounted(() => {
  generateOrderNo()
  loadCustomerList()
  loadWarehouseList()
  loadProductList()
})
</script>

<style scoped lang="scss">
.sale-order-create-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.form-container {
  padding: 16px;
}

.product-list {
  .product-item {
    background: white;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    
    .product-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      
      .product-name {
        font-weight: 500;
        font-size: 14px;
      }
    }
    
    .product-info {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 12px;
      color: #969799;
      margin-bottom: 12px;
    }
    
    .order-fields {
      .van-field {
        padding: 8px 0;
        
        :deep(.van-field__label) {
          width: 70px;
        }
      }
    }
  }
  
  .add-product {
    margin-top: 12px;
  }
}

.total-amount {
  :deep(.van-cell__value) {
    color: #ee0a24;
    font-weight: bold;
    font-size: 16px;
  }
}

.customer-picker,
.warehouse-picker {
  height: 100%;
  display: flex;
  flex-direction: column;
  
  .van-search {
    flex-shrink: 0;
  }
  
  .van-cell-group {
    flex: 1;
    overflow-y: auto;
  }
  
  .customer-title,
  .warehouse-title {
    display: flex;
    align-items: center;
    gap: 8px;
    
    .customer-name,
    .warehouse-name {
      font-weight: 500;
    }
  }
  
  .customer-label,
  .warehouse-label {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 12px;
    color: #969799;
  }
}

.product-picker {
  height: 100%;
  display: flex;
  flex-direction: column;
  
  .van-search {
    flex-shrink: 0;
  }
  
  .van-cell-group {
    flex: 1;
    overflow-y: auto;
  }
  
  .product-title {
    display: flex;
    align-items: center;
    gap: 8px;
    
    .name {
      font-weight: 500;
    }
  }
  
  .product-label {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 12px;
    color: #969799;
  }
}
</style>