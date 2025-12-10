// src/store/modules/product.js
import { defineStore } from 'pinia'
import {
  getSkuList,
  getSkuDetail,
  getProductSkus,
  getSkuSelectList,
  getProductList
} from '@/api/product'
// 移除 getStockList 导入，因为不再需要

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

    // 移除：不再需要productStockMap
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
     * 加载商品聚合列表
     * @param {Object} params - 筛选参数（page/limit/keyword等）
     * @param {Boolean} isRefresh - 是否刷新
     */
    async loadProductList(params, isRefresh = false) {
      if (this.productLoading && !isRefresh) return
      this.productLoading = true

      try {
        const res = await getProductList(params)

        // 确保响应成功
        if (res.code !== 200) {
          throw new Error(res.msg || '加载商品列表失败')
        }

        // 从 data 字段中提取数据
        const { list = [], total = 0 } = res.data || {}

        // 刷新则替换，否则追加
        this.productList = isRefresh ? list : [...this.productList, ...list]
        this.productTotal = total
        this.productFinished = this.productList.length >= total

        // 移除：不再需要额外加载库存数据
        // await this.loadProductStocks(this.productList.map(item => item.id))

        return list // 可选：返回列表数据
      } catch (error) {
        console.error('加载商品列表失败:', error)
        // 注意：store 中不能直接使用 showToast，需要在组件中处理
        throw error // 抛出错误，让组件处理
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
  }
  // 移除：不再需要getters中的getProductStock
})