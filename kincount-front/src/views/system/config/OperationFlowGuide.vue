<template>
  <div class="operation-guide">
    <!-- 导航栏 - 参照 Profile.vue 样式 -->
    <van-nav-bar 
      title="操作指南" 
      left-text="返回" 
      left-arrow 
      @click-left="$router.back()" 
      safe-area-inset-top
    />

    <!-- 内容区 -->
    <div class="guide-content">
      <van-tabs v-model:active="activeTab" sticky offset-top="46px" swipeable>
        <!-- 1. 采购流程 -->
        <van-tab title="采购" name="purchase">
          <div class="tab-content">
            <div class="section-header">
              <van-icon name="shopping-cart-o" size="20" color="#1989fa" />
              <span class="section-title">采购流程</span>
            </div>
            
            <div class="process-category">
              <div class="category-header">
                <div class="category-icon">📦</div>
                <div class="category-title">采购订单</div>
              </div>
              
              <div class="step-list">
                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">创建订单</div>
                    <div class="step-desc">选择供应商与目标仓库，添加SKU级商品明细</div>
                    <div class="step-status">
                      <van-tag size="small" type="primary">待审核</van-tag>
                    </div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">审核订单</div>
                    <div class="step-desc">审核通过或驳回</div>
                    <div class="step-status">
                      <van-tag size="small" type="success">已审核</van-tag>
                      <span class="step-tag-divider">/</span>
                      <van-tag size="small" type="danger">已取消</van-tag>
                    </div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">3</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">生成入库单</div>
                    <div class="step-desc">根据采购订单生成采购入库单</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">4</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">入库处理</div>
                    <div class="step-desc">入库审核通过后系统自动：</div>
                    <div class="system-action">
                      <div class="action-item">
                        <van-icon name="passed" size="14" color="#07c160" />
                        <span>增加仓库SKU库存</span>
                      </div>
                      <div class="action-item">
                        <van-icon name="passed" size="14" color="#07c160" />
                        <span>生成应付账款</span>
                      </div>
                      <div class="action-item">
                        <van-icon name="passed" size="14" color="#07c160" />
                        <span>更新供应商余额</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">5</div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">完成订单</div>
                    <div class="step-desc">订单完成状态</div>
                    <div class="step-status">
                      <van-tag size="small" type="success">已完成</van-tag>
                      <span class="step-tag-divider">/</span>
                      <van-tag size="small" type="warning">部分入库</van-tag>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="process-category">
              <div class="category-header">
                <div class="category-icon">🔄</div>
                <div class="category-title">采购退货</div>
              </div>
              
              <div class="step-list compact">
                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">退货申请</div>
                    <div class="step-desc">选择原订单/入库单，录入退货SKU数量</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">审核退货</div>
                    <div class="step-desc">审核通过后生成「退货出库单」</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">3</div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">出库处理</div>
                    <div class="step-desc">确认出库后系统自动处理</div>
                    <div class="step-status">
                      <van-tag size="small" type="success">已完成</van-tag>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </van-tab>

        <!-- 2. 销售流程 -->
        <van-tab title="销售" name="sale">
          <div class="tab-content">
            <div class="section-header">
              <van-icon name="gold-coin-o" size="20" color="#07c160" />
              <span class="section-title">销售流程</span>
            </div>
            
            <div class="process-category">
              <div class="category-header">
                <div class="category-icon">💰</div>
                <div class="category-title">销售订单</div>
              </div>
              
              <div class="step-list compact">
                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">创建订单</div>
                    <div class="step-desc">选择客户与发货仓库，添加SKU明细</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">审核订单</div>
                    <div class="step-desc">审核通过后状态</div>
                    <div class="step-status">
                      <van-tag size="small" type="success">已审核</van-tag>
                    </div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">3</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">生成出库单</div>
                    <div class="step-desc">根据销售订单生成「销售出库单」</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">4</div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">出库处理</div>
                    <div class="step-desc">出库审核后系统自动处理</div>
                    <div class="step-status">
                      <van-tag size="small" type="success">已完成</van-tag>
                      <span class="step-tag-divider">/</span>
                      <van-tag size="small" type="warning">部分出库</van-tag>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="process-category">
              <div class="category-header">
                <div class="category-icon">🔄</div>
                <div class="category-title">销售退货</div>
              </div>
              
              <div class="step-list compact">
                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">退货申请</div>
                    <div class="step-desc">选择原订单/出库单，录入退货数量</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">退货处理</div>
                    <div class="step-desc">审核通过并完成退款</div>
                    <div class="step-status">
                      <van-tag size="small" type="success">已完成</van-tag>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </van-tab>

        <!-- 3. 库存管理 -->
        <van-tab title="库存" name="stock">
          <div class="tab-content">
            <div class="section-header">
              <van-icon name="orders-o" size="20" color="#ff976a" />
              <span class="section-title">库存管理</span>
            </div>
            
            <div class="process-category">
              <div class="category-header">
                <div class="category-icon">📊</div>
                <div class="category-title">库存盘点</div>
              </div>
              
              <div class="step-list compact">
                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">开始盘点</div>
                    <div class="step-desc">选择仓库，添加待盘点SKU</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">录入数据</div>
                    <div class="step-desc">录入实盘数量，计算盘盈/盘亏</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">3</div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">完成盘点</div>
                    <div class="step-desc">系统自动修正库存为实盘数</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="process-category">
              <div class="category-header">
                <div class="category-icon">📦</div>
                <div class="category-title">库存调拨</div>
              </div>
              
              <div class="step-list compact">
                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">创建调拨</div>
                    <div class="step-desc">选择源仓库→目标仓库，添加调拨SKU/数量</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">执行调拨</div>
                    <div class="step-desc">系统自动调整两边仓库库存</div>
                    <div class="step-status">
                      <van-tag size="small" type="success">已完成</van-tag>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </van-tab>

        <!-- 4. 账款核销 -->
        <van-tab title="账款" name="settle">
          <div class="tab-content">
            <div class="section-header">
              <van-icon name="balance-list-o" size="20" color="#7232dd" />
              <span class="section-title">账款核销</span>
            </div>
            
            <div class="process-category">
              <div class="category-header">
                <div class="category-icon">💳</div>
                <div class="category-title">核销流程</div>
              </div>
              
              <div class="step-list compact">
                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">1</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">选择记录</div>
                    <div class="step-desc">在「应收/应付」页面选中待核销记录</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">2</div>
                    <div class="step-line"></div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">填写核销</div>
                    <div class="step-desc">填写本次核销金额、支付方式、日期</div>
                  </div>
                </div>

                <div class="step-item">
                  <div class="step-marker">
                    <div class="step-number">3</div>
                  </div>
                  <div class="step-content">
                    <div class="step-title">系统处理</div>
                    <div class="step-desc">提交后系统自动更新相关数据</div>
                    <div class="step-status">
                      <van-tag size="small" type="success">已结清</van-tag>
                      <span class="step-tag-divider">/</span>
                      <van-tag size="small" type="warning">未结清</van-tag>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </van-tab>

        <!-- 5. 状态速查 -->
        <van-tab title="状态" name="status">
          <div class="tab-content">
            <div class="section-header">
              <van-icon name="info-o" size="20" color="#1989fa" />
              <span class="section-title">状态速查表</span>
            </div>
            
            <div class="status-grid">
              <div v-for="s in statusList" :key="s.code" 
                   class="status-card" 
                   :class="getStatusClass(s.code)"
                   @click="showStatusDetail(s)">
                <div class="status-code">{{ s.code }}</div>
                <div class="status-name">{{ s.name }}</div>
                <div class="status-scene">{{ s.scene }}</div>
              </div>
            </div>

            <div class="note-section">
              <div class="note-header">
                <van-icon name="info-o" size="16" color="#1989fa" />
                <span>说明</span>
              </div>
              <div class="note-content">
                <p>• 状态码在所有业务单据中通用</p>
                <p>• 绿色状态表示正常完成</p>
                <p>• 橙色状态表示部分处理或待处理</p>
                <p>• 红色状态表示异常或已取消</p>
              </div>
            </div>
          </div>
        </van-tab>
      </van-tabs>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { showDialog } from 'vant'

