<template>
    <div class="purchase-inbound-page">
        <!-- 导航栏 -->
        <van-nav-bar title="采购入库" left-arrow @click-left="handleBack" right-text="提交" @click-right="handleSubmit" />

        <!-- 表单容器 -->
        <van-form ref="formRef" class="form-container">
            <!-- 订单选择区域 -->
            <van-cell-group title="采购订单信息">
                <van-field v-model="form.order_no" label="订单编号" placeholder="请选择采购订单" readonly is-link required
                    :rules="[{ required: true, message: '请选择采购订单' }]" @click="showOrderPicker = true" />
                <van-field v-model="form.supplier_name" label="供应商" readonly placeholder="请先选择订单" />
                <van-field v-model="form.warehouse_name" label="入库仓库" readonly placeholder="请先选择订单" />
                <van-field v-model="form.inbound_date" label="入库日期" placeholder="请选择日期" readonly is-link required
                    :rules="[{ required: true, message: '请选择入库日期' }]" @click="showDatePicker = true" />
                <van-field v-model="form.remark" label="备注" type="textarea" placeholder="请输入入库备注信息" rows="2" />
            </van-cell-group>

            <!-- SKU入库明细 -->
            <van-cell-group title="SKU入库明细" v-if="form.items.length > 0">
                <!-- 表头 -->
                <van-row class="sku-table-header">
                    <van-col span="8">SKU信息</van-col>
                    <van-col span="4" class="text-center">采购数量</van-col>
                    <van-col span="4" class="text-center">已入库</van-col>
                    <van-col span="4" class="text-center">可入库</van-col>
                    <van-col span="4" class="text-center">本次入库</van-col>
                </van-row>

                <!-- 明细列表 -->
                <van-row class="sku-table-row" v-for="(item, index) in form.items" :key="item.sku_id + '_' + index">
                    <van-col span="8">
                        <div class="sku-info">
                            <div class="sku-name">{{ item.sku_name }}</div>
                            <div class="sku-code">{{ item.sku_code }}</div>
                            <div class="sku-spec">{{ item.spec_text || '无规格' }}</div>
                        </div>
                    </van-col>
                    <van-col span="4" class="text-center">{{ item.purchase_quantity }}</van-col>
                    <van-col span="4" class="text-center">{{ item.inbound_quantity || 0 }}</van-col>
                    <van-col span="4" class="text-center">{{ item.available_quantity }}</van-col>
                    <van-col span="4" class="text-center">
                        <van-field v-model.number="item.current_quantity" type="number" :min="1"
                            :max="item.available_quantity" @input="handleQuantityChange(item)" :rules="[
                                { required: true, message: '请输入数量' },
                                { validator: () => validateQuantity(item), message: '数量超出范围' }
                            ]" />
                    </van-col>
                </van-row>

                <!-- 合计 -->
                <van-row class="sku-table-total">
                    <van-col span="20" class="text-right">
                        <span class="total-label">本次入库合计：</span>
                    </van-col>
                    <van-col span="4" class="text-center">
                        <span class="total-value">{{ totalInboundQuantity }}</span>
                    </van-col>
                </van-row>
            </van-cell-group>

            <!-- 空状态 -->
            <van-empty v-if="form.items.length === 0 && !loadingOrder" description="请先选择采购订单" class="empty-state" />
        </van-form>

        <!-- 订单选择弹窗 -->
        <van-popup v-model:show="showOrderPicker" position="bottom" :style="{ height: '70%' }"
            :close-on-click-overlay="true">
            <div class="order-picker">
                <van-nav-bar title="选择采购订单" left-text="取消" @click-left="showOrderPicker = false" />
                <van-search v-model="orderSearch" placeholder="搜索订单号/供应商" @search="loadOrderList" />
                <van-list v-model:loading="loadingOrderList" :finished="finishedOrderList" finished-text="没有更多订单"
                    @load="loadOrderList">
                    <van-cell v-for="order in orderList" :key="order.id" :title="`订单号：${order.order_no}`"
                        :label="getOrderLabel(order)" @click="selectOrder(order)" is-link>
                        <template #extra>
                            <van-tag type="primary">{{ getStatusText(order.status) }}</van-tag>
                        </template>
                    </van-cell>
                </van-list>
            </div>
        </van-popup>

        <!-- 日期选择器 -->
        <van-popup v-model:show="showDatePicker" position="bottom" :close-on-click-overlay="true">
            <van-date-picker v-model="currentDate" title="选择入库日期" :min-date="minDate" :max-date="maxDate"
                @confirm="onDateConfirm" @cancel="showDatePicker = false" />
        </van-popup>

        <!-- 加载状态 -->
        <van-loading v-if="submitting" class="fullscreen-loading" />
    </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'
