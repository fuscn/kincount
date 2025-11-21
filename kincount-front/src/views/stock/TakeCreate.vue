<!-- src/views/stock/TakeCreate.vue -->
<template>
    <div class="take-create-page">
        <van-nav-bar title="新建盘点" left-text="取消" right-text="保存" left-arrow @click-left="handleCancel"
            @click-right="handleSubmit" />

        <van-form ref="formRef" @submit="handleSubmit" class="form-container">
            <!-- 基本信息 -->
            <van-cell-group title="基本信息">
                <van-field v-model="form.take_no" label="盘点单号" placeholder="系统自动生成" readonly />
                <van-field v-model="form.warehouse_name" label="盘点仓库" placeholder="请选择仓库" readonly is-link required
                    :rules="[{ required: true, message: '请选择仓库' }]" @click="showWarehousePicker = true" />
                <van-field v-model="form.take_date" label="盘点日期" placeholder="请选择盘点日期" readonly is-link required
                    :rules="[{ required: true, message: '请选择盘点日期' }]" @click="showDatePicker = true" />
            </van-cell-group>

            <!-- 盘点说明 -->
            <van-cell-group title="盘点说明">
                <van-field v-model="form.remark" label="备注说明" type="textarea" placeholder="请输入盘点说明（可选）" rows="2"
                    autosize maxlength="200" show-word-limit />
            </van-cell-group>

            <!-- 盘点商品 -->
            <van-cell-group title="盘点商品">
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
                        </div>
                        <div class="stock-fields">
                            <van-field v-model="item.current_stock" label="当前库存" type="number" placeholder="0" readonly>
                                <template #extra>{{ item.unit }}</template>
                            </van-field>
                            <van-field v-model="item.actual_stock" label="实际库存" type="number" placeholder="请输入实际数量"
                                required :rules="[{ required: true, message: '请输入实际数量' }]">
                                <template #extra>{{ item.unit }}</template>
                            </van-field>
                            <van-field v-model="item.difference" label="差异数量" type="number" placeholder="自动计算" readonly>
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

        <!-- 仓库选择器 -->
        <van-popup v-model:show="showWarehousePicker" position="bottom">
            <van-picker :columns="warehouseOptions" @confirm="onWarehouseConfirm"
                @cancel="showWarehousePicker = false" />
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
                                    <span>当前库存: {{ product.stock || 0 }}{{ product.unit }}</span>
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
import { addStockTake } from '@/api/stock'
import dayjs from 'dayjs'

const router = useRouter()
const formRef = ref()

// 表单数据
const form = reactive({
    take_no: '',
    warehouse_id: '',
    warehouse_name: '',
    take_date: dayjs().format('YYYY-MM-DD'),
    remark: '',
    items: []
})

// 选择器状态
const showWarehousePicker = ref(false)
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

// 计算差异数量
watch(() => form.items, (newItems) => {
    newItems.forEach(item => {
        const current = Number(item.current_stock) || 0
        const actual = Number(item.actual_stock) || 0
        item.difference = actual - current
    })
}, { deep: true })

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

// 生成盘点单号
const generateTakeNo = async () => {
    try {
        const result = await generateNumber('take')
        // 处理不同的响应结构
        if (typeof result === 'string') {
            form.take_no = result
        } else if (result?.data) {
            form.take_no = result.data
        } else if (result?.number) {
            form.take_no = result.number
        } else if (result?.take_no) {
            form.take_no = result.take_no
        } else {
            // 如果无法获取，使用时间戳作为备选
            form.take_no = `TAKE${dayjs().format('YYYYMMDDHHmmss')}`
        }
    } catch (error) {
        console.error('生成单号失败:', error)
        // 如果生成失败，使用时间戳作为备选
        form.take_no = `TAKE${dayjs().format('YYYYMMDDHHmmss')}`
    }
}

// 事件处理
const handleCancel = () => {
    showConfirmDialog({
        title: '确认取消',
        message: '确定要取消新建盘点吗？所有未保存的数据将会丢失。'
    }).then(() => {
        router.back()
    }).catch(() => {
        // 取消操作
    })
}

const handleSubmit = async () => {
    try {
        await formRef.value.validate()

        if (form.items.length === 0) {
            showToast('请至少添加一个盘点商品')
            return
        }

        // 验证实际库存是否填写
        const invalidItem = form.items.find(item => item.actual_stock === '' || item.actual_stock == null)
        if (invalidItem) {
            showToast(`请填写商品"${invalidItem.product_name}"的实际库存`)
            return
        }

        // 按照后端要求的数据结构提交
        const submitData = {
            warehouse_id: form.warehouse_id,
            remark: form.remark || '',
            items: form.items.map(item => ({
                product_id: item.product_id,
                actual_quantity: Number(item.actual_stock) || 0,
                // 如果需要原因字段，可以添加
                // reason: item.reason || ''
            }))
        }

        console.log('提交数据:', submitData) // 调试信息

        const result = await addStockTake(submitData)
        showToast('盘点单创建成功')

        // 根据后端返回结果跳转
        if (result.id) {
            router.push(`/stock/take/detail/${result.id}`)
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

const onWarehouseConfirm = (value) => {
    form.warehouse_id = value.selectedOptions[0].value
    form.warehouse_name = value.selectedOptions[0].text
    showWarehousePicker.value = false
}

const onDateConfirm = (value) => {
    form.take_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
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

    // 添加选中的商品到盘点项，避免重复添加
    selectedProductData.forEach(product => {
        const exists = form.items.find(item => item.product_id === product.id)
        if (!exists) {
            form.items.push({
                product_id: product.id,
                product_name: product.name,
                product_no: product.product_no,
                spec: product.spec,
                unit: product.unit,
                current_stock: product.total_stock || product.stock || 0,
                actual_stock: product.total_stock || product.stock || 0,
                difference: 0
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
    generateTakeNo()
    loadWarehouseOptions()
    loadProductList()
})
</script>

<style scoped lang="scss">
.take-create-page {
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
            gap: 12px;
            font-size: 12px;
            color: #969799;
            margin-bottom: 12px;
        }

        .stock-fields {
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