const router = useRouter()

/* 标签页当前高亮 */
const activeTab = ref('purchase')

/* 通用状态码速查表 */
const statusList = [
  { code: 1, name: '待审核', scene: '订单/出入库单/退货单' },
  { code: 2, name: '已审核', scene: '订单/出入库单/退货单' },
  { code: 3, name: '部分处理', scene: '订单（部分出入库）' },
  { code: 4, name: '已完成', scene: '订单/出入库单/调拨单' },
  { code: 5, name: '已取消', scene: '订单/出入库单/退货单/调拨单' },
  { code: 6, name: '已完成', scene: '退货单（含退款）' }
]

const getStatusClass = (code) => {
  switch(code) {
    case 1: return 'status-pending'
    case 2: return 'status-approved'
    case 3: return 'status-partial'
    case 4: return 'status-completed'
    case 5: return 'status-cancelled'
    case 6: return 'status-refund'
    default: return ''
  }
}

const showStatusDetail = (status) => {
  showDialog({
    title: `状态 ${status.code} - ${status.name}`,
    message: `适用场景：${status.scene}\n\n该状态表示${getStatusDesc(status.code)}`,
    confirmButtonText: '知道了',
    showCancelButton: false
  })
}

const getStatusDesc = (code) => {
  const descMap = {
    1: '单据已提交，等待管理员审核',
    2: '单据已通过审核，可以进行后续操作',
    3: '单据已部分处理完成，还需继续处理',
    4: '单据已全部处理完成',
    5: '单据已被取消或驳回',
    6: '退货单已完成且退款已处理'
  }
  return descMap[code] || '该状态为系统通用状态'
}
</script>

<style scoped lang="scss">
.operation-guide {
  background: #f7f8fa;
  min-height: 100vh;
}

