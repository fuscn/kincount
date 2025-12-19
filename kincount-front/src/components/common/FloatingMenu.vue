<template>
  <!-- 悬浮气泡 -->
  <van-floating-bubble v-model:offset="offset" axis="xy" magnetic="x" :gap="0" teleport="body" @click="toggleMenu">
    <van-icon :name="isExpanded ? 'cross' : 'apps-o'" />
  </van-floating-bubble>

  <!-- 快捷菜单弹层 -->
  <van-popup 
    v-model:show="isExpanded" 
    position="bottom" 
    round 
    :style="{ height: '80%' }" 
    teleport="body"
    :transition-appear="true"
  >
    <div class="floating-menu-popup">
      <!-- 头部 -->
      <div class="menu-header">
        <h3 class="menu-title">快捷操作</h3>
        <div class="header-actions">
          <van-button type="primary" size="mini" @click="goToGuide">
            操作指南
          </van-button>
          <van-icon name="replay" class="reset-icon" @click="resetPosition" title="重置位置" />
          <van-icon name="cross" class="close-icon" @click="closeMenu" />
        </div>
      </div>

      <!-- 分类树 -->
      <div class="menu-content">
        <van-tree-select 
          v-model:main-active-index="activeCategoryIndex" 
          :items="menuTreeData" 
          height="100%"
          @click-nav-item="handleNavClick"
        >
          <template #content>
            <div class="category-content">
              <div 
                v-for="item in currentCategoryItems" 
                :key="item.id" 
                class="menu-item"
                :class="{ disabled: !hasPermission(item.perm), 'processing': processingItem === item.id }"
                @click="() => handleMenuItemClick(item)"
              >
                <div class="item-icon">
                  <van-icon :name="item.icon" />
                </div>
                <div class="item-content">
                  <div class="item-title">{{ item.name }}</div>
                  <div v-if="item.description" class="item-desc">
                    {{ item.description }}
                  </div>
                </div>
                <div class="item-arrow-container">
                  <van-icon name="arrow" class="item-arrow" />
                  <van-loading v-if="processingItem === item.id" size="16px" color="#1989fa" />
                </div>
              </div>

              <van-empty v-if="currentCategoryItems.length === 0" description="暂无操作项" image="search" />
            </div>
          </template>
        </van-tree-select>
      </div>

      <!-- 最近使用 -->
      <div v-if="recentItems.length > 0" class="recent-section">
        <div class="section-title">最近使用</div>
        <div class="recent-items">
          <van-tag 
            type="primary" 
            size="medium" 
            @click="goHome"
            :class="{ 'processing': processingItem === 'home' }"
          >
            <van-icon name="home-o" />
            返回首页
            <van-loading v-if="processingItem === 'home'" size="14px" color="#fff" />
          </van-tag>
          <van-tag 
            v-for="item in recentItems" 
            :key="item.id" 
            type="primary" 
            size="medium"
            @click="() => handleMenuItemClick(item)"
            :class="{ 'processing': processingItem === item.id }"
          >
            <van-icon :name="item.icon" class="recent-icon" />
            {{ item.name }}
            <van-loading v-if="processingItem === item.id" size="14px" color="#fff" />
          </van-tag>
        </div>
      </div>
    </div>
  </van-popup>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/modules/auth'
import { PERM } from '@/constants/permissions'
import { showToast } from 'vant'

const router = useRouter()
const authStore = useAuthStore()

/* -------------------- 数据 -------------------- */
const offset = ref({ x: 100, y: 400 })
const isExpanded = ref(false)
const activeCategoryIndex = ref(0)
const recentItems = ref([])
const processingItem = ref(null) // 当前正在处理的菜单项ID
let animationTimeout = null

