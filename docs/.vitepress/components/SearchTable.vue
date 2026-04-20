<template>
  <div>
    <div v-if="missing" class="warning custom-block" style="margin-bottom: 16px">
      <p class="custom-block-title">WARNING</p>
      <p>Extension list is not generated yet. Run <code>bin/spc dev:gen-ext-docs</code> to generate it.</p>
    </div>
    <template v-else>
      <header class="DocSearch-SearchBar" style="padding: 0">
        <form class="DocSearch-Form searchinput">
          <input class="DocSearch-Input" v-model="filterText" placeholder="Filter name..." @input="doFilter" />
        </form>
      </header>
      <table>
        <thead>
        <tr>
          <th>Extension Name</th>
          <th>Linux</th>
          <th>macOS</th>
          <th>Windows</th>
          <th>Website</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="item in filterData" :key="item.name">
          <td>
            <span v-if="!item.hasNotes">{{ item.name }}</span>
            <a v-else :href="'./extension-notes.html#' + item.name">{{ item.name }}</a>
          </td>
          <td>{{ item.linux ? '✅' : '' }}</td>
          <td>{{ item.macos ? '✅' : '' }}</td>
          <td>{{ item.windows ? '✅' : '' }}</td>
          <td>
            <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="ext-source-link">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M10 6v2H5v11h11v-5h2v6a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1h6zm11-3v8h-2V6.413l-7.793 7.794-1.414-1.414L17.585 5H13V3h8z"/></svg>
            </a>
          </td>
        </tr>
        </tbody>
      </table>
      <div v-if="filterData.length === 0" style="margin: 0 4px 20px 4px; color: var(--vp-c-text-2); font-size: 14px">
        No result, please try another keyword.
      </div>
    </template>
  </div>
</template>

<script>
export default {
  name: "SearchTable"
}
</script>

<script setup>
import { ref } from 'vue'
import { data as extData } from '../extensions.data.js'

const missing = extData.missing
const data = ref(extData.extensions)
const filterData = ref(extData.extensions)
const filterText = ref('')

const doFilter = () => {
  if (filterText.value === '') {
    filterData.value = data.value
    return
  }
  filterData.value = data.value.filter(item =>
    item.name.toLowerCase().includes(filterText.value.toLowerCase())
  )
}
</script>

<style>
.searchinput {
  border: 1px solid var(--vp-c-divider);
}
.ext-source-link {
  color: var(--vp-c-text-3);
  vertical-align: middle;
  opacity: 0.6;
  transition: opacity 0.2s;
}
.ext-source-link:hover {
  opacity: 1;
  color: var(--vp-c-brand-1);
}
</style>
