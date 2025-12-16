<template>
    <!-- 客户选择器组件 -->
    <div class="customer-select">
        <!-- 插槽：自定义触发器 -->
        <slot name="trigger" :selected="selectedValue" :open="openPicker">
            <!-- 默认触发器：简单按钮 -->
            <div class="default-trigger" @click="openPicker">
                <van-button
                    :type="triggerButtonType"
                    :size="triggerButtonSize"
                    :block="triggerButtonBlock"
                    :disabled="disabled"
                    :loading="triggerLoading"
                >
                    <template v-if="selectedValue && (mode === 'single' || !Array.isArray(selectedValue))">
                        {{ displayText }}
                    </template>
                    <template v-else-if="mode === 'multi' && Array.isArray(selectedValue) && selectedValue.length > 0">
                        已选 {{ selectedValue.length }} 个客户
                    </template>
                    <template v-else>
                        {{ placeholder }}
                    </template>
                    <van-icon v-if="showTriggerIcon" :name="triggerIcon" />
                </van-button>
            </div>
        </slot>

        <!-- 客户选择弹出层 -->
        <van-popup
            v-model:show="showPicker"
            :position="popupPosition"
            :round="true"
            :close-on-click-overlay="true"
            :style="popupStyle"
            @closed="onPopupClosed"
        >
            <div class="customer-picker">
                <!-- 标题栏 -->
                <div class="picker-header">
                    <div class="header-title">{{ popupTitle || '选择客户' }}</div>
                    <van-icon
                        name="cross"
                        class="close-icon"
                        @click="showPicker = false"
                    />
                </div>

                <!-- 搜索框 -->
                <div class="search-box">
                    <van-search
                        v-model="searchKeyword"
                        :placeholder="searchPlaceholder"
                        @search="handleSearch"
                        @clear="handleClearSearch"
                        @update:model-value="handleSearchInput"
                    />
                </div>

                <!-- 客户列表 -->
                <div class="picker-content">
                    <van-list
                        v-model:loading="loading"
                        :finished="finished"
                        :finished-text="finishedText"
                        :immediate-check="false"
                        @load="loadMore"
                    >
                        <!-- 加载中 -->
                        <template v-if="loading && !customerList.length">
                            <div class="loading-container">
                                <van-loading type="spinner" size="24px" vertical>加载中...</van-loading>
                            </div>
                        </template>

                        <!-- 空状态 -->
                        <div v-else-if="!customerList.length" class="empty-state">
                            <van-empty
                                :image="searchKeyword ? 'search' : 'default'"
                                :description="searchKeyword ? `未找到「${searchKeyword}」相关客户` : '暂无客户数据'"
                            />
                        </div>

                        <!-- 客户列表 -->
                        <div v-else class="customer-list">
                            <div
                                v-for="customer in customerList"
                                :key="customer.id"
                                class="customer-item"
                                :class="{ 'customer-item--selected': isSelected(customer) }"
                                @click="handleItemClick(customer)"
                            >
                                <!-- 客户信息 -->
                                <div class="customer-info">
                                    <!-- 客户名称区域 -->
                                    <div class="customer-name-row">
                                        <!-- 客户名称和标签 -->
                                        <div class="name-and-tag">
                                            <!-- 客户名称 -->
                                            <span class="name">
                                                {{ customer.name }}
                                                <van-tag
                                                    v-if="customer.type === 2"
                                                    size="small"
                                                    type="primary"
                                                    round
                                                    class="company-tag"
                                                >
                                                    公司
                                                </van-tag>
                                            </span>
                                        </div>

                                        <!-- 选中标记 -->
                                        <van-icon
                                            v-if="isSelected(customer)"
                                            name="success"
                                            class="selected-icon"
                                        />
                                    </div>

                                    <!-- 联系人信息 -->
                                    <div v-if="customer.contact_person || customer.phone" class="contact-info">
                                        <span v-if="customer.contact_person" class="contact-person">
                                            {{ customer.contact_person }}
                                        </span>
                                        <span v-if="customer.phone" class="phone">
                                            {{ customer.phone }}
                                        </span>
                                    </div>

                                    <!-- 其他信息 -->
                                    <div v-if="showOtherInfo(customer)" class="other-info">
                                        <span v-if="customer.email" class="email">
                                            {{ customer.email }}
                                        </span>
                                        <span v-if="customer.address" class="address">
                                            {{ customer.address }}
                                        </span>
                                    </div>

                                    <!-- 账款信息 -->
                                    <div v-if="showArrears && customer.receivable_balance !== undefined" class="account-info">
                                        <span class="label">应收余额：</span>
                                        <span
                                            class="amount"
                                            :class="{
                                                'amount--danger': customer.receivable_balance > 0,
                                                'amount--zero': customer.receivable_balance === 0,
                                                'amount--credit': customer.receivable_balance < 0
                                            }"
                                        >
                                            ¥{{ formatAmount(customer.receivable_balance) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </van-list>
                </div>

                <!-- 操作按钮 -->
                <div v-if="showActions" class="picker-actions">
                    <van-button type="default" @click="handleCancel">取消</van-button>
                    <van-button
                        type="primary"
                        @click="handleConfirm"
                        :disabled="!hasSelection"
                    >
                        确定
                    </van-button>
                </div>
            </div>
        </van-popup>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { showToast } from 'vant'
import { useCustomerStore } from '@/store/modules/customer'
import { formatAmount as formatAmountUtil } from '@/utils/format'

// Props定义
const props = defineProps({
    // 值绑定
    modelValue: {
        type: [Number, String, Object, Array],
        default: null
    },
    
    // 触发按钮配置
    placeholder: {
        type: String,
        default: '请选择客户'
    },
    disabled: {
        type: Boolean,
        default: false
    },
    triggerButtonType: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'primary', 'success', 'warning', 'danger'].includes(value)
    },
    triggerButtonSize: {
        type: String,
        default: 'normal',
        validator: (value) => ['large', 'normal', 'small', 'mini'].includes(value)
    },
    triggerButtonBlock: {
        type: Boolean,
        default: true
    },
    triggerLoading: {
        type: Boolean,
        default: false
    },
    showTriggerIcon: {
        type: Boolean,
        default: true
    },
    triggerIcon: {
        type: String,
        default: 'arrow-down'
    },
    
    // 弹窗配置
    popupTitle: {
        type: String,
        default: '选择客户'
    },
    searchPlaceholder: {
        type: String,
        default: '搜索客户名称/联系人/电话'
    },
    popupPosition: {
        type: String,
        default: 'bottom',
        validator: (value) => ['bottom', 'center', 'top'].includes(value)
    },
    popupStyle: {
        type: Object,
        default: () => ({ height: '70%' })
    },
    closeOnClickOverlay: {
        type: Boolean,
        default: true
    },
    showActions: {
        type: Boolean,
        default: false
    },
    
    // 数据配置
    showArrears: {
        type: Boolean,
        default: false
    },
    onlyEnabled: {
        type: Boolean,
        default: true
    },
    filterFn: {
        type: Function,
        default: null
    },
    immediate: {
        type: Boolean,
        default: true
    },
    returnObject: {
        type: Boolean,
        default: false
    },
    mode: {
        type: String,
        default: 'single',
        validator: (value) => ['single', 'multi'].includes(value)
    },
    finishedText: {
        type: String,
        default: '没有更多了'
    },
    pageSize: {
        type: Number,
        default: 20
    },
    showOtherInfo: {
        type: Boolean,
        default: false
    },
    autoSearchOnClear: {
        type: Boolean,
        default: true
    },
    debounceDelay: {
        type: Number,
        default: 500
    },
    // 使用状态管理缓存
    useStoreCache: {
        type: Boolean,
        default: true
    }
})

