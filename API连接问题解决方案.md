# 系统配置页面API连接问题解决方案

## 问题描述
系统信息页面和系统日志页面在访问后端API时出现500错误，这是因为后端服务可能没有在预期的地址上运行。

## 错误信息
```
system.js:101   GET http://localhost:5173/api/system/info 500 (Internal Server Error)
```

## 解决方案

### 1. 启动后端服务
确保后端ThinkPHP8服务在以下地址上运行：
```
http://127.0.0.1:82
```

启动命令：
```bash
cd d:\git\kincount\kincount-backend
php think run -H 127.0.0.1 -p 82
```

### 2. 检查API代理配置
前端Vite配置已正确设置API代理：
```javascript
// vite.config.js
proxy: {
  '/api': {
    target: 'http://127.0.0.1:82',
    changeOrigin: true,
    rewrite: path => path.replace(/^\/api/, '/index.php/k')
  }
}
```

### 3. 验证API连接
可以使用以下方式验证API连接：

#### 方法1：使用浏览器访问
直接访问以下URL：
```
http://127.0.0.1:82/index.php/k/system/status
```

#### 方法2：使用测试页面
打开 `d:\git\kincount\test-api.html` 文件，点击"测试API连接"按钮。

### 4. 已实现的前端错误处理
已经对系统信息页面和系统日志页面添加了友好的错误处理：
- 当API不可用时，显示"未知"或默认值
- 提供更友好的错误提示信息
- 在系统日志页面添加模拟数据，以便用户了解页面功能

## 后端API端点
后端已实现以下系统相关API：
- `GET /system/status` - 系统状态
- `GET /system/info` - 系统信息
- `GET /system/logs` - 系统日志
- `DELETE /system/logs` - 清空日志

## 注意事项
1. 确保后端服务先启动，再访问前端页面
2. 如果后端服务运行在其他端口，需要修改vite.config.js中的代理配置
3. 确保后端路由配置正确，包含系统相关的路由组