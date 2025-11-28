// src/utils/format.js

/**
 * 格式化日期为 YYYY-MM-DD
 * @param {string|Date} date - 日期字符串或日期对象
 * @returns {string} 格式化后的日期字符串
 */
export function formatDate(date) {
  if (!date) return ''
  
  try {
    const d = new Date(date)
    // 处理无效日期
    if (isNaN(d.getTime())) return ''
    
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  } catch (error) {
    console.error('日期格式化错误:', error)
    return ''
  }
}

/**
 * 格式化日期时间为 YYYY-MM-DD HH:mm:ss
 * @param {string|Date} dateTime - 日期时间字符串或日期对象
 * @returns {string} 格式化后的日期时间字符串
 */
export function formatDateTime(dateTime) {
  if (!dateTime) return ''
  
  try {
    const d = new Date(dateTime)
    // 处理无效日期
    if (isNaN(d.getTime())) return ''
    
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    const seconds = String(d.getSeconds()).padStart(2, '0')
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`
  } catch (error) {
    console.error('日期时间格式化错误:', error)
    return ''
  }
}

/**
 * 格式化价格，保留两位小数
 * @param {number|string} price - 价格
 * @returns {string} 格式化后的价格字符串
 */
export function formatPrice(price) {
  if (price === null || price === undefined || price === '') return '0.00'
  
  const num = Number(price)
  if (isNaN(num)) return '0.00'
  
  return num.toFixed(2)
}

/**
 * 格式化金额，添加千位分隔符
 * @param {number|string} amount - 金额
 * @returns {string} 格式化后的金额字符串
 */
export function formatAmount(amount) {
  if (amount === null || amount === undefined || amount === '') return '0.00'
  
  const num = Number(amount)
  if (isNaN(num)) return '0.00'
  
  return num.toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

/**
 * 格式化数字，添加千位分隔符
 * @param {number|string} number - 数字
 * @param {number} decimals - 小数位数
 * @returns {string} 格式化后的数字字符串
 */
export function formatNumber(number, decimals = 0) {
  if (number === null || number === undefined || number === '') return '0'
  
  const num = Number(number)
  if (isNaN(num)) return '0'
  
  return num.toLocaleString('zh-CN', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  })
}

export default {
  formatDate,
  formatDateTime,
  formatPrice,
  formatAmount,
  formatNumber
}