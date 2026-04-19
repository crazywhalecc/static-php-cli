<script setup>
import SearchTable from "../../.vitepress/components/SearchTable.vue";
</script>

# Extensions

> - `yes`: supported
> - _blank_: not supported yet, or WIP
> - `no` with issue link: confirmed to be unavailable due to issue
> - `partial` with issue link: supported but not perfect due to issue

<search-table />

::: tip
If an extension you need is missing, you can create a [Feature Request](https://github.com/crazywhalecc/static-php-cli/issues).

Some extensions or libraries that the extension depends on will have some optional features. 
For example, the gd library optionally supports libwebp, freetype, etc. 
If you only use `bin/spc build gd --build-cli` they will not be included (static-php-cli defaults to the minimum dependency principle).

For more information about optional libraries, see [Extensions, Library Dependency Map](./deps-map). 
For optional libraries, you can also select an extension from the [Command Generator](./cli-generator) and then select optional libraries.
:::