import dayjs from 'dayjs'

// 路由实例
const router = useRouter()

// 状态管理
const purchaseStore = usePurchaseStore()

// 表单引用
const formRef = ref(null)

// 响应式数据
const form = reactive({
    order_id: '',
    order_no: '',
    supplier_id: '',
    supplier_name: '',
    warehouse_id: '',
    warehouse_name: '',
    inbound_date: dayjs().format('YYYY-MM-DD'),
    remark: '',
    items: [] // SKU入库明细
})

// 选择器状态
const showOrderPicker = ref(false)
const showDatePicker = ref(false)
const currentDate = ref(new Date())
const minDate = new Date('2020-01-01')
const maxDate = new Date()

// 订单选择相关
const orderSearch = ref('')
const orderList = ref([])
const loadingOrderList = ref(false)
const finishedOrderList = ref(false)
const orderPage = ref(1)
const loadingOrder = ref(false)

// 提交状态
const submitting = ref(false)

// 计算本次入库总数量
const totalInboundQuantity = computed(() => {
    return form.items.reduce((sum, item) => {
        return sum + (Number(item.current_quantity) || 0)
    }, 0)
})


/**
 * 加载可入库的采购订单列表（状态为已审核或部分入库）
 */
const loadOrderList = async (isRefresh = false) => {
    if (isRefresh) {
        orderPage.value = 1
        finishedOrderList.value = false
    }

    loadingOrderList.value = true
    try {
        const params = {
            page: orderPage.value,
            pageSize: 10,
            status: [2, 3], // 只加载已审核(2)和部分入库(3)的订单
            keyword: orderSearch.value
        }



        const res = await purchaseStore.loadOrderList(params)

        // 修正数据路径：根据API响应结构调整
        const list = res?.data?.data || []  // 注意这里是 res.data.data

        if (isRefresh) {
            orderList.value = list
        } else {
            orderList.value = [...orderList.value, ...list]
        }

        // 判断是否加载完成
        if (list.length < params.pageSize) {
            finishedOrderList.value = true
        } else {
            orderPage.value++
        }
    } catch (error) {
        showToast('加载订单列表失败')
        console.error('loadOrderList error:', error)
    } finally {
        loadingOrderList.value = false
    }

    try {
        const res = await purchaseStore.loadOrderList(params)
        console.log('API响应:', res) // 调试信息
        console.log('数据路径:', res?.data) // 调试信息

        const list = res?.data?.data || []
        console.log('订单列表:', list) // 调试信息

        // ... 其他代码
    } catch (error) {
        // ... 错误处理
    }
}

/**
 * 选择采购订单并加载详情
 */
const selectOrder = async (order) => {
    if (!order.id) return

    loadingOrder.value = true
    try {
        // 加载订单详情（包含SKU明细）
        const detail = await purchaseStore.loadOrderDetail(order.id)
        const orderData = detail?.data || {}  // 注意这里可能是 detail.data

        // 填充订单基本信息
        form.order_id = orderData.id
        form.order_no = orderData.order_no
        form.supplier_id = orderData.supplier?.id
        form.supplier_name = orderData.supplier?.name
        form.warehouse_id = orderData.warehouse?.id
        form.warehouse_name = orderData.warehouse?.name

        // 处理SKU明细（计算可入库数量）
        form.items = (orderData.items || []).map(item => {
            const purchaseQty = Number(item.quantity) || 0
            const inboundQty = Number(item.received_quantity) || 0  // 注意字段名是 received_quantity
            const availableQty = purchaseQty - inboundQty

            return {
                sku_id: item.sku_id,
                sku_code: item.product?.product_no, // 使用产品编号作为SKU编码
                sku_name: item.product?.name || '未知产品',
                spec_text: item.product?.spec || '无规格',
                purchase_quantity: purchaseQty,
                inbound_quantity: inboundQty,
                available_quantity: availableQty,
                current_quantity: availableQty > 0 ? 1 : 0 // 默认1，不能超过可入库数量
            }
        })

        showOrderPicker.value = false
    } catch (error) {
        showToast('加载订单详情失败')
        console.error('selectOrder error:', error)
    } finally {
        loadingOrder.value = false
    }
}
/**
 * 验证入库数量
 */
