# 更新日志

所有对 xMojipick 的显著更改都将记录在此文件中。

---

## [1.1.0] - 2026-03-10

### 性能优化
- SVG 表情包改为外部文件 URL 加载，不再内联 base64 编码
  - 文章页 HTML 从 ~4.3 MB 降至 ~130 KB（降幅 97%）
  - PJAX 响应从 ~2.1 MB 降至 ~35 KB（降幅 98%）
  - 所有表情包统一使用 `"file"` 字段引用文件名，不再读取 SVG 内容嵌入 JSON
- 非首个 Tab 表情统一启用懒加载（`data-src` + `data-lazy`）
- 移除 `svg_to_data_uri()` 在 picker / 评论 / 邮件中的调用

### 改动文件
- `includes/class-loader.php` — `load_packs_from_disk()` / `generate_pack_json()` / `render_inline()` / `render_email()` 移除 SVG 内联分支
- `includes/class-comment.php` — `build_js_packs()` / `output_picker_html()` / `get_tab_icon()` 移除 SVG base64 分支
- `assets/packs/pack-旺财.json` / `pack-Redmoji.json` / `pack-线条.json` — 重新生成，`"svg"` 字段替换为 `"file"` 引用

---

## [1.0.1] - 2026-03-08

### 改进
- PJAX/SPA 兼容性增强
- 表情包数据改由 `<head>` JSON 元素输出，PJAX head-switch 自动更新

---

## [1.0.0] - 2026-03-07

### 首次发布
- 多表情包表情插件，支持 SVG/PNG/GIF/AVIF/WebP
- 评论区 / 后台 / 邮件三端渲染
- PJAX / SPA 主题兼容
- 暗色模式自动检测
- 懒加载、键盘导航、SVG 安全过滤
