// 手机号
export function isMobile(s) {
  return /^1[3-9]\d{9}$/.test(s)
}

// 金额 2 位小数
export function isMoney(s) {
  return /^\d+(\.\d{1,2})?$/.test(s)
}

// 正整数
export function isInt(s) {
  return /^[1-9]\d*$/.test(s)
}

// 邮箱
export function isEmail(s) {
  return /^[\w.-]+@[\w.-]+\.\w+$/.test(s)
}

// 身份证号
export function isIDCard(s) {
  return /(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/.test(s)
}