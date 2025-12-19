<template>
    <!-- 客户选择器组件 -->
    <div v-if="!hideTrigger" class="customer-select" @click="openPicker">
        <!-- 插槽：自定义触发器 -->
        <slot name="trigger" :selected="selectedCustomer" :open="openPicker">
            <!-- 默认触发器：简单按钮 -->
            <div class="default-trigger" :class="{ 'trigger-disabled': disabled }">
                <van-button
                    :type="triggerButtonType"
                    :size="triggerButtonSize"
                    :block="triggerButtonBlock"
                    :disabled="disabled"
                    :loading="triggerLoading"
                >
                    <template v-if="selectedCustomer">
                        {{ displayText }}
                    </template>
                    <template v-else>
                        {{ placeholder }}
                    </template>
                    <van-icon v-if="showTriggerIcon" :name="triggerIcon" />
                </van-button>
            </div>
        </slot>
    </div>

    <!-- 客户选择弹出层 -->
    <van-popup
        v-model:show="showPicker"
        :position="popupPosition"
        round
        :style="popupStyle"
        :close-on-click-overlay="closeOnClickOverlay"
        @closed="handlePopupClosed"
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
                    @update:model-value="handleSearchInput"
                    @search="handleSearch"
                    @clear="handleClearSearch"
                />
            </div>

            <!-- 客户列表 -->
            <div class="customer-list-container">
                <div v-if="!loading">
                    <!-- 全部客户选项 -->
                    <div
                        v-if="showAllOption"
                        class="customer-item"
                        :class="{ 'customer-item--selected': isSelected(0) }"
                        @click="handleCustomerSelect({ id: 0, name: '全部客户' })"
                    >
                        <div class="customer-info">
                            <div class="customer-name-row">
                                <div class="name-and-tag">
                                    <span class="name">全部客户</span>
                                </div>
                                <div class="selected-status">
                                    <van-icon
                                        v-if="isSelected(0)"
                                        name="success"
                                        color="#07c160"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 客户列表 -->
                    <div class="customer-list">
                        <div
                            v-for="customer in filteredCustomers"
                            :key="customer.id"
                            class="customer-item"
                            :class="{
                                'customer-item--selected': isSelected(customer.id),
                                'customer-item--disabled': customer.status === 0
                            }"
                            @click="handleCustomerSelect(customer)"
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
                                    <div class="selected-status">
                                        <van-tag
                                            v-if="customer.status === 0"
                                            size="small"
                                            type="danger"
                                            plain
                                        >
                                            已禁用
                                        </van-tag>
                                        <van-icon
                                            v-if="isSelected(customer.id)"
                                            name="success"
                                            color="#07c160"
                                        />
                                    </div>
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
                </div>
                
                <!-- 加载中 -->
                <div v-else class="loading">
                    <van-loading type="spinner" size="24px" />
                    <span class="loading-text">加载中...</span>
                </div>
                
                <!-- 空状态 -->
                <van-empty
                    v-if="!loading && filteredCustomers.length === 0 && (!showAllOption || searchKeyword)"
                    :description="searchKeyword ? '未找到相关客户' : '暂无客户数据'"
                />
            </div>

            <!-- 操作按钮 -->
            <div v-if="showActions" class="picker-actions">
                <van-button type="default" @click="handleCancel">取消</van-button>
                <van-button
                    type="primary"
                    @click="handleConfirm"
                    :disabled="!hasSelection"
                >
                    {{ confirmButtonText || '确定' }}
                </van-button>
            </div>
        </div>
    </van-popup>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { showToast } from 'vant'