const validateQuantity = (item) => {
    const qty = Number(item.current_quantity) || 0
    return qty > 0 && qty <= item.available_quantity
}

/**
 * 处理数量变化
 */
const handleQuantityChange = (item) => {
    // 确保数量为正数且不超过可入库数量
    let qty = Number(item.current_quantity) || 0
    if (qty < 1) qty = 1
    if (qty > item.available_quantity) qty = item.available_quantity
    item.current_quantity = qty
}

/**
 * 日期选择确认
 */
const onDateConfirm = (date) => {
    form.inbound_date = dayjs(date).format('YYYY-MM-DD')
    showDatePicker.value = false
}

/**
 * 获取订单状态文本
 */
const getStatusText = (status) => {
    const statusMap = {
        2: '已审核',
        3: '部分入库'
    }
    return statusMap[status] || '未知状态'
}

/**
 * 构造订单标签信息
 */
const getOrderLabel = (order) => {
    const labels = []
    if (order.supplier?.name) labels.push(`供应商：${order.supplier.name}`)
    if (order.created_at) labels.push(`创建时间：${dayjs(order.created_at).format('YYYY-MM-DD')}`)
    if (order.warehouse?.name) labels.push(`仓库：${order.warehouse.name}`)
    return labels.join(' | ')
}

/**
 * 提交入库单
 */
const handleSubmit = async () => {
    // 表单验证
    if (!form.order_id) {
        showToast('请选择采购订单')
        return
    }

    if (totalInboundQuantity.value <= 0) {
        showToast('请填写入库数量')
        return
    }

    // 构造提交数据
    const submitData = {
        purchase_order_id: form.order_id,
        inbound_date: form.inbound_date,
        remark: form.remark,
        items: form.items
            .filter(item => Number(item.current_quantity) > 0)
            .map(item => ({
                sku_id: item.sku_id,
                quantity: item.current_quantity
            }))
    }

    try {
        submitting.value = true
        // 调用入库接口
        const result = await purchaseStore.createInbound(submitData)
        if (result?.code === 200) {
            showSuccessToast('入库单创建成功')
            router.push('/purchase/inbound') // 跳转到入库单列表
        } else {
            showToast('创建失败，请重试')
        }
    } catch (error) {
        showToast(error.message || '提交失败')
        console.error('handleSubmit error:', error)
    } finally {
        submitting.value = false
    }
}

/**
 * 返回上一页
 */
const handleBack = () => {
    showConfirmDialog({
        title: '提示',
        message: '是否放弃当前入库单编辑？'
    })
        .then(() => {
            router.back()
        })
        .catch(() => {
            // 取消返回
        })
}

// 初始化
onMounted(() => {
    loadOrderList(true)
})
</script>
<style scoped lang="scss">
.purchase-inbound-page {
    background-color: #f7f8fa;
    min-height: 100vh;
    padding-bottom: 16px;
}

.form-container {
    padding: 16px;
}

.empty-state {
    margin: 40px 0;
}

// SKU表格样式
.sku-table-header {
    padding: 12px 16px;
    background-color: #f5f5f5;
    font-size: 14px;
    color: #666;
    border-bottom: 1px solid #eee;
    font-weight: 500;
}

.sku-table-row {
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    align-items: center;

    &:last-child {
        border-bottom: none;
    }
}

.sku-table-total {
    padding: 12px 16px;
    background-color: #f5f5f5;
    font-size: 14px;
    margin-top: 8px;

    .total-label {
        font-weight: 500;
    }

    .total-value {
        font-weight: 700;
        color: #ee0a24;
    }
}

.sku-info {
    .sku-name {
        font-size: 14px;
        margin-bottom: 4px;
    }

    .sku-code {
        font-size: 12px;
        color: #999;
        margin-bottom: 2px;
    }

    .sku-spec {
        font-size: 12px;
        color: #666;
    }
}

// 订单选择弹窗样式
.order-picker {
    .van-nav-bar {
        border-bottom: 1px solid #eee;
    }

    .van-search {
        margin: 10px;
    }
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.fullscreen-loading {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(255, 255, 255, 0.8);
    z-index: 100;
}
</style>