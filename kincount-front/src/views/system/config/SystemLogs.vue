<template>
  <div class="system-logs">
    <!-- 导航栏 -->
    <van-nav-bar 
      title="系统日志" 
      left-text="返回" 
      left-arrow 
      fixed
      placeholder
      @click-left="$router.back()" 
    >
      <template #right>
        <van-button size="small" type="danger" @click="clearLogs">
          清空日志
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 内容区 -->
    <div class="logs-content">
      <!-- 日志刷新按钮 -->
      <div class="refresh-bar">
        <van-button 
          type="primary" 
          size="small" 
          icon="replay" 
          :loading="loading"
          @click="refreshLogs"
        >
          刷新日志
        </van-button>
        <span class="log-count">共 {{ logs.length }} 条日志</span>
      </div>

      <!-- 日志列表 -->
      <div class="log-list" v-if="logs.length > 0">
        <div 
          v-for="(log, index) in logs" 
          :key="index"
          class="log-item"
          :class="getLogLevelClass(log)"
        >
          <div class="log-header">
            <span class="log-time">{{ extractTime(log) }}</span>
            <van-tag :type="getLogLevelType(log)" size="small">
              {{ extractLogLevel(log) }}
            </van-tag>
          </div>
          <div class="log-content">{{ extractContent(log) }}</div>
        </div>
      </div>

      <!-- 空状态 -->
      <van-empty 
        v-else-if="!loading"
        description="暂无日志记录" 
        image="description"
      />
      
      <!-- 加载状态 -->
      <van-loading v-if="loading" class="loading-center" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'
import { getSystemLogs, clearSystemLogs } from '@/api/system'

const router = useRouter()

// 日志列表
const logs = ref([])
// 加载状态
const loading = ref(false)

// 获取系统日志
const fetchLogs = async () => {
  loading.value = true
  try {
    const response = await getSystemLogs()
    if (response && response.data) {
      logs.value = response.data
    }
  } catch (error) {
    console.error('获取系统日志失败:', error)
    showToast('获取系统日志失败，请检查服务器连接')
    // 添加模拟日志数据，以便用户了解页面功能
    logs.value = [
      '[2023-12-29T10:00:00.000000+08:00] INFO: 系统启动',
      '[2023-12-29T10:01:00.000000+08:00] ERROR: 无法连接到后端服务',
      '[2023-12-29T10:02:00.000000+08:00] WARNING: 请检查服务器状态'
    ]
  } finally {
    loading.value = false
  }
}

// 刷新日志
const refreshLogs = () => {
  fetchLogs()
}

// 清空日志
const clearLogsConfirm = async () => {
  try {
    await showConfirmDialog({
      title: '确认清空',
      message: '确定要清空所有系统日志吗？此操作不可恢复！'
    })
    
    try {
      await clearSystemLogs()
      logs.value = []
      showToast('日志已清空')
    } catch (error) {
      console.error('清空日志失败:', error)
      showToast('清空日志失败，请检查服务器连接')
    }
  } catch (error) {
    // 用户取消操作
  }
}

// 提取日志时间
const extractTime = (log) => {
  const timeMatch = log.match(/\[(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}\+?\d{0,2}:?\d{0,2})\]/)
  if (timeMatch) {
    const date = new Date(timeMatch[1])
    return date.toLocaleString()
  }
  return ''
}

// 提取日志级别
const extractLogLevel = (log) => {
  if (log.includes('ERROR') || log.includes('error')) return 'ERROR'
  if (log.includes('WARNING') || log.includes('warning')) return 'WARNING'
  if (log.includes('INFO') || log.includes('info')) return 'INFO'
  if (log.includes('DEBUG') || log.includes('debug')) return 'DEBUG'
  return 'LOG'
}

// 提取日志内容
const extractContent = (log) => {
  // 移除时间戳和日志级别，只保留内容
  let content = log.replace(/^\[.*?\]\s*\w+\.\w+\.\w+\s*:\s*/, '')
  content = content.replace(/^\[.*?\]\s*/, '')
  return content.trim()
}

// 获取日志级别类型
const getLogLevelType = (log) => {
  const level = extractLogLevel(log)
  switch (level) {
    case 'ERROR': return 'danger'
    case 'WARNING': return 'warning'
    case 'INFO': return 'primary'
    case 'DEBUG': return 'default'
    default: return 'default'
  }
}

// 获取日志级别样式类
const getLogLevelClass = (log) => {
  const level = extractLogLevel(log)
  return `log-${level.toLowerCase()}`
}

// 页面加载时获取日志
onMounted(() => {
  fetchLogs()
})
</script>

<style scoped lang="scss">
.system-logs {
  background: #f7f8fa;
  min-height: 100vh;
}

.logs-content {
  padding: 12px;
  padding-bottom: 20px;
}

.refresh-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding: 12px;
  background: white;
  border-radius: 8px;
}

.log-count {
  font-size: 14px;
  color: #969799;
}

.log-list {
  background: white;
  border-radius: 8px;
  overflow: hidden;
}

.log-item {
  padding: 12px 16px;
  border-bottom: 1px solid #ebedf0;
  
  &:last-child {
    border-bottom: none;
  }
  
  &.log-error {
    background-color: rgba(250, 50, 50, 0.05);
  }
  
  &.log-warning {
    background-color: rgba(255, 156, 0, 0.05);
  }
  
  &.log-info {
    background-color: rgba(25, 137, 250, 0.05);
  }
}

.log-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.log-time {
  font-size: 12px;
  color: #969799;
}

.log-content {
  font-size: 14px;
  color: #323233;
  line-height: 1.5;
  word-break: break-all;
}

.loading-center {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}
</style>