import { useCustomerStore } from '@/store/modules/customer'
import { formatAmount as formatAmountUtil } from '@/utils/format'
const props = defineProps({
    // 值绑定
    modelValue: {
        type: [Number, String, Object, Array],
        default: null
    },
    
    // 触发按钮配置
    hideTrigger: {
        type: Boolean,
        default: false
    },
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
    showConfirmButton: {
        type: Boolean,
        default: false
    },
    confirmButtonText: {
        type: String,
        default: '确定'
    },
    cancelButtonText: {
        type: String,
        default: '取消'
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
    },
    // 新增：是否显示全部客户选项
    showAllOption: {
        type: Boolean,
        default: false
    },
    // 新增：全部客户选项的值
    allCustomerValue: {
        type: [Number, String],
        default: 0
    },
    // 新增：是否允许选择已禁用的客户
    allowDisabled: {
        type: Boolean,
        default: false
    },
    // 新增：是否立即选择（不显示确认按钮时有效）
    immediateSelect: {
        type: Boolean,
        default: true
    },
    // 新增：是否自动加载数据
    autoLoad: {
        type: Boolean,
        default: true
    },
    // 新增：排除的客户ID
    excludeIds: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['update:modelValue', 'change', 'select', 'confirm', 'cancel', 'clear', 'search'])

const customerStore = useCustomerStore()
        
        // 状态
        const showPicker = ref(false)
        const searchKeyword = ref('')
        const loading = ref(false)
        const tempSelection = ref(props.mode === 'multi' ? [] : null)
        const finished = ref(false)
        const currentPage = ref(1)
        
        // 防抖定时器
        let searchTimer = null
        
        // 计算属性
        const selectedCustomer = computed(() => {
            if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
                return null
            }
            
            // 如果是"全部客户"选项
            if (props.modelValue == props.allCustomerValue && props.showAllOption) {
                return { id: props.allCustomerValue, name: '全部客户' }
            }
            
            // 多选模式
            if (props.mode === 'multi' && Array.isArray(props.modelValue)) {
                if (props.returnObject) {
                    return props.modelValue
                } else {
                    // 如果是ID数组，返回第一个选中的客户对象（用于显示）
                    if (props.modelValue.length > 0) {
                        const firstId = props.modelValue[0]
                        const customer = findCustomerById(firstId)
                        return customer || null
                    }
                    return null
                }
            }
            
            // 单选模式
            if (props.returnObject && typeof props.modelValue === 'object' && props.modelValue !== null) {
                return props.modelValue
            }
            
            // 单选模式，返回ID
            if (typeof props.modelValue === 'number' || typeof props.modelValue === 'string') {
                return findCustomerById(props.modelValue)
            }
            
            return null
        })
        
        const displayText = computed(() => {
            if (!selectedCustomer.value) return ''
            
            // "全部客户"选项
            if (selectedCustomer.value.id == props.allCustomerValue) {
                return '全部客户'
            }
            
            // 多选模式
            if (props.mode === 'multi' && Array.isArray(props.modelValue)) {
                if (props.returnObject) {
                    const names = props.modelValue.map(customer => customer?.name || '')
                    return names.filter(name => name).join(', ') || ''
                } else {
                    // 如果是ID数组
                    const count = props.modelValue.length
                    if (count === 0) return ''
                    if (count === 1) {
                        const customer = findCustomerById(props.modelValue[0])
                        return customer ? customer.name : `客户${props.modelValue[0]}`
                    }
                    return `已选 ${count} 个客户`
                }
            }
            
            // 单选模式
            return selectedCustomer.value.name || ''
        })
        
        // 获取客户列表数据
        const customerList = computed(() => {
            let customers = []
            if (props.useStoreCache) {
                customers = customerStore.list
            }
            
            // 排除指定的客户
            if (props.excludeIds.length > 0) {
                customers = customers.filter(item => !props.excludeIds.includes(item.id))
            }
            
            return customers
        })
        
        // 过滤后的客户列表（用于搜索）
        const filteredCustomers = computed(() => {
            let customers = customerList.value
            
            // 过滤状态
            if (props.onlyEnabled && !props.allowDisabled) {
                customers = customers.filter(item => item.status !== 0)
            }
            
            // 自定义筛选函数
            if (props.filterFn && typeof props.filterFn === 'function') {
                customers = customers.filter(props.filterFn)
            }
            
            // 搜索过滤
            if (searchKeyword.value.trim()) {
                const keyword = searchKeyword.value.toLowerCase()
                return customers.filter(item => {
                    // 匹配客户名称
                    if (item.name && item.name.toLowerCase().includes(keyword)) {
                        return true
                    }
                    // 匹配联系人
                    if (item.contact_person && item.contact_person.toLowerCase().includes(keyword)) {
                        return true
                    }
                    // 匹配电话
                    if (item.phone && item.phone.toLowerCase().includes(keyword)) {
                        return true
                    }
                    return false
                })
            }
            
            return customers
        })
        
        // 检查是否有选择
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
        
        // 检查是否选中
        const isSelected = (customerId) => {
            if (props.mode === 'multi') {
                if (props.showActions) {
                    // 显示操作按钮时，使用临时选择
                    return tempSelection.value.some(item => {
                        if (props.returnObject) {
                            return item.id === customerId
                        } else {
                            return item === customerId
                        }
                    })
                }
                
                // 不显示操作按钮时，直接使用modelValue
                if (props.returnObject) {
                    return Array.isArray(props.modelValue) &&
                        props.modelValue.some(item => item.id === customerId)
                } else {
                    return Array.isArray(props.modelValue) &&
                        props.modelValue.includes(customerId)
                }
            } else {
                if (props.showActions) {
                    // 显示操作按钮时，使用临时选择
                    if (props.returnObject) {
                        return tempSelection.value && tempSelection.value.id === customerId
                    } else {
                        return tempSelection.value === customerId
                    }
                }
                
                // 不显示操作按钮时，直接使用modelValue
                if (props.returnObject) {
                    return props.modelValue && props.modelValue.id === customerId
                } else {
                    return props.modelValue == customerId
                }
            }
        }
        
        // 方法
        const findCustomerById = (id) => {
            return customerList.value.find(customer => {
                return customer.id == id
            })
        }
        
        // 打开选择器
        const openPicker = () => {
            if (props.disabled) return
            showPicker.value = true
        }
        
        // 加载客户数据
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
                    finished.value = customerStore.list.length >= result.total
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
        
        // 加载更多
        const loadMore = () => {
            if (finished.value || loading.value) return
            loadCustomers(false)
        }
        
        // 重置并加载
        const resetAndLoad = () => {
            currentPage.value = 1
            finished.value = false
            loadCustomers()
        }
        
        // 搜索处理
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
        
        // 选择客户
        const handleCustomerSelect = (customer) => {
            if (props.disabled) return
            
            // 处理"全部客户"选项
            if (customer.id == props.allCustomerValue) {
                // 如果显示确认按钮，使用临时选择
                if (props.showActions) {
                    if (props.mode === 'multi') {
                        // 多选模式下，选择全部客户会清空其他选择
                        tempSelection.value = [props.allCustomerValue]
                    } else {
                        tempSelection.value = props.allCustomerValue
                    }
                } else {
                    // 否则立即确认选择
                    confirmCustomerSelect(customer)
                }
                return
            }
            
            // 检查是否允许选择已禁用的客户
            if (customer.status === 0 && !props.allowDisabled) {
                showToast('该客户已禁用，无法选择')
                return
            }
            
            // 如果显示确认按钮，使用临时选择
            if (props.showActions) {
                updateTempSelection(customer)
                return
            }
            
            // 否则立即确认选择
            confirmCustomerSelect(customer)
        }
        
        // 更新临时选择
        const updateTempSelection = (customer) => {
            // 如果选择了"全部客户"，清空其他选择
            if (customer.id == props.allCustomerValue) {
                if (props.mode === 'multi') {
                    tempSelection.value = [props.allCustomerValue]
                } else {
                    tempSelection.value = props.allCustomerValue
                }
                return
            }
            
            // 如果已经选择了"全部客户"，清空全部客户选项
            if (tempSelection.value && 
                (props.mode === 'single' ? tempSelection.value == props.allCustomerValue : 
                 tempSelection.value.includes(props.allCustomerValue))) {
                if (props.mode === 'multi') {
                    tempSelection.value = []
                } else {
                    tempSelection.value = null
                }
            }
            
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
        
        // 确认选择
        const confirmCustomerSelect = (customer) => {
            let newValue
            
            // 处理"全部客户"选项
            if (customer.id == props.allCustomerValue) {
                newValue = props.mode === 'multi' ? [props.allCustomerValue] : props.allCustomerValue
            } else {
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
            }
            
            // 更新值
            emit('update:modelValue', newValue)
            emit('change', newValue, customer ? customer.name : '')
            emit('select', customer)
            
            // 如果不是多选或显示操作按钮，关闭弹窗
            if (props.mode === 'single' && !props.showActions && props.immediateSelect) {
                showPicker.value = false
                searchKeyword.value = ''
            }
        }
        
        // 确认选择（当显示确认按钮时）
        const handleConfirm = () => {
            let newValue = tempSelection.value
            
            // 确保格式正确
            if (props.mode === 'multi' && !Array.isArray(newValue)) {
                newValue = []
            }
            
            // 处理"全部客户"选项
            if (newValue && (props.mode === 'single' ? newValue == props.allCustomerValue : 
                Array.isArray(newValue) && newValue.includes(props.allCustomerValue))) {
                // 如果选择了"全部客户"，清空其他选择
                newValue = props.mode === 'multi' ? [props.allCustomerValue] : props.allCustomerValue
            }
            
            emit('update:modelValue', newValue)
            emit('change', newValue, getDisplayTextForValue(newValue))
            emit('confirm', newValue)
            
            showPicker.value = false
            searchKeyword.value = ''
            tempSelection.value = props.mode === 'multi' ? [] : null
        }
        
        // 取消选择
        const handleCancel = () => {
            showPicker.value = false
            searchKeyword.value = ''
            tempSelection.value = props.mode === 'multi' ? [] : null
            emit('cancel')
        }
        
        // 弹窗关闭后
        const handlePopupClosed = () => {
            // 重置临时选择
            tempSelection.value = props.mode === 'multi' ? [] : null
            searchKeyword.value = ''
        }
        
        // 格式化金额
        const formatAmount = (amount) => {
            return formatAmountUtil(amount)
        }
        
        // 获取值的显示文本
        const getDisplayTextForValue = (value) => {
            if (!value) return ''
            
            // "全部客户"选项
            if (value == props.allCustomerValue) {
                return '全部客户'
            }
            
            // 多选模式
            if (props.mode === 'multi' && Array.isArray(value)) {
                if (props.returnObject) {
                    const names = value.map(customer => customer?.name || '').filter(name => name)
                    return names.join(', ') || ''
                } else {
                    const count = value.length
                    if (count === 0) return ''
                    if (count === 1) {
                        const customer = findCustomerById(value[0])
                        return customer ? customer.name : `客户${value[0]}`
                    }
                    return `已选 ${count} 个客户`
                }
            }
            
            // 单选模式
            if (props.returnObject && typeof value === 'object' && value !== null) {
                return value.name || ''
            }
            
            // 单选模式，返回ID
            if (typeof value === 'number' || typeof value === 'string') {
                const customer = findCustomerById(value)
                return customer ? customer.name : `客户${value}`
            }
            
            return ''
        }
        
        // 是否显示其他信息
        const showOtherInfo = (customer) => {
            return props.showOtherInfo && (customer.email || customer.address)
        }
        
        // 监听弹窗显示/隐藏
        watch(showPicker, (newVal) => {
            if (newVal) {
                // 初始化临时选择
                if (props.showActions) {
                    if (props.mode === 'multi') {
                        tempSelection.value = props.modelValue && Array.isArray(props.modelValue)
                            ? [...props.modelValue]
                            : []
                    } else {
                        tempSelection.value = props.modelValue || null
                    }
                }
                
                // 弹窗显示时，清空搜索关键词
                searchKeyword.value = ''
                
                // 如果客户列表为空或不使用缓存，则加载数据
                if (customerList.value.length === 0 || !props.useStoreCache) {
                    loadCustomers()
                }
                
                // 自动聚焦搜索框（需要等待弹窗渲染完成）
                nextTick(() => {
                    const searchInput = document.querySelector('.customer-picker .van-search__field')
                    if (searchInput) {
                        searchInput.focus()
                    }
                })
            } else {
                // 弹窗关闭时，重置临时选择
                tempSelection.value = props.mode === 'multi' ? [] : null
                searchKeyword.value = ''
            }
        })
        
        // 监听外部值变化
        watch(() => props.modelValue, () => {
            // 这里可以处理外部值变化时的逻辑
        }, { immediate: true, deep: true })
        
        // 组件挂载时预加载客户数据
        onMounted(() => {
            if (props.immediate && props.autoLoad && !props.useStoreCache) {
                // 预加载数据（如果不使用store缓存）
                loadCustomers()
            }
        })
        
        // 暴露给父组件的方法
defineExpose({
    openPicker,
    closePicker: () => { showPicker.value = false },
    loadCustomers,
    clear: () => {
        emit('update:modelValue', props.mode === 'multi' ? [] : null)
        emit('change', props.mode === 'multi' ? [] : null, '')
        emit('clear')
        searchKeyword.value = ''
    },
    // 获取当前选中的客户信息
    getSelectedCustomer: () => {
        if (!props.modelValue) return null
        
        if (props.mode === 'multi') {
            if (props.returnObject) {
                return Array.isArray(props.modelValue) ? props.modelValue : []
            } else {
                return Array.isArray(props.modelValue) 
                    ? props.modelValue.map(id => {
                        if (id === props.allCustomerValue) return { id: props.allCustomerValue, name: '全部客户' }
                        return findCustomerById(id)
                    }).filter(Boolean)
                    : []
            }
        } else {
            if (props.returnObject) {
                return props.modelValue
            } else {
                if (props.modelValue === props.allCustomerValue) {
                    return { id: props.allCustomerValue, name: '全部客户' }
                }
                return findCustomerById(props.modelValue)
            }
        }
    },
    refresh: () => { resetAndLoad() }
})
</script>

<style scoped>
.customer-select {
    display: inline-block;
    width: 100%;
}

.default-trigger {
    cursor: pointer;
    
    &.trigger-disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }
}

