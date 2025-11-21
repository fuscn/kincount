// src/api/auth.js
import request from '@/utils/request'

// 登录
export function login(data) {
  return request.post('/auth/login', data)
}

// 退出
export function logout() {
  return request.post('/auth/logout')
}

// 刷新 token
export function refreshToken() {
  return request.post('/auth/refresh')
}

// 获取当前用户信息
export function getUserInfo() {
  return request.get('/auth/userinfo')
}

// 修改资料
export function updateProfile(data) {
  return request.put('/auth/profile', data)
}

// 修改密码
export function changePassword(data) {
  return request.put('/auth/password', data)
}