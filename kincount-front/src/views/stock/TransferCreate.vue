<!-- src/views/stock/TransferCreate.vue -->
<template>
    <div class="transfer-create-page">
        <van-nav-bar title="新建调拨" left-text="取消" right-text="保存" left-arrow @click-left="handleCancel"
            @click-right="handleSubmit" />

        <van-form ref="formRef" @submit="handleSubmit" class="form-container">
            <!-- 基本信息 -->
            <van-cell-group title="基本信息">
                <van-field v-model="form.transfer_no" label="调拨单号" placeholder="系统自动生成" readonly />
                <van-field v-model="form.from_warehouse_name" label="调出仓库" placeholder="请选择调出仓库" readonly is-link
                    required :rules="[{ required: true, message: '请选择调出仓库' }]"
                    @click="showFromWarehousePicker = true" />
                <van-field v-model="form.to_warehouse_name" label="调入仓库" placeholder="请选择调入仓库" readonly is-link required
                    :rules="[{ required: true, message: '请选择调入仓库' }]" @click="showToWarehousePicker = true" />
                <van-field v-model="form.transfer_date" label="调拨日期" placeholder="请选择调拨日期" readonly is-link required
                    :rules="[{ required: true, message: '请选择调拨日期' }]" @click="showDatePicker = true" />
            </van-cell-group>

            <!-- 调拨说明 -->
            <van-cell-group title="调拨说明">
                <van-field v-model="form.remark" label="备注说明" type="textarea" placeholder="请输入调拨说明（可选）" rows="2"
                    autosize maxlength="200" show-word-limit />
            </van-cell-group>

            <!-- 调拨商品 -->
            <van-cell-group title="调拨商品">
                <div class="product-list">
                    <div class="product-item" v-for="(item, index) in form.items" :key="index">
                        <div class="product-header">
                            <span class="product-name">{{ item.product_name }}</span>
                            <van-button size="mini" type="danger" plain @click="removeProduct(index)">
                                删除
                            </van-button>
                        </div>
                        <div class="product-info">
                            <span>编号: {{ item.product_no }}</span>
                            <span>规格: {{ item.spec || '无' }}</span>
                            <span>当前库存: {{ item.current_stock }}{{ item.unit }}</span>
                        </div>
                        <div class="transfer-fields">
                            <van-field v-model="item.transfer_quantity" label="调拨数量" type="number" placeholder="请输入调拨数量"
                                required :rules="[
                                    { required: true, message: '请输入调拨数量' },
                                    { validator: validateQuantity, message: '调拨数量不能超过当前库存' }
                                ]">
                                <template #extra>{{ item.unit }}</template>
                            </van-field>
                        </div>
                    </div>

                    <!-- 添加商品按钮 -->
                    <div class="add-product">
                        <van-button block type="primary" plain icon="plus" @click="showProductPicker = true">
                            添加商品
                        </van-button>
                    </div>
                </div>
            </van-cell-group>
        </van-form>

        <!-- 调出仓库选择器 -->
        <van-popup v-model:show="showFromWarehousePicker" position="bottom">
            <van-picker :columns="warehouseOptions" @confirm="onFromWarehouseConfirm"
                @cancel="showFromWarehousePicker = false" />
        </van-popup>

        <!-- 调入仓库选择器 -->
        <van-popup v-model:show="showToWarehousePicker" position="bottom">
            <van-picker :columns="toWarehouseOptions" @confirm="onToWarehouseConfirm"
                @cancel="showToWarehousePicker = false" />
        </van-popup>

        <!-- 日期选择器 -->
        <van-popup v-model:show="showDatePicker" position="bottom">
            <van-date-picker :min-date="minDate" :max-date="maxDate" @confirm="onDateConfirm"
                @cancel="showDatePicker = false" />
        </van-popup>

        <!-- 商品选择器 -->
        <van-popup v-model:show="showProductPicker" position="bottom" :style="{ height: '70%' }">
            <div class="product-picker">
                <van-nav-bar title="选择商品" left-text="取消" right-text="确定" @click-left="showProductPicker = false"
                    @click-right="onProductConfirm" />
                <van-search v-model="productSearch" placeholder="搜索商品名称/编号" @search="searchProducts" />
                <van-checkbox-group v-model="selectedProducts">
                    <van-cell-group>
                        <van-cell v-for="product in productList" :key="product.id" clickable
                            @click="toggleProduct(product)">
                            <template #title>
                                <div class="product-title">
                                    <van-checkbox :name="product.id" />
                                    <span class="name">{{ product.name }}</span>
                                </div>
                            </template>
                            <template #label>
                                <div class="product-label">
                                    <span>编号: {{ product.product_no }}</span>
                                    <span>规格: {{ product.spec || '无' }}</span>
                                    <span>当前库存: {{ getProductStock(product) }}{{ product.unit }}</span>
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
import { getWarehouseOptions } from '@/api/warehouse'
import { getProductList } from '@/api/product'
import { addStockTransfer } from '@/api/stock'
import dayjs from 'dayjs'