.customer-picker {
    height: 100%;
    display: flex;
    flex-direction: column;
    background-color: #fff;
}

.picker-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    border-bottom: 1px solid #ebedf0;
    
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

.customer-list-container {
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.customer-list {
    padding: 8px 0;
}

.customer-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #f5f5f5;
    cursor: pointer;
    transition: background-color 0.2s;
    
    &:hover {
        background-color: #f7f8fa;
    }
    
    &:active {
        background-color: #ebedf0;
    }
    
    &.customer-item--selected {
        background-color: #f0f9ff;
    }
    
    &.customer-item--disabled {
        opacity: 0.6;
        cursor: not-allowed;
        
        &:hover,
        &:active {
            background-color: transparent;
        }
    }
}

.customer-info {
    flex: 1;
    margin-right: 12px;
    overflow: hidden;
    
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
                font-size: 14px;
                font-weight: 500;
                color: #323233;
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
        
        .selected-status {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            
            :deep(.van-tag) {
                flex-shrink: 0;
            }
            
            .van-icon-success {
                font-size: 16px;
                flex-shrink: 0;
            }
        }
    }
    
    .contact-info {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px 12px;
        margin-bottom: 4px;
        font-size: 12px;
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
        font-size: 11px;
        color: #999;
        
        .email,
        .address {
            line-height: 1.4;
        }
    }
    
    .account-info {
        font-size: 11px;
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

.loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 0;
    
    .loading-text {
        margin-top: 12px;
        font-size: 14px;
        color: #969799;
    }
}

/* 操作按钮 */
.picker-actions {
    display: flex;
    padding: 12px 16px;
    border-top: 1px solid #ebedf0;
    gap: 12px;
    
    .van-button {
        flex: 1;
    }
}

/* 空状态样式调整 */
:deep(.van-empty) {
    padding: 40px 0;
}

/* 确保弹窗内容不会超出屏幕 */
:deep(.van-popup) {
    overflow: hidden;
}

/* 调整搜索框样式 */
:deep(.van-search) {
    padding: 0;
    
    .van-search__content {
        border-radius: 16px;
        background-color: #fff;
    }
}

/* 响应式调整 */
@media (max-width: 768px) {
    .picker-header {
        padding: 14px;
    }
    
    .search-box {
        padding: 8px 14px;
    }
    
    .customer-item {
        padding: 10px 14px;
    }
    
    .picker-actions {
        padding: 10px 14px;
    }
}
</style>