// src/api/dashboard.js
import request from '@/utils/request'

// 数据概览
export function getOverview() {
  return request.get('/dashboard/overview')
}

// 统计信息
export function getStatistics() {
  return request.get('/dashboard/statistics')
}

// 预警信息
export function getAlerts() {
  return request.get('/dashboard/alerts')
}

// 快捷操作
export function getQuickActions() {
  return request.get('/dashboard/quick-actions')
}