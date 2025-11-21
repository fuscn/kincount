import dayjs from 'dayjs'
import 'dayjs/locale/zh-cn'
import relativeTime from 'dayjs/plugin/relativeTime'

dayjs.extend(relativeTime)
dayjs.locale('zh-cn')

// 常用格式
export const DATE_FMT = 'YYYY-MM-DD'
export const TIME_FMT = 'YYYY-MM-DD HH:mm:ss'

// 今天
export function today() {
  return dayjs().format(DATE_FMT)
}

// 昨天
export function yesterday() {
  return dayjs().add(-1, 'day').format(DATE_FMT)
}

// 月初
export function monthFirst() {
  return dayjs().startOf('month').format(DATE_FMT)
}

// 友好时间
export function fromNow(date) {
  return dayjs(date).from()
}

// 格式化
export function fmt(date, template = DATE_FMT) {
  return dayjs(date).format(template)
}

export default dayjs