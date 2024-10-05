<template>
  <div>
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
        <th>FreeBSD</th>
        <th>Windows</th>
      </tr>
      </thead>
      <tbody>
      <tr v-for="item in filterData">
        <td v-if="!item.notes">{{ item.name }}</td>
        <td v-else>
          <a :href="'./extension-notes.html#' + item.name">{{ item.name }}</a>
        </td>
        <td>{{ item.linux }}</td>
        <td>{{ item.macos }}</td>
        <td>{{ item.freebsd }}</td>
        <td>{{ item.windows }}</td>
      </tr>
      </tbody>
    </table>
    <div v-if="filterData.length === 0" style="margin: 0 4px 20px 4px; color: var(--vp-c-text-2); font-size: 14px">
      No result, please try another keyword.
    </div>
  </div>
</template>

<script>
export default {
  name: "SearchTable"
}
</script>

<script setup>
import {ref} from "vue";
import ext from '../../../config/ext.json';

// 将 ext 转换为列表，方便后续操作
const data = ref([]);
for (const [name, item] of Object.entries(ext)) {
  data.value.push({
    name,
    linux: item.support?.Linux === undefined ? 'yes' : (item.support?.Linux === 'wip' ? '' : item.support?.Linux),
    macos: item.support?.Darwin === undefined ? 'yes' : (item.support?.Darwin === 'wip' ? '' : item.support?.Darwin),
    freebsd: item.support?.BSD === undefined ? 'yes' : (item.support?.BSD === 'wip' ? '' : item.support?.BSD),
    windows: item.support?.Windows === undefined ? 'yes' : (item.support?.Windows === 'wip' ? '' : item.support?.Windows),
    notes: item.notes === true,
  });
}


const filterData = ref(data.value);
const filterText = ref('');

const doFilter = () => {
  if (filterText.value === '') {
    filterData.value = data.value;
    return;
  }
  filterData.value = data.value.filter(item => {
    return item.name.toLowerCase().includes(filterText.value.toLowerCase());
  });
}
</script>

<style>
.searchinput {
  border: 1px solid var(--vp-c-divider);
}
</style>