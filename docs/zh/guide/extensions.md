<script setup>
import SearchTable from "../../.vitepress/components/SearchTable.vue";
</script>

# 扩展列表

> - `yes`: 已支持
> - 空白: 目前还不支持，或正在支持中
> - `no` with issue link: 确定不支持或无法支持
> - `partial` with issue link: 已支持，但是无法完美工作


<search-table />

::: tip
如果缺少您需要的扩展，您可以创建 [功能请求](https://github.com/crazywhalecc/static-php-cli/issues)。

有些扩展或扩展依赖的库会有一些可选的特性，例如 gd 库可选支持 libwebp、freetype 等。
如果你只使用 `bin/spc build gd --build-cli` 是不会包含它们（static-php-cli 默认为最小依赖原则）。

有关编译可选库，请参考 [扩展、库的依赖关系图表](./deps-map)。对于可选的库，你也可以从 [编译命令生成器](./cli-generator) 中选择扩展后展开选择可选库。
:::
