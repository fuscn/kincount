<!-- src/views/stock/TransferCreate.vue -->
<template>
    <div class="transfer-create-page">
        <van-nav-bar
            title="新建调拨"
            left-text="取消"
            right-text="保存"
            left-arrow
            @click-left="handleCancel"
            @click-right="handleSubmit"
        />

        <van-form ref="formRef" @submit="handleSubmit" class="form-container">
            <!-- 基本信息区域 -->
            <section class="form-section">
                <van-cell-group inset title="基本信息">
                    <!-- 调出仓库选择 -->
                    <van-field
                        readonly
                        clickable
                        label="调出仓库"
                        :placeholder="form.from_warehouse_name || '请选择调出仓库'"
                        @click="openFromWarehouseSelect"
                    >
                        <template #input>
                            {{ form.from_warehouse_name || '' }}
                        </template>
                        <template #right-icon>
                            <van-icon name="arrow" />
                        </template>
                    </van-field>
                    
                    <!-- 调入仓库选择 -->
                    <van-field
                        readonly
                        clickable
                        label="调入仓库"
                        :placeholder="form.to_warehouse_name || '请选择调入仓库'"
                        @click="openToWarehouseSelect"
                    >
                        <template #input>
                            {{ form.to_warehouse_name || '' }}
                        </template>
                        <template #right-icon>
                            <van-icon name="arrow" />
                        </template>
                    </van-field>
                    <van-field
                        v-model="form.transfer_date"
                        label="调拨日期"
                        placeholder="请选择调拨日期"
                        readonly
                        is-link
                        @click="showDatePicker = true"
                    />
                </van-cell-group>
            </section>

            <!-- 调拨说明区域 -->
            <section class="form-section">
                <van-cell-group inset title="调拨说明">
                    <van-field
                        v-model="form.remark"
                        label="备注说明"
                        type="textarea"
                        placeholder="请输入调拨说明（可选）"
                        rows="3"
                        autosize
                        maxlength="200"
                        show-word-limit
                    />
                </van-cell-group>
            </section>

            <!-- 调拨商品区域 -->
            <section class="form-section">
                <div class="sku-section">
                    <div class="section-title">
                        <span>调拨商品明细</span>
                        <van-button size="small" type="primary" @click="showProductPicker = true" icon="plus">
                            添加商品
                        </van-button>
                    </div>
                    
                    <!-- 调拨商品列表 -->
                    <div class="sku-stock-take-list">
                        <van-swipe-cell v-for="(item, index) in form.items" :key="index" :right-width="60">
                            <div class="sku-take-card">
                                <!-- 信息区域 -->
                                <div class="sku-info">
                                    <!-- 第一行：商品名称 + SKU编号 -->
                                    <div class="row product-row">
                                        <div class="product-name">{{ item.product_name }}</div>
                                        <div class="sku-code">{{ item.product_no }}</div>
                                    </div>
                                    
                                    <!-- 第二行：规格 + 数量编辑 -->
                                    <div class="row sku-row">
                                        <div class="sku-spec">{{ item.spec }}</div>
                                        <div class="quantity-input">
                                            <span class="stock-label">数量:</span>
                                            <van-field 
                                                v-model="item.quantity" 
                                                type="number" 
                                                placeholder="请输入数量" 
                                                class="quantity-field"
                                                :error-message="item.quantityError"
                                                @blur="validateQuantity(item, index)"
                                            />
                                            <span class="unit">{{ item.unit || '个' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 删除按钮 -->
                            <template #right>
                                <van-button square type="danger" text="删除" class="delete-btn" @click="removeProduct(index)" />
                            </template>
                        </van-swipe-cell>
                        
                        <!-- 空状态 -->
                        <van-empty 
                            v-if="form.items.length === 0" 
                            description="暂无调拨商品" 
                            image="default"
                        />
                    </div>
                </div>
            </section>
        </van-form>

        <!-- 商品选择弹窗 -->
        <van-popup v-model:show="showProductPicker" position="bottom" :style="{ height: '80%' }">
            <div class="product-picker-container">
                <van-nav-bar 
                    title="选择调拨商品" 
                    left-text="取消" 
                    right-text="确定"
                    @click-left="showProductPicker = false"
                    @click-right="handleProductConfirm"
                />
                <div class="picker-content">
                    <SkuStockList 
                        mode="list"
                        :warehouse-id="form.from_warehouse_id"
                        :selectable="true"
                        :selected-ids="selectedSkuIds"
                        @click-card="handleSkuSelect"
                    />
                </div>
            </div>
        </van-popup>

        <!-- 日期选择弹窗 -->
        <van-popup v-model:show="showDatePicker" position="bottom">
            <van-date-picker
                v-model="selectedDateArray"
                :min-date="minDate"
                :max-date="maxDate"
                @confirm="onDateConfirm"
                @cancel="showDatePicker = false"
            />
        </van-popup>
        
        <!-- 调出仓库选择器组件 -->
        <WarehouseSelect
            ref="fromWarehouseSelectRef"
            v-model="form.from_warehouse_id"
            :popup-title="'选择调出仓库'"
            :search-placeholder="'搜索仓库'"
            :only-enabled="true"
            :hide-trigger="true"
            @change="onFromWarehouseSelectChange"
            @confirm="onFromWarehouseConfirm"
        />
        
        <!-- 调入仓库选择器组件 -->
        <WarehouseSelect
            ref="toWarehouseSelectRef"
            v-model="form.to_warehouse_id"
            :popup-title="'选择调入仓库'"
            :search-placeholder="'搜索仓库'"
            :only-enabled="true"
            :hide-trigger="true"
            :exclude-ids="toWarehouseExcludeIds"
            @change="onToWarehouseSelectChange"
            @confirm="onToWarehouseConfirm"
        />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'
import { addStockTransfer } from '@/api/stock'
import dayjs from 'dayjs'

// 业务组件
import WarehouseSelect from '@/components/business/WarehouseSelect.vue'
import SkuStockList from '@/components/business/SkuStockList.vue'

const router = useRouter()
const formRef = ref()
const fromWarehouseSelectRef = ref()
const toWarehouseSelectRef = ref()

// 表单数据
const form = reactive({
    from_warehouse_id: '',
    from_warehouse_name: '',
    to_warehouse_id: '',
    to_warehouse_name: '',
    transfer_date: dayjs().format('YYYY-MM-DD'),
    remark: '',
    items: []
})

// 弹窗状态
const showProductPicker = ref(false)
const showDatePicker = ref(false)

// 日期选择
const selectedDate = ref(new Date())
const selectedDateArray = ref([new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate()])
const minDate = ref(new Date())
const maxDate = ref(new Date(Date.now() + 365 * 24 * 60 * 60 * 1000)) // 一年后

// 选中的SKU ID
const selectedSkuIds = ref([])

// 临时选中的商品数据
const selectedSkus = ref([])

// 计算调入仓库的排除ID列表
const toWarehouseExcludeIds = computed(() => {
    return form.from_warehouse_id ? [form.from_warehouse_id] : []
})

// 表单验证方法
const validateForm = () => {
    if (!form.from_warehouse_id) {
        showToast('请选择调出仓库')
        return false
    }
    
    if (!form.to_warehouse_id) {
        showToast('请选择调入仓库')
        return false
    }
    
    if (form.from_warehouse_id === form.to_warehouse_id) {
        showToast('调出仓库和调入仓库不能相同')
        return false
    }
    
    if (!form.transfer_date) {
        showToast('请选择调拨日期')
        return false
    }
    
    if (form.items.length === 0) {
        showToast('请至少添加一个调拨商品')
        return false
    }

    // 验证每个商品的调拨数量
    for (const item of form.items) {
        if (!item.quantity || Number(item.quantity) <= 0) {
            showToast(`请输入${item.product_name}的有效调拨数量`)
            return false
        }
        
        if (Number(item.quantity) > item.current_stock) {
            showToast(`${item.product_name}的调拨数量不能超过当前库存`)
            return false
        }
    }

    return true
}

// 事件处理方法
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
        if (!validateForm()) {
            return
        }

        const submitData = {
            from_warehouse_id: form.from_warehouse_id,
            to_warehouse_id: form.to_warehouse_id,
            remark: form.remark || '',
            items: form.items.map(item => ({
                sku_id: item.sku_id,
                quantity: Number(item.quantity) || 0
            }))
        }

        const result = await addStockTransfer(submitData)
        showToast('调拨单创建成功')
        
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

// 仓库选择器事件处理
const onFromWarehouseSelectChange = (id, name) => {
    console.log('调出仓库选择变化:', id, name)
    if (id) {
        form.from_warehouse_id = id
        form.from_warehouse_name = name
        console.log('选择的调出仓库:', name, 'ID:', id)
    } else {
        form.from_warehouse_id = ''
        form.from_warehouse_name = ''
        console.log('已清空调出仓库选择')
    }
}

// 调出仓库确认事件
const onFromWarehouseConfirm = (id, warehouse) => {
    console.log('调出仓库确认:', id, warehouse)
    if (id) {
        form.from_warehouse_id = id
        form.from_warehouse_name = warehouse?.name || ''
    }
}

const onToWarehouseSelectChange = (id, name) => {
    console.log('调入仓库选择变化:', id, name)
    if (id) {
        form.to_warehouse_id = id
        form.to_warehouse_name = name
        console.log('选择的调入仓库:', name, 'ID:', id)
    } else {
        form.to_warehouse_id = ''
        form.to_warehouse_name = ''
        console.log('已清空调入仓库选择')
    }
}

// 调入仓库确认事件
const onToWarehouseConfirm = (id, warehouse) => {
    console.log('调入仓库确认:', id, warehouse)
    if (id) {
        form.to_warehouse_id = id
        form.to_warehouse_name = warehouse?.name || ''
    }
}

// 手动触发调出仓库选择器打开
const openFromWarehouseSelect = () => {
    fromWarehouseSelectRef.value?.openPicker?.()
}

// 手动触发调入仓库选择器打开
const openToWarehouseSelect = () => {
    toWarehouseSelectRef.value?.openPicker?.()
}

// 日期选择确认
const onDateConfirm = ({ selectedValues }) => {
    const [year, month, day] = selectedValues
    form.transfer_date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    showDatePicker.value = false
}

// 处理SKU选择
const handleSkuSelect = (sku) => {
    // 检查是否已经选择过
    const exists = selectedSkus.value.find(item => item.id === sku.id)
    if (exists) {
        // 如果已选择，则取消选择
        selectedSkus.value = selectedSkus.value.filter(item => item.id !== sku.id)
        selectedSkuIds.value = selectedSkuIds.value.filter(id => id !== sku.id)
    } else {
        // 添加到选择列表
        selectedSkus.value.push(sku)
        selectedSkuIds.value.push(sku.id)
    }
}

// 处理商品选择确认
const handleProductConfirm = () => {
    try {
        const addedCount = selectedSkus.value.length // 先记录要添加的数量
        
        // 添加选中的商品到调拨项，避免重复添加
        for (const sku of selectedSkus.value) {
            const exists = form.items.find(item => item.sku_id === sku.id)
            if (!exists) {
                // 更灵活地获取商品名称，适配不同的数据结构
                let productName = '未知商品'
                if (sku.sku?.product?.name) {
                    productName = sku.sku.product.name
                } else if (sku.product?.name) {
                    productName = sku.product.name
                } else if (sku.product_name) {
                    productName = sku.product_name
                } else if (sku.name) {
                    productName = sku.name
                }

                // 获取商品编码
                let productNo = ''
                if (sku.sku?.sku_code) {
                    productNo = sku.sku.sku_code
                } else if (sku.sku_code) {
                    productNo = sku.sku_code
                } else if (sku.product_no) {
                    productNo = sku.product_no
                } else if (sku.no) {
                    productNo = sku.no
                }

                // 获取规格
                let spec = ''
                if (sku.sku?.spec_text) {
                    spec = sku.sku.spec_text
                } else if (sku.spec_text) {
                    spec = sku.spec_text
                } else if (sku.sku?.spec && typeof sku.sku.spec === 'string') {
                    spec = sku.sku.spec
                } else if (sku.sku?.spec && typeof sku.sku.spec === 'object') {
                    spec = Object.entries(sku.sku.spec).map(([key, value]) => `${key}:${value}`).join(' ')
                } else if (sku.spec && typeof sku.spec === 'string') {
                    spec = sku.spec
                } else if (sku.spec && typeof sku.spec === 'object') {
                    spec = Object.entries(sku.spec).map(([key, value]) => `${key}:${value}`).join(' ')
                }

                const currentStock = sku.quantity || sku.stock_quantity || sku.stock || 0
                
                const newItem = {
                    sku_id: sku.id,
                    product_name: productName,
                    product_no: productNo,
                    spec: spec,
                    unit: sku.unit || '个',
                    current_stock: currentStock,
                    quantity: '1',
                    quantityError: ''
                }
                
                form.items.push(newItem)
            }
        }

        // 清空选择列表并关闭弹窗
        selectedSkus.value = []
        selectedSkuIds.value = []
        showProductPicker.value = false
        showToast(`成功添加 ${addedCount} 个商品`)
    } catch (error) {
        console.error('添加商品失败:', error)
        showToast('添加失败')
    }
}

// 验证数量输入
const validateQuantity = (item, index) => {
    const quantity = Number(item.quantity) || 0
    
    if (!item.quantity || item.quantity === '') {
        item.quantityError = '请输入调拨数量'
        return false
    }
    
    if (quantity <= 0) {
        item.quantityError = '调拨数量必须大于0'
        return false
    }
    
    if (quantity > item.current_stock) {
        item.quantityError = '调拨数量不能超过库存'
        return false
    }
    
    item.quantityError = ''
    return true
}

// 商品操作方法
const removeProduct = async (index) => {
    try {
        await showConfirmDialog({
            title: '确认删除',
            message: '确定要删除这个商品吗？'
        })
        
        form.items.splice(index, 1)
        showToast('删除成功')
    } catch {
        // 用户取消删除
    }
}

// 生命周期
onMounted(() => {
    // 调拨单号由后台自动生成，无需前端生成
    // 设置默认日期为今天
    const today = new Date()
    selectedDateArray.value = [today.getFullYear(), today.getMonth() + 1, today.getDate()]
})
</script>

<style scoped lang="scss">
.transfer-create-page {
    background: #f7f8fa;
    min-height: 100vh;

    :deep(.van-nav-bar) {
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .form-container {
        padding: 0;
        background: transparent;
    }

    .form-section {
        margin-bottom: 12px;

        :deep(.van-cell-group) {
            background: white;
            border-radius: 8px;
            margin: 0 16px;
            overflow: hidden;

            .van-cell-group__title {
                padding: 16px 16px 8px;
                font-size: 14px;
                font-weight: 600;
                color: #323233;
            }

            .van-cell {
                padding: 16px;

                &:not(:last-child)::after {
                    left: 16px;
                }

                .van-field__label {
                    width: 90px;
                    font-weight: 500;
                    color: #646566;
                }

                .van-field__control {
                    font-size: 14px;
                }

                &.van-field--required {
                    .van-field__label::after {
                    color: #ee0a24;
                }
            }
        }
    }
}

    // SKU区域样式
    .sku-section {
        margin-top: 16px;
        background-color: #fff;
        border-radius: 8px;
        overflow: hidden;

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #ebedf0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);

            span {
                font-size: 16px;
                font-weight: 600;
                color: #323233;
            }
        }

        .sku-stock-take-list {
            .sku-take-card {
                background: #fff;
                border-bottom: 1px solid #ebedf0;
                transition: all 0.3s ease;

                &:last-child {
                    border-bottom: none;
                }

                &:active {
                    background-color: #f2f3f5;
                }

                .sku-info {
                    padding: 16px;

                    .row {
                        display: flex;
                        align-items: center;
                        margin-bottom: 8px;
                        min-height: 22px;

                        &:last-child {
                            margin-bottom: 0;
                        }

                        &.product-row {
                            justify-content: space-between;
                            align-items: flex-start;

                            .product-name {
                                font-size: 15px;
                                font-weight: 600;
                                color: #323233;
                                flex: 1;
                                line-height: 1.4;
                                margin-right: 8px;
                            }

                            .sku-code {
                                font-size: 12px;
                                color: #969799;
                                background: #f7f8fa;
                                padding: 2px 6px;
                                border-radius: 4px;
                                font-family: monospace;
                                white-space: nowrap;
                            }
                        }

                        &.sku-row {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            gap: 12px;

                            .sku-spec {
                                font-size: 13px;
                                color: #646566;
                                flex: 1;
                            }

                            .quantity-input {
                                display: flex;
                                align-items: center;
                                gap: 8px;
                                white-space: nowrap;

                                .stock-label {
                                    font-size: 13px;
                                    color: #646566;
                                }

                                .quantity-field {
                                    width: 80px;

                                    :deep(.van-field__control) {
                                        text-align: center;
                                        background: #f7f8fa;
                                        border-radius: 4px;
                                    }
                                }

                                .unit {
                                    font-size: 13px;
                                    color: #646566;
                                }
                            }
                        }


                    }
                }
            }

            .van-empty {
                padding: 40px 20px;
            }
        }

        .delete-btn {
            height: 100%;
        }
    }
}

// 响应式设计
@media (max-width: 375px) {
    .transfer-create-page {
        .form-section {
            :deep(.van-cell-group) {
                margin: 0 12px;
                border-radius: 6px;

                .van-cell {
                    padding: 12px;

                    .van-field__label {
                        width: 80px;
                        font-size: 13px;
                    }
                }
            }
        }

        .product-list {
            .transfer-item {
                margin: 6px 12px;

                .item-actions {
                    padding: 10px 12px;
                    gap: 8px;

                    .van-field {
                        :deep(.van-field__label) {
                            width: 70px;
                            font-size: 13px;
                        }
                    }

                    .van-button {
                        min-width: 50px;
                        font-size: 12px;
                    }
                }
            }
        }
    }
}

.placeholder {
    color: #c8c9cc;
}

// 暗黑模式支持
@media (prefers-color-scheme: dark) {
    .transfer-create-page {
        background: #1a1a1a;

        :deep(.van-nav-bar) {
            background: #2a2a2a;
            color: white;

            .van-nav-bar__text,
            .van-nav-bar__title {
                color: white;
            }
        }

        .form-section {
            :deep(.van-cell-group) {
                background: #2a2a2a;

                .van-cell-group__title {
                    color: #f0f0f0;
                }

                .van-cell {
                    background: #2a2a2a;
                    color: #f0f0f0;

                    &:not(:last-child)::after {
                        border-color: #3a3a3a;
                    }

                    .van-field__label {
                        color: #b0b0b0;
                    }

                    .van-field__control {
                        color: #f0f0f0;
                        background: transparent;
                    }

                    .van-field__placeholder {
                        color: #808080;
                    }
                }
            }
        }
    }
}
</style>