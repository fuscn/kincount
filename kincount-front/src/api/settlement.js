// src/api/settlement.js
import request from '@/utils/request'

// 核销记录列表
export function getSettlementList(params) {
  return request({
    url: '/account/settlement',
    method: 'get',
    params
  })
}

// 核销记录详情
export function getSettlementDetail(id) {
  return request({
    url: `/account/settlement/${id}`,
    method: 'get'
  })
}

// 创建核销记录（单个）
export function createSettlement(data) {
  return request({
    url: '/account/settlement',
    method: 'post',
    data
  })
}

// 批量核销
export function batchCreateSettlement(data) {
  return request({
    url: '/account/settlement/batch',
    method: 'post',
    data
  })
}

// 根据账款ID获取核销记录
export function getSettlementsByAccountId(accountId) {
  return request({
    url: `/account/settlement/account/${accountId}`,
    method: 'get'
  })
}

// 根据财务收支ID获取核销记录
export function getSettlementsByFinancialId(financialId) {
  return request({
    url: `/account/settlement/financial/${financialId}`,
    method: 'get'
  })
}

// 获取可核销的账款列表
export function getSettableAccounts(params) {
  return request({
    url: '/account/settlement/settleable',
    method: 'get',
    params
  })
}

// 获取核销统计
export function getSettlementStatistics(params) {
  return request({
    url: '/account/settlement/statistics',
    method: 'get',
    params
  })
}

// 取消核销（需要高级权限）
export function cancelSettlement(id) {
  return request({
    url: `/account/settlement/cancel/${id}`,
    method: 'post'
  })
}