=== xMojipick ===
Contributors: gentpan
Tags: emoji, sticker, comment, svg, inline, emoji pack
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+

多表情包 SVG 内联表情插件 — 支持评论区、后台、邮件渲染。

== 描述 ==

xMojipick 是一个轻量级 WordPress 表情插件：

* 多表情包支持 — 每个 JSON 文件 = 一个 tab
* 内联 SVG 渲染 — 零图片请求，极速加载
* 评论框内嵌面板 — 表情按钮在评论框左下角
* tabs 切换 — 多套表情自由切换
* 三端渲染 — 前台评论 + 后台管理 + 邮件通知全部正确显示
* SVG 安全过滤 — 自动移除 script 和事件处理器

== 安装 ==

1. 将 `xmojipick` 上传到 `/wp-content/plugins/`
2. 启用插件
3. 在「工具 → xMojipick」中管理表情包

== 添加表情包 ==

在 `assets/packs/` 目录下创建 JSON 文件，一个文件 = 一个 tab：

{
    "name": "表情包名称",
    "sort": 1,
    "icon": "smile",
    "emojis": {
        "smile": {
            "name": "微笑",
            "svg": "<svg viewBox=\"0 0 64 64\">...</svg>"
        }
    }
}

字段说明：
* name — 表情包名称（显示在 tab 上）
* sort — 排序（数字越小越靠前）
* icon — tab 图标使用哪个 emoji 的 slug
* emojis — 表情列表，key 为 slug，value 含 name 和 svg 代码

操作步骤：
1. 用文本编辑器打开你的 .svg 文件
2. 复制全部 <svg>...</svg> 代码
3. 粘贴到 JSON 中 "svg" 字段的引号内
4. 双引号需转义为 \"

== 使用 ==

* 评论框左下角点击笑脸图标 → 弹出表情面板 → 切换 tab → 点击插入
* 手动输入 :slug: 格式代码也可以（如 :smile: :heart:）
* 评论、后台、邮件中都会正确渲染表情