// Emits定义
const emit = defineEmits([
    'update:modelValue',
    'change',
    'select',
    'confirm',
    'cancel',
    'clear',
    'search'
])

// 使用状态管理
const customerStore = useCustomerStore()

// 响应式数据
const showPicker = ref(false)
const searchKeyword = ref('')
const loading = ref(false)
const finished = ref(false)
const currentPage = ref(1)
const total = ref(0)
const tempSelection = ref(props.mode === 'multi' ? [] : null)

// 使用状态管理的列表或组件本地列表
const customerList = computed(() => {
    if (props.useStoreCache) {
        return customerStore.list
    }
    return []
})

// 防抖定时器
let searchTimer = null

// 计算属性
const displayText = computed(() => {
    if (!props.modelValue) return ''

    // 多选模式
    if (props.mode === 'multi' && Array.isArray(props.modelValue)) {
        if (props.returnObject) {
            const names = props.modelValue.map(customer => customer?.name || '')
            return names.filter(name => name).join(', ') || ''
        } else {
            // 如果是ID数组，尝试从列表中查找名称
            const names = props.modelValue.map(id => {
                const customer = findCustomerById(id)
                return customer ? customer.name : `客户${id}`
            })
            return names.join(', ') || ''
        }
    }

    // 单选模式
    if (props.returnObject && typeof props.modelValue === 'object' && props.modelValue !== null) {
        return props.modelValue.name || ''
    }

    // 单选模式，返回ID
    if (typeof props.modelValue === 'number' || typeof props.modelValue === 'string') {
        const customer = findCustomerById(props.modelValue)
        return customer ? customer.name : (props.modelValue ? `客户${props.modelValue}` : '')
    }

    return ''
})

