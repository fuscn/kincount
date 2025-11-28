// src/store/modules/product.js
import { defineStore } from 'pinia'
import {
  getSkuList,
  getSkuDetail,
  getProductSkus,
  getSkuSelectList,
  getProductList
} from '@/api/product'
import { getStockList } from '@/api/stock'

export const useProductStore = defineStore('product', {
  state: () => ({
    skuList: [],          // 最后一次 SKU 列表
    total: 0,             // 总条数
    currentSku: {},       // 当前查看/编辑的 SKU
    productSkus: [],      // 某商品下的全部 SKU（规格矩阵）
    skuSelectOptions: [],  // 下拉/搜索候选 SKU

    // 新增：商品聚合列表（非SKU）
    productList: [],         // 商品列表数据
    productTotal: 0,         // 商品总条数
    productLoading: false,   // 商品列表加载状态
    productFinished: false,  // 商品列表加载完成状态

    // 新增：商品库存映射（key: productId, value: 总库存）
    productStockMap: new Map(),
  }),

  actions: {
    /**
     * 重置商品列表状态
     */
    resetProductList() {
      this.productList = []
      this.productTotal = 0
      this.productFinished = false
    },

    /**
     * 加载商品库存（聚合SKU级库存）
     * @param {Array} productIds - 商品ID数组
     */
    async loadProductStocks(productIds) {
      if (!productIds.length) return

      try {
        // 调用SKU级库存接口，筛选目标商品的所有SKU库存
        const res = await getStockList({ product_ids: productIds.join(',') })
        const stockList = res.list || []

        // 聚合：按商品ID汇总总库存
        const stockMap = new Map()
        stockList.forEach(stock => {
          const { product_id, quantity } = stock
          if (!stockMap.has(product_id)) {
            stockMap.set(product_id, 0)
          }
          stockMap.set(product_id, stockMap.get(product_id) + Number(quantity || 0))
        })

        // 更新状态中的库存映射
        this.productStockMap = stockMap
      } catch (error) {
        console.error('加载商品库存失败:', error)
      }
    },
    /**
 * 加载商品聚合列表
 * @param {Object} params - 筛选参数（page/limit/keyword等）
 * @param {Boolean} isRefresh - 是否刷新
 */
    async loadProductList(params, isRefresh = false) {
      if (this.productLoading && !isRefresh) return
      this.productLoading = true

      try {
        const res = await getProductList(params)
        const list = res.list || []
        const total = res.total || 0

        // 刷新则替换，否则追加
        this.productList = isRefresh ? list : [...this.productList, ...list]
        this.productTotal = total
        this.productFinished = this.productList.length >= total

        // 同步加载对应商品的库存
        await this.loadProductStocks(this.productList.map(item => item.id))
      } catch (error) {
        console.error('加载商品列表失败:', error)
        showToast('加载商品失败')
      } finally {
        this.productLoading = false
      }
    },
    // 加载 SKU 分页列表（替代原商品列表）
    async loadSkuList(params) {
      const { list, total } = await getSkuList(params)
      this.skuList = list
      this.total = total
    },

    // 加载单个 SKU 详情（替代原商品详情）
    async loadSkuDetail(skuId) {
      this.currentSku = await getSkuDetail(skuId)
    },

    // 加载某商品下的全部 SKU（规格切换用）
    async loadProductSkus(productId) {
      this.productSkus = await getProductSkus(productId)
    },

    // SKU 选择器搜索（远程下拉）
    async searchSkuSelect(keyword, limit = 20) {
      const list = await getSkuSelectList({ keyword, limit })
      this.skuSelectOptions = list
    },

    // 清空当前 SKU
    resetCurrentSku() {
      this.currentSku = {}
    }
  },
  getters: {
    /**
     * 根据商品ID获取总库存
     * @param {Number} productId - 商品ID
     * @returns {Number} 总库存
     */
    getProductStock: (state) => (productId) => {
      return state.productStockMap.get(productId) || 0
    }
  }
})