/* -------------------- 完整菜单数据 -------------------- */
const menuData = [
  /* ===== 商品管理 ===== */
  {
    id: 'product',
    name: '商品管理',
    icon: 'apps-o',
    items: [
      { id: 'product-create', name: '新增商品', icon: 'add-o', path: '/product/create', perm: PERM.PRODUCT_ADD },
      { id: 'product-list', name: '商品列表', icon: 'photo-o', path: '/product', perm: PERM.PRODUCT_VIEW },
      { id: 'category', name: '分类管理', icon: 'label-o', path: '/category', perm: PERM.CATEGORY_VIEW },
      { id: 'brand', name: '品牌管理', icon: 'star-o', path: '/brand', perm: PERM.BRAND_VIEW }
    ]
  },
  /* ===== 采购管理 ===== */
  {
    id: 'purchase',
    name: '采购管理',
    icon: 'cart-o',
    items: [
      { id: 'po-create', name: '新增采购订单', icon: 'add-o', path: '/purchase/order/create', perm: PERM.PURCHASE_ORDER_ADD },
      { id: 'po-list', name: '采购订单', icon: 'orders-o', path: '/purchase/order', perm: PERM.PURCHASE_ORDER_VIEW },
      { id: 'pi-list', name: '采购入库', icon: 'logistics', path: '/purchase/stock', perm: PERM.PURCHASE_STOCK_VIEW },
      { id: 'po-return-list', name: '采购退货', icon: 'logistics', path: '/purchase/return', perm: PERM.RETURN_ORDER_VIEW },
      { id: 'po-return-storage', name: '退货出库单', icon: 'logistics', path: '/purchase/return/storage', perm: PERM.RETURN_STOCK_VIEW }
    ]
  },
  /* ===== 销售管理 ===== */
  {
    id: 'sale',
    name: '销售管理',
    icon: 'balance-o',
    items: [
      { id: 'so-create', name: '新增销售订单', icon: 'add-o', path: '/sale/order/create', perm: PERM.SALE_ORDER_ADD },
      { id: 'so-list', name: '销售订单列表', icon: 'orders-o', path: '/sale/order', perm: PERM.SALE_ORDER_VIEW },
      { id: 'so-stock', name: '销售出库列表', icon: 'logistics', path: '/sale/stock', perm: PERM.SALE_STOCK_VIEW },
      { id: 'so-return', name: '销售退货列表', icon: 'refund-o', path: '/sale/return', perm: PERM.RETURN_ORDER_VIEW },
      { id: 'so-return-storage', name: '退货入库列表', icon: 'refund-o', path: '/sale/return/storage', perm: PERM.RETURN_STOCK_VIEW }
    ]
  },
  /* ===== 库存管理 ===== */
  {
    id: 'stock',
    name: '库存管理',
    icon: 'bag-o',
    items: [
      { id: 'stock-list', name: '库存查询', icon: 'search', path: '/stock', perm: PERM.STOCK_VIEW },
      { id: 'stock-take', name: '库存盘点', icon: 'todo-list-o', path: '/stock/take', perm: PERM.STOCK_TAKE_VIEW },
      { id: 'stock-transfer', name: '库存调拨', icon: 'exchange', path: '/stock/transfer', perm: PERM.STOCK_TRANSFER_VIEW },
      { id: 'stock-warning', name: '库存预警', icon: 'warning-o', path: '/stock/warning', perm: PERM.STOCK_WARNING_VIEW }
    ]
  },
  /* ===== 账款管理 ===== */
  {
    id: 'account',
    name: '账款管理',
    icon: 'balance-o',
    items: [
      { id: 'ar-list', name: '应收款项', icon: 'gold-coin-o', path: '/account/receivable', perm: PERM.ACCOUNT_RECEIVABLE_VIEW },
      { id: 'ap-list', name: '应付款项', icon: 'cash-on-deliver', path: '/account/payable', perm: PERM.ACCOUNT_PAYABLE_VIEW },
      { id: 'settlement-list', name: '核销记录', icon: 'passed', path: '/account/settlement', perm: PERM.SETTLEMENT_VIEW }
    ]
  },
  /* ===== 财务管理 ===== */
  {
    id: 'financial',
    name: '财务管理',
    icon: 'balance-list-o',
    items: [
      { id: 'fi-record', name: '收支记录', icon: 'records-o', path: '/financial/record', perm: PERM.FINANCIAL_RECORD_VIEW },
      { id: 'fi-create', name: '新增收支', icon: 'add-o', path: '/financial/record/create', perm: PERM.FINANCIAL_RECORD_ADD },
      { id: 'fi-dashboard', name: '财务概览', icon: 'chart-trending-o', path: '/financial/dashboard', perm: PERM.FINANCIAL_RECORD_VIEW },
      { id: 'fi-profit', name: '利润报表', icon: 'chart-trending-o', path: '/financial/report/profit', perm: PERM.FINANCIAL_REPORT_VIEW },
      { id: 'fi-cashflow', name: '资金流水', icon: 'bill-o', path: '/financial/report/cashflow', perm: PERM.FINANCIAL_REPORT_VIEW }
    ]
  },
  /* ===== 系统管理 ===== */
  {
    id: 'system',
    name: '系统管理',
    icon: 'setting-o',
    items: [
      { id: 'sys-user', name: '用户管理', icon: 'friends-o', path: '/system/user', perm: PERM.USER_VIEW },
      { id: 'sys-role', name: '角色权限', icon: 'manager-o', path: '/system/role', perm: PERM.ROLE_VIEW },
      { id: 'sys-config', name: '系统配置', icon: 'setting-o', path: '/system/config', perm: PERM.CONFIG_VIEW }
    ]
  },
  /* ===== 基础资料 ===== */
  {
    id: 'base',
    name: '基础资料',
    icon: 'bookmark-o',
    items: [
      { id: 'customer', name: '客户管理', icon: 'friends-o', path: '/customer', perm: PERM.CUSTOMER_VIEW },
      { id: 'supplier', name: '供应商管理', icon: 'shop-o', path: '/supplier', perm: PERM.SUPPLIER_VIEW },
      { id: 'warehouse', name: '仓库管理', icon: 'home-o', path: '/warehouse', perm: PERM.WAREHOUSE_VIEW }
    ]
  },
  /* ===== 个人中心 ===== */
  {
    id: 'profile',
    name: '个人中心',
    icon: 'user-o',
    items: [
      { id: 'profile-info', name: '我的资料', icon: 'user-o', path: '/system/user/profile', perm: null },
      { id: 'profile-password', name: '修改密码', icon: 'closed-eye', path: '/system/user/password', perm: null }
    ]
  }
];

