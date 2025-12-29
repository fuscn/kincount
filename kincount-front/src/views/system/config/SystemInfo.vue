<template>
  <div class="system-info">
    <!-- 导航栏 -->
    <van-nav-bar 
      title="系统信息" 
      left-text="返回" 
      left-arrow 
      fixed
      placeholder
      @click-left="$router.back()" 
    />

    <!-- 内容区 -->
    <div class="info-content">
      <!-- 系统状态卡片 -->
      <van-cell-group title="系统状态" class="status-group">
        <van-cell center>
          <template #title>
            <span class="status-title">数据库连接</span>
          </template>
          <template #right-icon>
            <van-tag :type="systemStatus.database ? 'success' : 'danger'">
              {{ systemStatus.database ? '正常' : '异常' }}
            </van-tag>
          </template>
        </van-cell>
        
        <van-cell center>
          <template #title>
            <span class="status-title">磁盘使用情况</span>
          </template>
          <template #label>
            <div class="disk-info">
              <span>已用: {{ diskInfo.usage }}</span>
              <span class="disk-divider">|</span>
              <span>剩余: {{ diskInfo.free }}</span>
              <span class="disk-divider">|</span>
              <span>总计: {{ diskInfo.total }}</span>
            </div>
          </template>
          <template #right-icon>
            <van-tag :type="getDiskStatusType(diskInfo.usage)">
              {{ getDiskStatusText(diskInfo.usage) }}
            </van-tag>
          </template>
        </van-cell>
        
        <van-cell center>
          <template #title>
            <span class="status-title">系统时间</span>
          </template>
          <template #right-icon>
            <span class="time-text">{{ formatTime(systemStatus.timestamp) }}</span>
          </template>
        </van-cell>
      </van-cell-group>

      <!-- 环境信息卡片 -->
      <van-cell-group title="环境信息" class="env-group">
        <van-cell title="PHP版本" :value="systemInfo.php_version" />
        <van-cell title="ThinkPHP版本" :value="systemInfo.thinkphp_version" />
        <van-cell title="MySQL版本" :value="systemInfo.mysql_version" />
        <van-cell title="上传文件大小限制" :value="systemInfo.upload_max_size" />
        <van-cell title="公司名称" :value="systemInfo.company_name" />
      </van-cell-group>

      <!-- 前端信息卡片 -->
      <van-cell-group title="前端信息" class="frontend-group">
        <van-cell title="Vant版本" :value="frontendInfo.vantVersion" />
        <van-cell title="Vue版本" :value="frontendInfo.vueVersion" />
        <van-cell title="编译时间" :value="frontendInfo.buildTime" />
      </van-cell-group>

      <!-- 系统操作 -->
      <van-cell-group title="系统操作" class="action-group">
        <van-cell 
          title="刷新状态" 
          is-link 
          @click="refreshSystemStatus"
          :value="loading ? '刷新中...' : ''"
        />
        <van-cell 
          title="查看系统日志" 
          is-link 
          @click="viewSystemLogs"
        />
        <van-cell 
          title="清空系统日志" 
          is-link 
          @click="clearLogsConfirm"
        />
      </van-cell-group>

      <!-- 系统配置 -->
      <van-cell-group title="系统配置" class="config-group">
        <van-cell 
          title="基础配置" 
          is-link 
          @click="goToConfig('basic')"
        />
        <van-cell 
          title="业务规则" 
          is-link 
          @click="goToConfig('business')"
        />
        <van-cell 
          title="安全设置" 
          is-link 
          @click="goToConfig('security')"
        />
      </van-cell-group>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'
import { getSystemInfo, getSystemStatus, clearSystemLogs } from '@/api/system'

const router = useRouter()

// 系统状态
const systemStatus = reactive({
  database: false,
  disk: {},
  timestamp: 0
})

// 磁盘信息
const diskInfo = reactive({
  total: '0 GB',
  free: '0 GB',
  usage: '0 %'
})

// 系统信息
const systemInfo = reactive({
  php_version: '',
  thinkphp_version: '',
  mysql_version: '',
  upload_max_size: '',
  company_name: ''
})

// 前端信息
const frontendInfo = reactive({
  vantVersion: '',
  vueVersion: '',
  buildTime: ''
})

// 加载状态
const loading = ref(false)