const selectedValue = computed(() => {
    return props.modelValue
})

const hasSelection = computed(() => {
    if (props.mode === 'multi') {
        if (props.showActions) {
            return tempSelection.value && tempSelection.value.length > 0
        }
        return props.modelValue && Array.isArray(props.modelValue) && props.modelValue.length > 0
    } else {
        if (props.showActions) {
            return tempSelection.value !== null
        }
        return props.modelValue !== null && props.modelValue !== undefined
    }
})

// 监听器
watch(searchKeyword, (newVal) => {
    if (newVal === '' && props.autoSearchOnClear) {
        resetAndLoad()
    }
})

watch(showPicker, (newVal) => {
    if (newVal) {
        // 打开弹窗时初始化临时选择
        initTempSelection()
        // 加载数据
        if (props.immediate && customerList.value.length === 0) {
            nextTick(() => {
                loadCustomers()
            })
        }
    } else {
        // 关闭弹窗时重置搜索
        if (searchKeyword.value) {
            searchKeyword.value = ''
        }
    }
})

watch(() => props.modelValue, () => {
    initTempSelection()
}, { deep: true })

// 生命周期
onMounted(() => {
    if (props.immediate && !props.useStoreCache) {
        // 预加载数据（如果不使用store缓存）
        loadCustomers()
    }
})

// 方法
const findCustomerById = (id) => {
    return customerList.value.find(customer => {
        return customer.id == id
    })
}

const isSelected = (customer) => {
    if (props.showActions) {
        // 显示操作按钮时，使用临时选择
        if (props.mode === 'multi') {
            return tempSelection.value.some(item => {
                if (props.returnObject) {
                    return item.id === customer.id
                } else {
                    return item === customer.id
                }
            })
        } else {
            if (props.returnObject) {
                return tempSelection.value && tempSelection.value.id === customer.id
            } else {
                return tempSelection.value === customer.id
            }
        }
    }

    // 不显示操作按钮时，直接使用modelValue
    if (props.mode === 'multi') {
        if (props.returnObject) {
            return Array.isArray(props.modelValue) &&
                props.modelValue.some(item => item.id === customer.id)
        } else {
            return Array.isArray(props.modelValue) &&
                props.modelValue.includes(customer.id)
        }
    } else {
        if (props.returnObject) {
            return props.modelValue && props.modelValue.id === customer.id
        } else {
            return props.modelValue == customer.id
        }
    }
}

const initTempSelection = () => {
    if (!props.showActions) return

    if (props.mode === 'multi') {
        tempSelection.value = props.modelValue && Array.isArray(props.modelValue)
            ? [...props.modelValue]
            : []
    } else {
        tempSelection.value = props.modelValue || null
    }
}

const openPicker = () => {
    if (props.disabled) return
    showPicker.value = true
}

const loadCustomers = async (reset = true) => {
    if (loading.value) return

    loading.value = true

    try {
        const params = {
            page: reset ? 1 : currentPage.value,
            page_size: props.pageSize
        }

        // 搜索关键词
        if (searchKeyword.value) {
            params.keyword = searchKeyword.value
        }

        // 状态筛选
        if (props.onlyEnabled) {
            params.status = 1
        }

        // 使用状态管理加载数据
        const result = await customerStore.loadList(params)

        if (reset) {
            currentPage.value = 1
            // 使用状态管理的数据
            total.value = result.total
        } else {
            // 分页加载时，状态管理会自动追加数据
            currentPage.value++
        }

        // 判断是否加载完成
        const listLength = customerStore.list.length
        finished.value = listLength >= result.total

    } catch (error) {
        console.error('加载客户列表失败:', error)
        showToast('加载失败，请重试')
        finished.value = true
    } finally {
        loading.value = false
    }
}

const loadMore = () => {
    if (finished.value || loading.value) return
    loadCustomers(false)
}

const handleSearch = () => {
    resetAndLoad()
    emit('search', searchKeyword.value)
}

const handleSearchInput = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        handleSearch()
    }, props.debounceDelay)
}

const handleClearSearch = () => {
    searchKeyword.value = ''
    if (props.autoSearchOnClear) {
        resetAndLoad()
    }
}