/* -------------------- 计算属性 -------------------- */
const menuTreeData = computed(() =>
  menuData.map(g => ({ text: g.name }))
)

const currentCategoryItems = computed(() => {
  const items = menuData[activeCategoryIndex.value]?.items || []
  return items.filter(it => hasPermission(it.perm))
})

const hasPermission = (p) => {
  if (p === null || p === undefined) {
    return true
  }
  return authStore.hasPerm(p)
}

/* -------------------- 菜单操作 -------------------- */
const toggleMenu = () => {
  isExpanded.value = !isExpanded.value
  if (isExpanded.value) {
    loadRecentItems()
  }
}

const closeMenu = () => {
  isExpanded.value = false
  processingItem.value = null
  if (animationTimeout) {
    clearTimeout(animationTimeout)
    animationTimeout = null
  }
}

const resetPosition = () => {
  offset.value = { x: 100, y: window.innerHeight - 100 }
  savePosition()
  showToast('位置已重置')
}

const goHome = async () => {
  processingItem.value = 'home'
  
  // 等待一小段时间确保用户看到点击反馈
  await new Promise(resolve => setTimeout(resolve, 50))
  
  router.push('/dashboard')
  
  // 等待路由跳转开始后再关闭菜单
  setTimeout(() => {
    closeMenu()
  }, 150)
}

const goToGuide = async () => {
  processingItem.value = 'guide'
  
  // 短暂延迟给用户反馈
  await new Promise(resolve => setTimeout(resolve, 50))
  
  // 先关闭菜单，等待动画完成后再跳转
  isExpanded.value = false
  
  // 等待菜单关闭动画完成
  setTimeout(() => {
    router.push('/system/operationflowguide')
    processingItem.value = null
  }, 300) // 匹配 Vant Popup 的动画时间
}

const handleMenuItemClick = async (item) => {
  if (!hasPermission(item.perm)) {
    showToast('没有操作权限')
    return
  }
  
  processingItem.value = item.id
  
  // 记录最近使用
  addToRecentItems(item)
  
  // 短暂延迟给用户反馈
  await new Promise(resolve => setTimeout(resolve, 50))
  
  // 先关闭菜单
  isExpanded.value = false
  
  // 等待菜单关闭动画完成后再跳转
  setTimeout(() => {
    router.push(item.path)
    processingItem.value = null
  }, 250) // 稍微短一点的时间，因为用户期待快速响应
}

const handleNavClick = (index) => {
  activeCategoryIndex.value = index
}

const addToRecentItems = (item) => {
  recentItems.value = [
    item,
    ...recentItems.value.filter(i => i.id !== item.id)
  ].slice(0, 3)
  localStorage.setItem('recentMenuItems', JSON.stringify(recentItems.value))
}

const loadRecentItems = () => {
  try {
    const raw = localStorage.getItem('recentMenuItems')
    if (raw) recentItems.value = JSON.parse(raw)
  } catch { }
}

const savePosition = () =>
  localStorage.setItem('floatingMenuPosition', JSON.stringify(offset.value))

/* -------------------- 生命周期 -------------------- */
onMounted(() => {
  loadRecentItems()
  try {
    const raw = localStorage.getItem('floatingMenuPosition')
    if (raw) offset.value = JSON.parse(raw)
  } catch { }
  
  const handleResize = () => {
    if (offset.value.y > window.innerHeight - 150) {
      offset.value = { ...offset.value, y: window.innerHeight - 100 }
      savePosition()
    }
  }
  
  window.addEventListener('resize', handleResize)
  
  onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (animationTimeout) {
      clearTimeout(animationTimeout)
    }
  })
})
</script>

