// src/utils/common.js

/**
 * 金额格式化
 * @param {number} amount 金额
 * @returns {string} 格式化后的金额字符串
 */
export const formatAmount = (amount) => {
  if (amount === null || amount === undefined) return '0.00'
  const num = parseFloat(amount)
  if (isNaN(num)) return '0.00'
  return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

/**
 * 数字格式化，用于显示数量等
 * @param {number} num 数字
 * @returns {string} 格式化后的数字字符串
 */
export const formatNumber = (num) => {
  if (num === null || num === undefined) return '0'
  const number = parseFloat(num)
  if (isNaN(number)) return '0'
  return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

/**
 * 获取文件扩展名
 * @param {string} filename 文件名
 * @returns {string} 扩展名
 */
export const getFileExtension = (filename) => {
  if (!filename) return ''
  return filename.split('.').pop().toLowerCase()
}

/**
 * 防抖函数
 * @param {Function} func 要防抖的函数
 * @param {number} wait 等待时间
 * @returns {Function} 防抖后的函数
 */
export const debounce = (func, wait) => {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

/**
 * 节流函数
 * @param {Function} func 要节流的函数
 * @param {number} limit 时间限制
 * @returns {Function} 节流后的函数
 */
export const throttle = (func, limit) => {
  let inThrottle
  return function (...args) {
    if (!inThrottle) {
      func.apply(this, args)
      inThrottle = true
      setTimeout(() => inThrottle = false, limit)
    }
  }
}

/**
 * 深拷贝
 * @param {*} obj 要拷贝的对象
 * @returns {*} 拷贝后的对象
 */
export const deepClone = (obj) => {
  if (obj === null || typeof obj !== 'object') return obj
  if (obj instanceof Date) return new Date(obj.getTime())
  if (obj instanceof Array) return obj.map(item => deepClone(item))
  if (obj instanceof Object) {
    const clonedObj = {}
    Object.keys(obj).forEach(key => {
      clonedObj[key] = deepClone(obj[key])
    })
    return clonedObj
  }
}

/**
 * 生成随机字符串
 * @param {number} length 字符串长度
 * @returns {string} 随机字符串
 */
export const generateRandomString = (length = 8) => {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
  let result = ''
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  return result
}

/**
 * 判断是否为空
 * @param {*} value 要判断的值
 * @returns {boolean} 是否为空
 */
export const isEmpty = (value) => {
  if (value === null || value === undefined) return true
  if (typeof value === 'string') return value.trim() === ''
  if (Array.isArray(value)) return value.length === 0
  if (typeof value === 'object') return Object.keys(value).length === 0
  return false
}

/**
 * 生成订单号/编号
 * @param {string} prefix 前缀
 * @returns {string} 生成的编号
 */
export const generateOrderNo = (prefix = 'ORD') => {
  const timestamp = new Date().getTime()
  const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0')
  return `${prefix}${timestamp}${random}`
}

/**
 * 下载文件
 * @param {string} url 文件URL
 * @param {string} filename 文件名
 */
export const downloadFile = (url, filename) => {
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

/**
 * 复制文本到剪贴板
 * @param {string} text 要复制的文本
 * @returns {Promise<boolean>} 是否复制成功
 */
export const copyToClipboard = async (text) => {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text)
      return true
    } else {
      // 回退方案
      const textArea = document.createElement('textarea')
      textArea.value = text
      textArea.style.position = 'fixed'
      textArea.style.left = '-999999px'
      textArea.style.top = '-999999px'
      document.body.appendChild(textArea)
      textArea.focus()
      textArea.select()
      const successful = document.execCommand('copy')
      document.body.removeChild(textArea)
      return successful
    }
  } catch (err) {
    console.error('复制失败:', err)
    return false
  }
}

/**
 * 获取图片的Base64
 * @param {File} file 图片文件
 * @returns {Promise<string>} Base64字符串
 */
export const getImageBase64 = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.readAsDataURL(file)
    reader.onload = () => resolve(reader.result)
    reader.onerror = error => reject(error)
  })
}

/**
 * 格式化文件大小
 * @param {number} bytes 字节数
 * @returns {string} 格式化后的文件大小
 */
export const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

/**
 * 获取URL参数
 * @param {string} name 参数名
 * @returns {string|null} 参数值
 */
export const getUrlParam = (name) => {
  const urlParams = new URLSearchParams(window.location.search)
  return urlParams.get(name)
}

/**
 * 对象转URL参数字符串
 * @param {Object} obj 对象
 * @returns {string} URL参数字符串
 */
export const objectToUrlParams = (obj) => {
  const params = new URLSearchParams()
  Object.keys(obj).forEach(key => {
    if (obj[key] !== null && obj[key] !== undefined) {
      params.append(key, obj[key])
    }
  })
  return params.toString()
}

/**
 * 延迟函数
 * @param {number} ms 延迟毫秒数
 * @returns {Promise} Promise
 */
export const delay = (ms) => {
  return new Promise(resolve => setTimeout(resolve, ms))
}

/**
 * 生成UUID
 * @returns {string} UUID
 */
export const generateUUID = () => {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    const r = Math.random() * 16 | 0
    const v = c === 'x' ? r : (r & 0x3 | 0x8)
    return v.toString(16)
  })
}

/**
 * 数组去重
 * @param {Array} array 数组
 * @param {string} key 对象键名（可选）
 * @returns {Array} 去重后的数组
 */
export const uniqueArray = (array, key = null) => {
  if (key) {
    const seen = new Set()
    return array.filter(item => {
      const value = item[key]
      if (seen.has(value)) {
        return false
      }
      seen.add(value)
      return true
    })
  } else {
    return [...new Set(array)]
  }
}

/**
 * 数字千分位格式化
 * @param {number} num 数字
 * @returns {string} 格式化后的字符串
 */
export const thousandsFormat = (num) => {
  if (num === null || num === undefined) return ''
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

/**
 * 手机号脱敏
 * @param {string} phone 手机号
 * @returns {string} 脱敏后的手机号
 */
export const maskPhone = (phone) => {
  if (!phone) return ''
  return phone.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
}

/**
 * 邮箱脱敏
 * @param {string} email 邮箱
 * @returns {string} 脱敏后的邮箱
 */
export const maskEmail = (email) => {
  if (!email) return ''
  const [name, domain] = email.split('@')
  if (name.length <= 2) {
    return name.charAt(0) + '***@' + domain
  }
  return name.charAt(0) + '***' + name.charAt(name.length - 1) + '@' + domain
}
export const isArray = (value) => Array.isArray(value)
export const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value)
export const isString = (value) => typeof value === 'string'
export const isNumber = (value) => typeof value === 'number' && !isNaN(value)
export const isBoolean = (value) => typeof value === 'boolean'
export const isFunction = (value) => typeof value === 'function'
export const isNil = (value) => value === null || value === undefined