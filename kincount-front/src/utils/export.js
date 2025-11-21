import { showToast, showSuccessToast, showFailToast, showLoadingToast, closeToast } from 'vant'

/**
 * 下载后端返回的 Blob
 * @param {Blob} blob        后端流
 * @param {String} fileName  文件名（含扩展名）
 */
export function downloadBlob(blob, fileName) {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.style.display = 'none'
  link.href = url
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(url)
}

/**
 * 通用导出
 * @param {Function} api     后端接口（返回 Blob）
 * @param {Object}   params  查询参数
 * @param {String}   fileName
 */
export async function exportExcel(api, params, fileName = '导出.xlsx') {
  showLoadingToast({
    message: '导出中...',
    forbidClick: true,
    duration: 0
  })
  
  try {
    const blob = await api(params)
    downloadBlob(blob, fileName)
    closeToast()
    showSuccessToast('导出成功')
  } catch (e) {
    closeToast()
    showFailToast('导出失败')
  }
}