<script setup>
import SearchTable from "../../.vitepress/components/SearchTable.vue";
</script>

# 支持的扩展列表

> - ✅: 已支持
> - 空白: 目前还不支持，或正在支持中

<search-table />

::: tip
如果缺少您需要的扩展，您可以创建 [功能请求](https://github.com/crazywhalecc/static-php-cli/issues)。

某些扩展或其依赖的库会有可选特性（例如 gd 可选支持 libwebp、freetype 等）。
仅使用 `bin/spc build gd --build-cli` 不会包含这些可选依赖——StaticPHP 默认遵循最小依赖原则。
:::
