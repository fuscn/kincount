// src/api/system.js
import request from '@/utils/request'

/* ===== 用户管理 ===== */
// 用户列表
export function getUserList(params) {
  return request.get('/users', { params })
}

// 用户详情
export function getUserDetail(id) {
  return request.get(`/users/${id}`)
}

// 新增用户
export function addUser(data) {
  return request.post('/users', data)
}

// 编辑用户
export function updateUser(id, data) {
  return request.put(`/users/${id}`, data)
}

// 删除用户
export function deleteUser(id) {
  return request.delete(`/users/${id}`)
}

// 设置状态
export function setUserStatus(id, status) {
  return request.post(`/users/${id}/status`, { status })
}

// 重置密码
export function resetUserPassword(id, data) {
  return request.post(`/users/${id}/reset-password`, data)
}

/* ===== 角色管理 ===== */
// 角色列表
export function getRoleList(params) {
  return request.get('/roles', { params })
}

// 角色详情
export function getRoleDetail(id) {
  return request.get(`/roles/${id}`)
}

// 新增角色
export function addRole(data) {
  return request.post('/roles', data)
}

// 编辑角色
export function updateRole(id, data) {
  return request.put(`/roles/${id}`, data)
}

// 删除角色
export function deleteRole(id) {
  return request.delete(`/roles/${id}`)
}

// 角色下拉
export function getRoleOptions() {
  return request.get('/roles/options')
}

// 权限清单
export function getRolePermissions() {
  return request.get('/roles/permissions')
}
// 在 system.js 中添加或确认有这些函数
export function setRoleStatus(id, status) {
  return request.post(`/roles/${id}/status`, { status })
}

export function updateRolePermission(id, data) {
  return request.put(`/roles/${id}/permissions`, data)
}
/* ===== 系统配置 ===== */
// 配置列表
export function getSystemConfigs() {
  return request.get('/system/configs')
}

// 保存配置（批量）
export function saveSystemConfigs(data) {
  return request.post('/system/configs', data)
}

// 分组配置
export function getSystemConfigGroup(group) {
  return request.get(`/system/configs/${group}`)
}

// 系统信息
export function getSystemInfo() {
  return request.get('/system/info')
}

// 系统状态
export function getSystemStatus() {
  return request.get('/system/status')
}

// 系统日志
export function getSystemLogs() {
  return request.get('/system/logs')
}

// 清空日志
export function clearSystemLogs() {
  return request.delete('/system/logs')
}