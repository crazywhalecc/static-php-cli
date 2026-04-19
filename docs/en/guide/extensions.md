<script setup>
import SearchTable from "../../.vitepress/components/SearchTable.vue";
</script>

# Supported Extensions

> - ✅: Supported
> - blank: Not supported or not yet ported

<search-table />

::: tip
If an extension you need is missing, you can file a [feature request](https://github.com/crazywhalecc/static-php-cli/issues).

Some extensions or their library dependencies have optional features (e.g. gd can optionally use libwebp, freetype, etc.).
Running `bin/spc build gd --build-cli` alone will not include them — StaticPHP follows a minimal-dependency principle by default.
:::