const resetAndLoad = () => {
    currentPage.value = 1
    finished.value = false
    // 重置状态管理的列表
    if (!props.useStoreCache) {
        // 如果使用本地列表，这里需要清空
        // 但如果是使用状态管理，store会自动处理
    }
    loadCustomers()
}

const handleItemClick = (customer) => {
    if (props.disabled) return

    if (props.showActions) {
        // 显示操作按钮时，更新临时选择
        updateTempSelection(customer)
    } else {
        // 不显示操作按钮时，直接确认选择
        confirmSelection(customer)
    }
}

const updateTempSelection = (customer) => {
    if (props.mode === 'multi') {
        // 多选模式
        if (props.returnObject) {
            const index = tempSelection.value.findIndex(item => item.id === customer.id)
            if (index >= 0) {
                tempSelection.value.splice(index, 1)
            } else {
                tempSelection.value.push(customer)
            }
        } else {
            const index = tempSelection.value.indexOf(customer.id)
            if (index >= 0) {
                tempSelection.value.splice(index, 1)
            } else {
                tempSelection.value.push(customer.id)
            }
        }
    } else {
        // 单选模式
        if (props.returnObject) {
            tempSelection.value = tempSelection.value?.id === customer.id ? null : customer
        } else {
            tempSelection.value = tempSelection.value === customer.id ? null : customer.id
        }
    }
}

const confirmSelection = (customer) => {
    let newValue

    if (props.mode === 'multi') {
        // 多选模式
        if (props.returnObject) {
            const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
            const existingIndex = current.findIndex(item => item.id === customer.id)
            
            if (existingIndex >= 0) {
                current.splice(existingIndex, 1)
            } else {
                current.push(customer)
            }
            newValue = current
        } else {
            const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
            const existingIndex = current.indexOf(customer.id)
            
            if (existingIndex >= 0) {
                current.splice(existingIndex, 1)
            } else {
                current.push(customer.id)
            }
            newValue = current
        }
    } else {
        // 单选模式
        if (props.returnObject) {
            newValue = props.modelValue?.id === customer.id ? null : customer
        } else {
            newValue = props.modelValue === customer.id ? null : customer.id
        }
    }

    // 更新值
    emit('update:modelValue', newValue)
    emit('change', newValue)
    emit('select', customer)

    // 如果不是多选或显示操作按钮，关闭弹窗
    if (props.mode === 'single' && !props.showActions) {
        showPicker.value = false
        searchKeyword.value = ''
    }
}

const handleConfirm = () => {
    let newValue = tempSelection.value
    
    // 确保格式正确
    if (props.mode === 'multi' && !Array.isArray(newValue)) {
        newValue = []
    }
    
    emit('update:modelValue', newValue)
    emit('change', newValue)
    emit('confirm', newValue)
    
    showPicker.value = false
    searchKeyword.value = ''
}

const handleCancel = () => {
    showPicker.value = false
    searchKeyword.value = ''
    emit('cancel')
}

const onPopupClosed = () => {
    // 重置临时选择
    initTempSelection()
}

const formatAmount = (amount) => {
    return formatAmountUtil(amount)
}

const showOtherInfo = (customer) => {
    return props.showOtherInfo && (customer.email || customer.address)
}

// 暴露方法给父组件
defineExpose({
    openPicker,
    closePicker: () => { showPicker.value = false },
    refresh: () => { resetAndLoad() },
    clearSelection: () => {
        emit('update:modelValue', props.mode === 'multi' ? [] : null)
        emit('change', props.mode === 'multi' ? [] : null)
        emit('clear')
    },
    // 获取当前选中的客户信息
    getSelectedCustomer: () => {
        if (!props.modelValue) return null
        
        if (props.mode === 'multi') {
            if (props.returnObject) {
                return Array.isArray(props.modelValue) ? props.modelValue : []
            } else {
                return Array.isArray(props.modelValue) 
                    ? props.modelValue.map(id => findCustomerById(id)).filter(Boolean)
                    : []
            }
        } else {
            if (props.returnObject) {
                return props.modelValue
            } else {
                return findCustomerById(props.modelValue)
            }
        }
    }
})
</script>

<style scoped lang="scss">
.customer-select {
    width: 100%;
    display: inline-block;
    
    .default-trigger {
        cursor: pointer;
        
        &:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
    }
}