// 获取系统状态
const fetchSystemStatus = async () => {
  try {
    const response = await getSystemStatus()
    if (response && response.data) {
      Object.assign(systemStatus, response.data)
      if (response.data.disk) {
        Object.assign(diskInfo, response.data.disk)
      }
    }
  } catch (error) {
    console.error('获取系统状态失败:', error)
    // 不显示toast，避免过多错误提示
    Object.assign(systemStatus, { database: false, timestamp: Date.now() })
    Object.assign(diskInfo, { total: '未知', free: '未知', usage: '未知' })
  }
}

// 获取系统信息
const fetchSystemInfo = async () => {
  try {
    const response = await getSystemInfo()
    if (response && response.data) {
      Object.assign(systemInfo, response.data)
    }
  } catch (error) {
    console.error('获取系统信息失败:', error)
    // 不显示toast，避免过多错误提示
    Object.assign(systemInfo, {
      php_version: '未知',
      thinkphp_version: '未知',
      mysql_version: '未知',
      upload_max_size: '未知',
      company_name: '未知'
    })
  }
}

// 刷新系统状态
const refreshSystemStatus = async () => {
  loading.value = true
  try {
    await Promise.all([
      fetchSystemStatus(),
      fetchSystemInfo()
    ])
    
    // 检查是否成功获取到数据
    if (systemStatus.value.database || systemInfo.php_version !== '未知') {
      showToast('刷新成功')
    } else {
      showToast('无法连接到后端服务，请检查服务器状态')
    }
  } catch (error) {
    showToast('刷新失败，请检查网络连接')
  } finally {
    loading.value = false
  }
}

// 查看系统日志
const viewSystemLogs = () => {
  router.push('/system/logs')
}

// 清空系统日志
const clearLogsConfirm = async () => {
  try {
    await showConfirmDialog({
      title: '确认清空',
      message: '确定要清空所有系统日志吗？此操作不可恢复！'
    })
    
    try {
      await clearSystemLogs()
      showToast('日志已清空')
    } catch (error) {
      console.error('清空日志失败:', error)
      showToast('清空日志失败，请检查服务器连接')
    }
  } catch (error) {
    // 用户取消操作
  }
}

// 获取磁盘状态类型
const getDiskStatusType = (usage) => {
  const percentage = parseFloat(usage)
  if (percentage < 70) return 'success'
  if (percentage < 90) return 'warning'
  return 'danger'
}

// 获取磁盘状态文本
const getDiskStatusText = (usage) => {
  const percentage = parseFloat(usage)
  if (percentage < 70) return '正常'
  if (percentage < 90) return '警告'
  return '危险'
}

// 格式化时间
const formatTime = (timestamp) => {
  if (!timestamp) return '--'
  const date = new Date(timestamp * 1000)
  return date.toLocaleString()
}

// 跳转到配置页面
const goToConfig = (type) => {
  router.push(`/system/config/${type}`)
}

// 获取前端信息
const fetchFrontendInfo = () => {
  try {
    // 直接定义版本信息
    frontendInfo.vantVersion = '4.9.21'
    frontendInfo.vueVersion = '3.5.22'
    
    // 获取编译时间（使用构建时间戳）
    frontendInfo.buildTime = import.meta.env.VITE_APP_BUILD_TIME || new Date().toLocaleString()
  } catch (error) {
    console.error('获取前端信息失败:', error)
    frontendInfo.vantVersion = '未知'
    frontendInfo.vueVersion = '未知'
    frontendInfo.buildTime = '未知'
  }
}

// 页面加载时获取数据
onMounted(() => {
  fetchSystemStatus()
  fetchSystemInfo()
  fetchFrontendInfo()
})
</script>

<style scoped lang="scss">
.system-info {
  background: #f7f8fa;
  min-height: 100vh;
}

.info-content {
  padding: 12px;
  padding-bottom: 20px;
}

.status-group, .env-group, .frontend-group, .action-group, .config-group {
  margin-bottom: 12px;
  border-radius: 8px;
  overflow: hidden;
}

.status-title {
  font-weight: 500;
}

.disk-info {
  display: flex;
  align-items: center;
  font-size: 12px;
  color: #969799;
  margin-top: 4px;
}

.disk-divider {
  margin: 0 4px;
}

.time-text {
  font-size: 13px;
  color: #969799;
}

/* 标签样式 */
:deep(.van-cell-group__title) {
  padding: 12px 16px 8px;
  font-weight: 600;
  color: #323233;
  font-size: 14px;
}

/* 单元格样式 */
:deep(.van-cell) {
  padding: 12px 16px;
}

:deep(.van-cell__value) {
  color: #646566;
}

/* 状态标签样式 */
:deep(.van-tag) {
  font-weight: 500;
}
</style>