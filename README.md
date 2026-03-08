<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.0%2B-21759b?style=flat-square&logo=wordpress&logoColor=white" alt="WordPress 5.0+" />
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white" alt="PHP 7.4+" />
  <img src="https://img.shields.io/badge/License-GPL--2.0-blue?style=flat-square" alt="GPL-2.0" />
  <img src="https://img.shields.io/badge/Version-1.0.1-green?style=flat-square" alt="v1.0.1" />
</p>

<h1 align="center">😊 xMojipick</h1>

<p align="center">
  <strong>WordPress 多表情包表情插件</strong><br>
  支持 SVG / PNG / GIF / AVIF / WebP，评论区 · 后台 · 邮件三端渲染
</p>

---

## 特性

- **多表情包** — 每个 JSON 配置文件 = 一个 Tab，自由扩展
- **多格式支持** — SVG 内联渲染零请求 + PNG/GIF/AVIF/WebP 图片表情
- **三端渲染** — 前台评论、后台管理、邮件通知全部正确显示
- **PJAX / SPA 兼容** — 支持 Lared、Westlife 等 PJAX 主题无刷新导航
- **暗色模式** — 自动检测页面背景色，自动切换明暗主题
- **懒加载** — 非活动 Tab 图片按需加载，节省带宽
- **SVG 安全过滤** — 自动移除 `<script>`、事件处理器等危险代码
- **键盘导航** — 方向键选择表情，Enter 插入，Esc 关闭
- **轻量高效** — CSS 10KB + JS 14KB（minified），无第三方依赖

## 内置表情包

| 表情包 | 数量 | 格式 | 说明 |
|--------|------|------|------|
| **Redmoji** | 24 | SVG | 红色系可爱表情 |
| **bilibili** | 40 | PNG | B站经典小电视表情 |
| **旺财** | 62 | SVG | 旺财狗狗表情 |
| **线条** | 20 | SVG | 简笔线条表情 |

## 安装

1. 下载插件，上传 `xmojipick` 文件夹到 `/wp-content/plugins/`
2. 在 WordPress 后台启用插件
3. 前往 **工具 → xMojipick** 管理表情包

## 使用方法

### 评论区

评论框左下角点击 😊 图标 → 弹出表情面板 → 切换 Tab → 点击表情插入

手动输入 `:slug:` 格式也可以，例如 `:微笑:` `:开心:`

### 后台管理

**工具 → xMojipick** 提供两个功能：

- **表情包管理** — 启用/禁用表情包
- **文件扫描器** — 扫描 `assets/packs/` 目录，一键生成 JSON 配置

## 添加自定义表情包

### 方式一：文件扫描器（推荐）

1. 在 `assets/packs/` 下创建文件夹，如 `my-emoji/`
2. 将表情图片放入文件夹
3. 后台 **文件扫描器** → 扫描 → 生成 JSON

### 方式二：手动创建 JSON

在 `assets/packs/` 下创建 `pack-名称.json`：

```json
{
  "name": "表情包名称",
  "sort": 1,
  "emojis": [
    {
      "slug": "smile",
      "name": "微笑",
      "svg": "<svg viewBox=\"0 0 64 64\">...</svg>"
    }
  ]
}
```

**图片格式表情包：**

```json
{
  "name": "表情包名称",
  "sort": 2,
  "emojis": [
    {
      "slug": "hello",
      "name": "你好",
      "file": "hello.png"
    }
  ]
}
```

### 字段说明

| 字段 | 说明 |
|------|------|
| `name` | 表情包名称，显示在 Tab 上 |
| `sort` | 排序，数字越小越靠前 |
| `emojis[].slug` | 表情标识符，用于 `:slug:` 短代码 |
| `emojis[].name` | 表情名称，用于 title 提示 |
| `emojis[].svg` | SVG 代码（内联渲染，零请求） |
| `emojis[].file` | 图片文件名（相对于表情包文件夹） |

## 文件结构

```
xmojipick/
├── xmojipick.php            # 插件入口
├── uninstall.php             # 卸载清理
├── readme.txt                # WordPress 插件信息
├── includes/
│   ├── class-loader.php      # 表情包加载 + 渲染
│   ├── class-comment.php     # 前端评论区集成
│   └── class-admin.php       # 后台管理页面
└── assets/
    ├── css/
    │   ├── xmojipick.css         # 前端样式
    │   ├── xmojipick.min.css
    │   ├── xmojipick-admin.css   # 后台样式
    │   └── xmojipick-admin.min.css
    ├── js/
    │   ├── xmojipick.js          # 前端脚本
    │   ├── xmojipick.min.js
    │   ├── xmojipick-admin.js    # 后台脚本
    │   └── xmojipick-admin.min.js
    └── packs/                    # 表情包目录
        ├── pack-Redmoji.json
        ├── pack-bilibili.json
        ├── Redmoji/
        ├── bilibili/
        └── ...
```

## 技术细节

- **渲染方式**：SVG 表情使用 `data:image/svg+xml;base64` 内联到 `background-image`，零额外请求
- **短代码格式**：`:slug:` 存储在评论文本中，渲染时替换为表情元素
- **缓存机制**：表情包数据使用 WordPress Transient API 缓存
- **资源加载**：支持 `SCRIPT_DEBUG` 常量切换开发版/压缩版
- **安全**：SVG 经过 `wp_kses` 白名单过滤 + 自定义 `clean_svg` 清洗

## 兼容性

- WordPress 5.0+
- PHP 7.4+
- 已测试主题：Lared、Westlife 等 PJAX/SPA 主题
- 支持 contentEditable 评论编辑器

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)

---

<p align="center">
  Made by <a href="https://xifeng.net">西风</a>
</p>
