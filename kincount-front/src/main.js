import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import pinia from './store'
import 'vant/lib/index.css'          // 基础样式
// 函数式组件的样式必须单独引入
import 'vant/es/toast/style';
import 'vant/es/dialog/style';
import 'vant/es/notify/style';

import { Lazyload } from 'vant'
import components from '@/components'
import { permission } from '@/directives/permission'
import '@/styles/index.scss'  // 引入移动端样式


const app = createApp(App)

app.directive('perm', permission)
app.use(components)
app.use(pinia)
app.use(router)
app.use(Lazyload)                    // 图片懒加载
app.mount('#app')