/* 导航栏样式 - 参照 Profile.vue */
:deep(.van-nav-bar) {
  background: #fff;
  
  .van-nav-bar__title {
    color: #323233;
    font-weight: 600;
    font-size: 16px;
  }
  
  .van-nav-bar__text {
    color: #323233;
    font-size: 14px;
  }
  
  .van-nav-bar__arrow {
    color: #323233;
    font-size: 18px;
  }
}

.guide-content {
  padding-top: 46px;
  padding-bottom: env(safe-area-inset-bottom);
}

.tab-content {
  padding: 16px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid #ebedf0;
  
  .section-title {
    font-size: 18px;
    font-weight: 600;
    color: #323233;
  }
}

.process-category {
  background: white;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.category-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  
  .category-icon {
    font-size: 24px;
  }
  
  .category-title {
    font-size: 16px;
    font-weight: 600;
    color: #323233;
  }
}

.step-list {
  &.compact {
    .step-item {
      margin-bottom: 12px;
      
      &:last-child {
        margin-bottom: 0;
      }
    }
    
    .step-line {
      height: calc(100% + 12px);
    }
  }
}

.step-item {
  display: flex;
  margin-bottom: 16px;
  
  &:last-child {
    margin-bottom: 0;
    
    .step-line {
      display: none;
    }
  }
}

.step-marker {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-right: 12px;
  position: relative;
}

.step-number {
  width: 24px;
  height: 24px;
  background: linear-gradient(135deg, #1989fa, #3296fa);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 12px;
  z-index: 1;
}

.step-line {
  width: 2px;
  height: calc(100% + 16px);
  background: linear-gradient(to bottom, #1989fa, #ebedf0);
  position: absolute;
  top: 24px;
}

.step-content {
  flex: 1;
  padding-bottom: 4px;
}

.step-title {
  font-size: 15px;
  font-weight: 500;
  color: #323233;
  margin-bottom: 4px;
}

.step-desc {
  font-size: 13px;
  color: #969799;
  line-height: 1.4;
  margin-bottom: 8px;
}

.step-status {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.step-tag-divider {
  color: #969799;
  font-size: 12px;
}

.system-action {
  background: #f7f8fa;
  border-radius: 8px;
  padding: 12px;
  margin-top: 8px;
}

.action-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #646566;
  padding: 4px 0;
  
  &:first-child {
    padding-top: 0;
  }
  
  &:last-child {
    padding-bottom: 0;
  }
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.status-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  transition: all 0.2s ease;
  
  &:active {
    transform: scale(0.98);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
  }
  
  &.status-pending {
    border-top: 3px solid #ffba00;
  }
  
  &.status-approved {
    border-top: 3px solid #07c160;
  }
  
  &.status-partial {
    border-top: 3px solid #ff976a;
  }
  
  &.status-completed {
    border-top: 3px solid #1989fa;
  }
  
  &.status-cancelled {
    border-top: 3px solid #ee0a24;
  }
  
  &.status-refund {
    border-top: 3px solid #2db7f5;
  }
}

.status-code {
  font-size: 24px;
  font-weight: 700;
  color: #323233;
  margin-bottom: 4px;
}

.status-name {
  font-size: 15px;
  font-weight: 600;
  color: #323233;
  margin-bottom: 4px;
}

.status-scene {
  font-size: 12px;
  color: #969799;
  line-height: 1.4;
}

.note-section {
  background: #f7f8fa;
  border-radius: 12px;
  padding: 16px;
}

.note-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  
  span {
    font-size: 15px;
    font-weight: 600;
    color: #323233;
  }
}

.note-content {
  p {
    font-size: 13px;
    color: #646566;
    line-height: 1.5;
    margin-bottom: 6px;
    
    &:last-child {
      margin-bottom: 0;
    }
  }
}

/* 标签页样式优化 */
:deep(.van-tabs__wrap) {
  background: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  
  .van-tab {
    font-size: 14px;
    font-weight: 500;
    color: #969799;
  }
  
  .van-tab--active {
    color: #1989fa;
  }
  
  .van-tabs__line {
    background-color: #1989fa;
    height: 2px;
  }
}

/* 深色模式适配 */
@media (prefers-color-scheme: dark) {
  .operation-guide {
    background: #1a1a1a;
  }
  
  .section-title,
  .category-title,
  .step-title,
  .status-code,
  .status-name,
  .note-header span {
    color: #e1e1e1;
  }
  
  .step-desc,
  .status-scene,
  .note-content p {
    color: #a0a0a0;
  }
  
  .process-category,
  .status-card,
  .note-section {
    background: #2a2a2a;
  }
  
  .system-action {
    background: #3a3a3a;
  }
  
  :deep(.van-nav-bar) {
    background: #2a2a2a;
    
    .van-nav-bar__title,
    .van-nav-bar__text,
    .van-nav-bar__arrow {
      color: #e1e1e1;
    }
  }
  
  :deep(.van-tabs__wrap) {
    background: #2a2a2a;
    
    .van-tab {
      color: #a0a0a0;
    }
    
    .van-tab--active {
      color: #1989fa;
    }
  }
}
</style>