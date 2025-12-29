<template>
  <div class="system-config">
    <!-- 导航栏 -->
    <van-nav-bar 
      title="系统配置" 
      left-text="返回" 
      left-arrow 
      fixed
      placeholder
      @click-left="$router.back()" 
    />

    <!-- 内容区 -->
    <div class="config-content">
      <!-- 系统信息 -->
      <van-cell-group title="系统管理" class="config-group" v-if="canViewSystemInfo || canViewSystemLogs">
        <van-cell 
          title="系统信息" 
          label="查看系统状态和环境信息"
          is-link 
          @click="goToPage('/system/config/info')"
          v-if="canViewSystemInfo"
        />
        
        <van-cell 
          title="系统日志" 
          label="查看系统运行日志"
          is-link 
          @click="goToPage('/system/logs')"
          v-if="canViewSystemLogs"
        />
      </van-cell-group>
      
      <!-- 操作指南 - 对所有用户可见 -->
      <van-cell-group title="帮助" class="config-group">
        <van-cell 
          title="操作指南" 
          label="查看系统操作流程指南"
          is-link 
          @click="goToPage('/system/operationflowguide')"
        />
      </van-cell-group>

      <!-- 基础配置 -->
      <van-cell-group title="基础配置" class="config-group" v-if="canViewBasicConfig">
        <van-cell 
          title="公司信息" 
          label="配置公司基本信息"
          is-link 
          @click="goToConfig('company')"
        />
        
        <van-cell 
          title="界面设置" 
          label="配置系统界面显示"
          is-link 
          @click="goToConfig('ui')"
        />
      </van-cell-group>

      <!-- 业务配置 -->
      <van-cell-group title="业务配置" class="config-group" v-if="canViewBusinessConfig">
        <van-cell 
          title="库存管理" 
          label="配置库存预警和盘点规则"
          is-link 
          @click="goToConfig('stock')"
        />
        
        <van-cell 
          title="订单管理" 
          label="配置订单处理规则"
          is-link 
          @click="goToConfig('order')"
        />
        
        <van-cell 
          title="打印设置" 
          label="配置单据打印模板"
          is-link 
          @click="goToConfig('print')"
        />
      </van-cell-group>

      <!-- 安全配置 -->
      <van-cell-group title="安全配置" class="config-group" v-if="canViewSecurityConfig">
        <van-cell 
          title="密码策略" 
          label="配置密码安全规则"
          is-link 
          @click="goToConfig('password')"
        />
        
        <van-cell 
          title="会话管理" 
          label="配置用户会话规则"
          is-link 
          @click="goToConfig('session')"
        />
      </van-cell-group>

      <!-- 数据管理 -->
      <van-cell-group title="数据管理" class="config-group" v-if="canViewDataConfig">
        <van-cell 
          title="数据备份" 
          label="配置数据备份策略"
          is-link 
          @click="goToConfig('backup')"
        />
      </van-cell-group>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/modules/auth'
import { PERM } from '@/constants/permissions'

const router = useRouter()
const authStore = useAuthStore()

// 检查权限
const canViewSystemInfo = computed(() => authStore.isAdmin)
const canViewSystemLogs = computed(() => authStore.isAdmin)
const canViewBasicConfig = computed(() => authStore.isAdmin)
const canViewBusinessConfig = computed(() => authStore.isAdmin)
const canViewSecurityConfig = computed(() => authStore.isAdmin)
const canViewDataConfig = computed(() => authStore.isAdmin)

// 跳转到页面
const goToPage = (path) => {
  router.push(path)
}

// 跳转到配置页面
const goToConfig = (type) => {
  router.push(`/system/config/${type}`)
}
</script>

<style scoped lang="scss">
.system-config {
  background: #f7f8fa;
  min-height: 100vh;
}

.config-content {
  padding: 12px;
  padding-bottom: 20px;
}

.config-group {
  margin-bottom: 12px;
  border-radius: 8px;
  overflow: hidden;
}

/* 标题样式 */
:deep(.van-cell-group__title) {
  padding: 12px 16px 8px;
  font-weight: 600;
  color: #323233;
  font-size: 14px;
}

/* 单元格样式 */
:deep(.van-cell) {
  padding: 14px 16px;
  align-items: center;
}

:deep(.van-cell__title) {
  font-weight: 500;
  font-size: 15px;
}

:deep(.van-cell__label) {
  margin-top: 4px;
  font-size: 12px;
  color: #969799;
}
</style>