<style scoped lang="scss">
.floating-menu-popup {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: #f7f8fa;
}

.menu-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f0f0f0;
  background: white;
  flex-shrink: 0;
}

.menu-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #323233;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.reset-icon,
.close-icon {
  font-size: 18px;
  color: #969799;
  cursor: pointer;
  padding: 4px;
  transition: transform 0.2s ease;

  &:active {
    background: #f7f8fa;
    border-radius: 4px;
    transform: scale(0.9);
  }
}

.menu-content {
  flex: 1;
  overflow: hidden;
  background: white;
}

.category-content {
  padding: 16px;
  height: 100%;
  overflow-y: auto;
}

.menu-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  margin-bottom: 8px;
  background: #fafafa;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  
  &:active:not(.disabled):not(.processing) {
    background: #f0f0f0;
    transform: scale(0.98);
  }
  
  &.processing {
    opacity: 0.8;
    background: #e6f7ff;
    cursor: wait;
    
    .item-title {
      color: #1989fa;
    }
    
    .van-icon {
      color: #1989fa;
    }
  }

  &.disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.item-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
  flex-shrink: 0;

  .van-icon {
    font-size: 16px;
    color: #1989fa;
    transition: color 0.2s ease;
  }
}

.item-content {
  flex: 1;
  min-width: 0;
}

.item-title {
  font-size: 14px;
  font-weight: 500;
  color: #323233;
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: color 0.2s ease;
}

.item-desc {
  font-size: 12px;
  color: #969799;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item-arrow-container {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: 8px;
  flex-shrink: 0;
  min-width: 40px;
  justify-content: flex-end;
}

.item-arrow {
  font-size: 12px;
  color: #c8c9cc;
}

.recent-section {
  padding: 16px;
  border-top: 1px solid #f0f0f0;
  background: #f8f9fa;
  flex-shrink: 0;
}

.section-title {
  font-size: 14px;
  font-weight: 500;
  color: #646566;
  margin-bottom: 8px;
}

.recent-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.recent-items .van-tag {
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  
  &:active:not(.processing) {
    opacity: 0.8;
    transform: scale(0.95);
  }
  
  &.processing {
    opacity: 0.7;
    background: rgba(25, 137, 250, 0.8);
    cursor: wait;
  }
  
  .van-icon {
    font-size: 12px;
  }
  
  .van-loading {
    margin-left: 4px;
  }
}

.recent-icon {
  margin-right: 2px;
}

/* 优化动画性能 */
:deep(.van-popup) {
  transform: translate3d(0, 0, 0);
  backface-visibility: hidden;
  perspective: 1000px;
}

:deep(.van-popup--bottom) {
  transform-origin: center bottom;
}

/* 滚动条优化 */
.category-content::-webkit-scrollbar {
  width: 4px;
}

.category-content::-webkit-scrollbar-thumb {
  background: #c8c9cc;
  border-radius: 2px;
}

.category-content::-webkit-scrollbar-track {
  background: transparent;
}

/* 响应式优化 */
@media (max-width: 768px) {
  .menu-header {
    padding: 14px 16px;
  }

  .category-content {
    padding: 12px;
  }

  .menu-item {
    padding: 10px 12px;
    margin-bottom: 6px;
  }
  
  .recent-section {
    padding: 12px;
  }
}

/* 深色模式适配 */
@media (prefers-color-scheme: dark) {
  .floating-menu-popup {
    background: #1a1a1a;
  }
  
  .menu-header {
    background: #2a2a2a;
    border-bottom-color: #333;
  }
  
  .menu-title {
    color: #e1e1e1;
  }
  
  .reset-icon,
  .close-icon {
    color: #a0a0a0;
    
    &:active {
      background: #333;
    }
  }
  
  .menu-content {
    background: #2a2a2a;
  }
  
  .menu-item {
    background: #333;
    
    &:active:not(.disabled):not(.processing) {
      background: #3a3a3a;
    }
    
    &.processing {
      background: rgba(25, 137, 250, 0.1);
    }
  }
  
  .item-title {
    color: #e1e1e1;
  }
  
  .item-desc {
    color: #888;
  }
  
  .item-arrow {
    color: #666;
  }
  
  .recent-section {
    background: #2a2a2a;
    border-top-color: #333;
  }
  
  .section-title {
    color: #aaa;
  }
}
</style>