.customer-picker {
    height: 70vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    overflow: hidden;

    .picker-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        border-bottom: 1px solid #ebedf0;
        flex-shrink: 0;
        
        .header-title {
            font-size: 16px;
            font-weight: 600;
            color: #323233;
        }
        
        .close-icon {
            font-size: 18px;
            color: #969799;
            cursor: pointer;
        }
    }

    .search-box {
        padding: 10px 16px;
        background-color: #f7f8fa;
        border-bottom: 1px solid #ebedf0;
    }

    .picker-content {
        flex: 1;
        overflow-y: auto;
        min-height: 0;

        .loading-container {
            padding: 40px 0;
            text-align: center;
        }

        .empty-state {
            padding: 60px 20px;
        }

        .customer-list {
            .customer-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 16px;
                border-bottom: 1px solid #f5f5f5;
                cursor: pointer;
                transition: background-color 0.2s;

                &:last-child {
                    border-bottom: none;
                }

                &:hover {
                    background-color: #fafafa;
                }

                &:active {
                    background-color: #f5f5f5;
                }

                &--selected {
                    background-color: #f0f9ff;
                    
                    &:hover {
                        background-color: #e6f7ff;
                    }
                }

                .customer-info {
                    flex: 1;
                    overflow: hidden;
                    min-width: 0;

                    .customer-name-row {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 8px;
                        margin-bottom: 4px;

                        .name-and-tag {
                            flex: 1;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            overflow: hidden;
                            min-width: 0;

                            .name {
                                font-size: 16px;
                                font-weight: 500;
                                color: #1a1a1a;
                                line-height: 1.4;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                white-space: nowrap;
                                flex: 1;
                                min-width: 0;
                            }

                            .company-tag {
                                height: 20px;
                                line-height: 18px;
                                font-size: 11px;
                                padding: 0 6px;
                                flex-shrink: 0;
                            }
                        }

                        .selected-icon {
                            color: #07c160;
                            font-size: 16px;
                            flex-shrink: 0;
                            margin-left: 4px;
                        }
                    }

                    .contact-info {
                        display: flex;
                        align-items: center;
                        flex-wrap: wrap;
                        gap: 8px 12px;
                        margin-bottom: 4px;
                        font-size: 13px;
                        color: #666;

                        .contact-person {
                            padding: 1px 6px;
                            background: #f0f0f0;
                            border-radius: 3px;
                            line-height: 1.4;
                        }

                        .phone {
                            color: #1890ff;
                            line-height: 1.4;
                        }
                    }

                    .other-info {
                        display: flex;
                        flex-direction: column;
                        gap: 2px;
                        margin-bottom: 4px;
                        font-size: 12px;
                        color: #999;

                        .email,
                        .address {
                            line-height: 1.4;
                        }
                    }

                    .account-info {
                        font-size: 12px;
                        line-height: 1.4;

                        .label {
                            color: #999;
                        }

                        .amount {
                            font-weight: 500;

                            &--danger {
                                color: #f5222d;
                            }

                            &--zero {
                                color: #999;
                            }

                            &--credit {
                                color: #52c41a;
                            }
                        }
                    }
                }
            }
        }
    }

    .picker-actions {
        display: flex;
        padding: 12px 16px;
        border-top: 1px solid #f5f5f5;
        gap: 12px;
        flex-shrink: 0;

        .van-button {
            flex: 1;
        }
    }
}

// 响应式调整
@media (max-width: 768px) {
    .customer-picker {
        height: 80vh;

        .picker-header {
            padding: 14px;
        }
        
        .search-box {
            padding: 8px 14px;
        }

        .customer-list {
            .customer-item {
                padding: 10px 12px !important;

                .customer-info {
                    .customer-name-row {
                        .name-and-tag {
                            .name {
                                font-size: 14px !important;
                            }

                            .company-tag {
                                font-size: 10px !important;
                                padding: 0 4px !important;
                                height: 18px !important;
                                line-height: 16px !important;
                            }
                        }

                        .selected-icon {
                            font-size: 14px !important;
                        }
                    }

                    .contact-info {
                        font-size: 12px !important;
                    }

                    .other-info {
                        font-size: 11px !important;
                    }

                    .account-info {
                        font-size: 11px !important;
                    }
                }
            }
        }

        .picker-actions {
            padding: 10px 12px;
        }
    }
}

@media (max-width: 375px) {
    .customer-picker {
        height: 75vh;
    }
}

// 动画效果
:deep(.van-popup) {
    transition-timing-function: cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

// 自定义滚动条样式
.picker-content {
    &::-webkit-scrollbar {
        width: 4px;
    }

    &::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 2px;
    }

    &::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 2px;
    }

    &::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
}
</style>