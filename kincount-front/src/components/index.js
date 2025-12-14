// src/components/index.js
import Navbar from './common/Navbar.vue'
import AppMain from './common/AppMain.vue'
import Pagination from './common/Pagination.vue'
import SearchBar from './common/SearchBar.vue'
import Upload from './common/Upload.vue'
import FloatingMenu from './common/FloatingMenu.vue'  // 新增

import ProductCard from './business/ProductCard.vue'
import StockWarning from './business/StockWarning.vue'
import ChartLine from './business/ChartLine.vue'
import ChartPie from './business/ChartPie.vue'

// import WarehouseSelect  from './business/WarehouseSelect.vue'

import SkuSelect from './business/SkuSelect.vue'
import SkuTable from './business/SkuTable.vue'
import SkuStockList from './business/SkuStockList.vue'

export { SkuSelect, SkuTable, SkuStockList }

export default {
  install(app) {
    // 通用组件
    app.component('Navbar', Navbar)
    app.component('AppMain', AppMain)
    app.component('Pagination', Pagination)
    app.component('SearchBar', SearchBar)
    app.component('Upload', Upload)
    app.component('FloatingMenu', FloatingMenu)

    // 业务组件
    app.component('ProductCard', ProductCard)
    app.component('StockWarning', StockWarning)
    app.component('ChartLine', ChartLine)
    app.component('ChartPie', ChartPie)

    // SKU 相关组件
    app.component('SkuSelect', SkuSelect)
    app.component('SkuTable', SkuTable)
    app.component('SkuStockList', SkuStockList)
  }
}