const router = useRouter()
const formRef = ref()

// 表单数据
const form = reactive({
    transfer_no: '',
    from_warehouse_id: '',
    from_warehouse_name: '',
    to_warehouse_id: '',
    to_warehouse_name: '',
    transfer_date: dayjs().format('YYYY-MM-DD'),
    remark: '',
    items: []
})

// 选择器状态
const showFromWarehousePicker = ref(false)
const showToWarehousePicker = ref(false)
const showDatePicker = ref(false)
const showProductPicker = ref(false)

// 仓库选项
const warehouseOptions = ref([])

// 商品选择相关
const productSearch = ref('')
const productList = ref([])
const selectedProducts = ref([])

// 日期范围
const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

// 计算调入仓库选项（排除已选的调出仓库）
const toWarehouseOptions = computed(() => {
    if (!form.from_warehouse_id) {
        return warehouseOptions.value
    }
    return warehouseOptions.value.filter(warehouse =>
        warehouse.value !== form.from_warehouse_id
    )
})

// 加载仓库选项
const loadWarehouseOptions = async () => {
    try {
        const warehouses = await getWarehouseOptions()
        warehouseOptions.value = (warehouses?.data || warehouses || []).map(item => ({
            text: item.name,
            value: item.id
        }))
    } catch (error) {
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
        showToast('加载商品列表失败')
    }
}

// 获取商品在当前调出仓库的库存
const getProductStock = (product) => {
    // 这里需要根据实际情况获取商品在指定仓库的库存
    // 暂时返回总库存
    return product.total_stock || product.stock || 0
}

// 生成调拨单号
const generateTransferNo = async () => {
    try {
        const result = await generateNumber('transfer')
        // 处理不同的响应结构
        if (typeof result === 'string') {
            form.transfer_no = result
        } else if (result?.data) {
            form.transfer_no = result.data
        } else if (result?.number) {
            form.transfer_no = result.number
        } else if (result?.transfer_no) {
            form.transfer_no = result.transfer_no
        } else {
            // 如果无法获取，使用时间戳作为备选
            form.transfer_no = `TR${dayjs().format('YYYYMMDDHHmmss')}`
        }
    } catch (error) {
        console.error('生成单号失败:', error)
        // 如果生成失败，使用时间戳作为备选
        form.transfer_no = `TR${dayjs().format('YYYYMMDDHHmmss')}`
    }
}

// 验证调拨数量
const validateQuantity = (value) => {
    return Number(value) > 0
}

// 事件处理
const handleCancel = () => {
    showConfirmDialog({
        title: '确认取消',
        message: '确定要取消新建调拨吗？所有未保存的数据将会丢失。'
    }).then(() => {
        router.back()
    }).catch(() => {
        // 取消操作
    })
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    if (form.from_warehouse_id === form.to_warehouse_id) {
      showToast('调出仓库和调入仓库不能相同')
      return
    }
    
    if (form.items.length === 0) {
      showToast('请至少添加一个调拨商品')
      return
    }

    // 按照后端要求的数据结构提交
    const submitData = {
      from_warehouse_id: form.from_warehouse_id,
      to_warehouse_id: form.to_warehouse_id,
      remark: form.remark || '',
      items: form.items.map(item => ({
        product_id: item.product_id,
        transfer_quantity: Number(item.transfer_quantity) || 0
      }))
    }

    console.log('提交数据:', submitData) // 调试信息

    const result = await addStockTransfer(submitData)
    showToast('调拨单创建成功')
    
    // 根据后端返回结果跳转
    if (result.id) {
      router.push(`/stock/transfer/detail/${result.id}`)
    } else {
      router.back()
    }
  } catch (error) {
    if (error.message !== 'cancel') {
      console.error('保存失败:', error)
      showToast(error.message || '保存失败')
    }
  }
}

const onFromWarehouseConfirm = (value) => {
    form.from_warehouse_id = value.selectedOptions[0].value
    form.from_warehouse_name = value.selectedOptions[0].text
    showFromWarehousePicker.value = false
}

const onToWarehouseConfirm = (value) => {
    form.to_warehouse_id = value.selectedOptions[0].value
    form.to_warehouse_name = value.selectedOptions[0].text
    showToWarehousePicker.value = false
}

const onDateConfirm = (value) => {
    form.transfer_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
    showDatePicker.value = false
}

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

    // 添加选中的商品到调拨项，避免重复添加
    selectedProductData.forEach(product => {
        const exists = form.items.find(item => item.product_id === product.id)
        if (!exists) {
            form.items.push({
                product_id: product.id,
                product_name: product.name,
                product_no: product.product_no,
                spec: product.spec,
                unit: product.unit,
                current_stock: getProductStock(product),
                transfer_quantity: ''
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
    generateTransferNo()
    loadWarehouseOptions()
    loadProductList()
})
</script>

<style scoped lang="scss">
.transfer-create-page {
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
            flex-direction: column;
            gap: 4px;
            font-size: 12px;
            color: #969799;
            margin-bottom: 12px;
        }

        .transfer-fields {
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