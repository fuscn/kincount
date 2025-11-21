// src/api/utils.js
import request from '@/utils/request'

// 生成编号
export function generateNumber(type) {
  return request.get(`/utils/number/generate/${type}`)
}

// 上传图片
export function uploadImage(file) {
  const formData = new FormData()
  formData.append('file', file)
  return request.post('/utils/upload/image', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

// 上传文件
export function uploadFile(file) {
  const formData = new FormData()
  formData.append('file', file)
  return request.post('/utils/upload/file', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

// 省市区
export function getRegions() {
  return request.get('/utils/regions')
}

// 计量单位
export function getUnits() {
  return request.get('/utils/units')
}

// 统一选项下拉
export function getOptions(module) {
  return request.get(`/utils/options/${module}`)
}