<template>
  <div class="deps-map">
    <!-- Warning if data missing -->
    <div v-if="missing" class="warning custom-block" style="margin-bottom: 16px">
      <p class="custom-block-title">WARNING</p>
      <p>Dependency data not generated yet. Run <code>bin/spc dev:gen-deps-data</code> to generate it.</p>
    </div>

    <template v-else>
      <!-- Toolbar -->
      <div class="deps-toolbar">
        <input
          class="deps-search"
          v-model="searchText"
          :placeholder="i18n.searchPlaceholder"
          @input="selectedPkg = null"
        />
        <div class="deps-filters">
          <div class="filter-group">
            <button
              v-for="t in typeOptions"
              :key="t.value"
              :class="['filter-btn', { active: selectedType === t.value }]"
              @click="selectedType = t.value; selectedPkg = null"
            >{{ t.label }}</button>
          </div>
          <div class="filter-group">
            <button
              v-for="p in platformOptions"
              :key="p.value"
              :class="['filter-btn', { active: selectedPlatform === p.value }]"
              @click="selectedPlatform = p.value"
            >{{ p.label }}</button>
          </div>
        </div>
      </div>

      <div class="deps-layout">
        <!-- Package list -->
        <div class="deps-list">
          <div v-if="filteredPackages.length === 0" class="no-results">{{ i18n.noResults }}</div>
          <div
            v-for="pkg in filteredPackages"
            :key="pkg.name"
            :class="['pkg-item', { selected: selectedPkg === pkg.name }]"
            @click="selectPkg(pkg.name)"
          >
            <span class="pkg-name">{{ pkg.name }}</span>
            <span :class="['pkg-badge', pkg.type === 'php-extension' ? 'badge-ext' : 'badge-lib']">
              {{ typeLabel(pkg.type) }}
            </span>
          </div>
        </div>

        <!-- Detail panel -->
        <div class="deps-detail" v-if="selectedPkg && selectedPkgData">
          <h3 class="detail-title">{{ selectedPkg }}</h3>
          <span :class="['detail-type-badge', selectedPkgData.type === 'php-extension' ? 'badge-ext' : 'badge-lib']">
            {{ typeLabel(selectedPkgData.type) }}
          </span>

          <!-- OS support for extensions -->
          <div v-if="selectedPkgData.type === 'php-extension' && selectedPkgData.os" class="detail-section">
            <div class="detail-label">{{ i18n.supportedPlatforms }}</div>
            <div class="detail-chips">
              <span v-for="os in selectedPkgData.os" :key="os" class="chip chip-os">{{ os }}</span>
            </div>
          </div>

          <!-- Required deps -->
          <div class="detail-section">
            <div class="detail-label">{{ i18n.requiredDeps }}</div>
            <div class="detail-chips" v-if="currentDepends.length > 0">
              <span
                v-for="dep in currentDepends"
                :key="dep"
                :class="['chip', 'chip-required', { clickable: packages[dep] }]"
                @click="packages[dep] && selectPkg(dep)"
              >{{ dep }}</span>
            </div>
            <div v-else class="no-deps">{{ i18n.none }}</div>
          </div>

          <!-- Suggested deps -->
          <div class="detail-section">
            <div class="detail-label">{{ i18n.suggestedDeps }}</div>
            <div class="detail-chips" v-if="currentSuggests.length > 0">
              <span
                v-for="dep in currentSuggests"
                :key="dep"
                :class="['chip', 'chip-suggested', { clickable: packages[dep] }]"
                @click="packages[dep] && selectPkg(dep)"
              >{{ dep }}</span>
            </div>
            <div v-else class="no-deps">{{ i18n.none }}</div>
          </div>

          <!-- Required by -->
          <div class="detail-section">
            <div class="detail-label">{{ i18n.requiredBy }}</div>
            <div class="detail-chips" v-if="requiredBy.length > 0">
              <span
                v-for="dep in requiredBy"
                :key="dep"
                class="chip chip-rev clickable"
                @click="selectPkg(dep)"
              >{{ dep }}</span>
            </div>
            <div v-else class="no-deps">{{ i18n.none }}</div>
          </div>

          <!-- Suggested by -->
          <div class="detail-section">
            <div class="detail-label">{{ i18n.suggestedBy }}</div>
            <div class="detail-chips" v-if="suggestedBy.length > 0">
              <span
                v-for="dep in suggestedBy"
                :key="dep"
                class="chip chip-rev-sug clickable"
                @click="selectPkg(dep)"
              >{{ dep }}</span>
            </div>
            <div v-else class="no-deps">{{ i18n.none }}</div>
          </div>

          <!-- Mermaid graph -->
          <div class="detail-section" v-if="hasMermaid">
            <div class="detail-label">{{ i18n.depGraph }}</div>
            <div ref="mermaidEl" class="mermaid-wrap"></div>
          </div>
        </div>

        <!-- Empty state -->
        <div class="deps-detail deps-detail-empty" v-else>
          <p>{{ i18n.selectHint }}</p>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useData } from 'vitepress'
import { data as depsData } from '../deps-map.data.js'

const { lang, isDark } = useData()

const missing = depsData.missing
const packages = depsData.packages ?? {}

// --- i18n ---
const I18N = {
  zh: {
    searchPlaceholder: '搜索包名...',
    noResults: '未找到包。',
    selectHint: '← 选择一个包以查看其依赖关系。',
    supportedPlatforms: '支持的平台',
    requiredDeps: '必需依赖',
    suggestedDeps: '可选依赖',
    requiredBy: '被哪些包依赖',
    suggestedBy: '被哪些包建议',
    depGraph: '依赖关系图',
    none: '无',
  },
  en: {
    searchPlaceholder: 'Search package...',
    noResults: 'No packages found.',
    selectHint: '← Select a package to view its dependency details.',
    supportedPlatforms: 'Supported Platforms',
    requiredDeps: 'Required Dependencies',
    suggestedDeps: 'Suggested Dependencies',
    requiredBy: 'Required By',
    suggestedBy: 'Suggested By',
    depGraph: 'Dependency Graph',
    none: 'None',
  },
}
const i18n = computed(() => I18N[lang.value] ?? I18N.en)

// --- State ---
const searchText = ref('')
const selectedType = ref('all')
const selectedPlatform = ref('linux')
const selectedPkg = ref(null)
const mermaidEl = ref(null)

// --- Options ---
const typeOptions = computed(() => [
  { value: 'all', label: lang.value === 'zh' ? '全部' : 'All' },
  { value: 'php-extension', label: lang.value === 'zh' ? '扩展' : 'Extensions' },
  { value: 'library', label: lang.value === 'zh' ? '库' : 'Libraries' },
])
const platformOptions = [
  { value: 'linux', label: 'Linux' },
  { value: 'macos', label: 'macOS' },
  { value: 'windows', label: 'Windows' },
]

function typeLabel(type) {
  if (type === 'php-extension') return 'ext'
  if (type === 'library') return 'lib'
  return type
}

// --- Package list ---
const allPackages = computed(() =>
  Object.entries(packages).map(([name, data]) => ({ name, ...data }))
)

const filteredPackages = computed(() => {
  let list = allPackages.value
  if (selectedType.value !== 'all') {
    list = list.filter(p => p.type === selectedType.value)
  }
  if (searchText.value.trim()) {
    const q = searchText.value.trim().toLowerCase()
    list = list.filter(p => p.name.toLowerCase().includes(q))
  }
  return list
})

// --- Selected package ---
const selectedPkgData = computed(() =>
  selectedPkg.value ? (packages[selectedPkg.value] ?? null) : null
)

const currentDepends = computed(() =>
  selectedPkgData.value?.platforms?.[selectedPlatform.value]?.depends ?? []
)
const currentSuggests = computed(() =>
  selectedPkgData.value?.platforms?.[selectedPlatform.value]?.suggests ?? []
)

const requiredBy = computed(() => {
  if (!selectedPkg.value) return []
  const name = selectedPkg.value
  const plat = selectedPlatform.value
  return Object.entries(packages)
    .filter(([, d]) => (d.platforms?.[plat]?.depends ?? []).includes(name))
    .map(([n]) => n)
})

const suggestedBy = computed(() => {
  if (!selectedPkg.value) return []
  const name = selectedPkg.value
  const plat = selectedPlatform.value
  return Object.entries(packages)
    .filter(([, d]) => (d.platforms?.[plat]?.suggests ?? []).includes(name))
    .map(([n]) => n)
})

// --- Mermaid ---
const hasMermaid = computed(
  () => currentDepends.value.length > 0 || currentSuggests.value.length > 0
)

function buildMermaidCode() {
  const deps = currentDepends.value
  const sugs = currentSuggests.value
  if (deps.length === 0 && sugs.length === 0) return ''

  const safe = s => s.replace(/[^a-zA-Z0-9_]/g, '_')
  const root = safe(selectedPkg.value)
  const lines = ['graph LR', `  ${root}["${selectedPkg.value}"]`]

  const MAX_DEPTH = 6   // max hops from root
  const MAX_CHILDREN = 6 // per-node child limit for levels 2+

  const visitedNodes = new Set([selectedPkg.value])
  const visitedEdges = new Set()
  const queue = []

  // Level 1: direct required deps — solid arrows, no child limit
  for (const dep of deps) {
    const ek = `${selectedPkg.value}\0${dep}`
    if (!visitedEdges.has(ek)) {
      visitedEdges.add(ek)
      lines.push(`  ${root} --> ${safe(dep)}["${dep}"]`)
    }
    if (!visitedNodes.has(dep)) {
      visitedNodes.add(dep)
      queue.push({ name: dep, depth: 1 })
    }
  }

  // BFS: levels 2–MAX_DEPTH — dotted arrows, capped children per node
  while (queue.length > 0) {
    const { name, depth } = queue.shift()
    if (depth >= MAX_DEPTH) continue
    const children = packages[name]?.platforms?.[selectedPlatform.value]?.depends ?? []
    for (const child of children.slice(0, MAX_CHILDREN)) {
      const ek = `${name}\0${child}`
      if (!visitedEdges.has(ek)) {
        visitedEdges.add(ek)
        lines.push(`  ${safe(name)} -.-> ${safe(child)}["${child}"]`)
      }
      if (!visitedNodes.has(child)) {
        visitedNodes.add(child)
        queue.push({ name: child, depth: depth + 1 })
      }
    }
  }

  // Suggested deps from root (single level, optional dotted)
  for (const sug of sugs) {
    lines.push(`  ${root} -. optional .-> ${safe(sug)}["${sug}"]`)
  }

  return lines.join('\n')
}

let mermaidLib = null

async function renderMermaid() {
  if (!mermaidEl.value || !hasMermaid.value) return
  const code = buildMermaidCode()
  if (!code) return

  try {
    if (!mermaidLib) {
      const m = await import('mermaid')
      mermaidLib = m.default
    }
    mermaidLib.initialize({
      startOnLoad: false,
      theme: isDark.value ? 'dark' : 'default',
      securityLevel: 'loose',
    })
    const id = 'deps-graph-' + Date.now()
    const { svg } = await mermaidLib.render(id, code)
    if (mermaidEl.value) {
      mermaidEl.value.innerHTML = svg
    }
  } catch {
    if (mermaidEl.value) {
      mermaidEl.value.innerHTML = `<pre class="mermaid-fallback">${code}</pre>`
    }
  }
}

function selectPkg(name) {
  selectedPkg.value = name
}

watch([selectedPkg, selectedPlatform, isDark], async () => {
  await nextTick()
  await renderMermaid()
})

onMounted(async () => {
  await nextTick()
  await renderMermaid()
})
</script>

<style scoped>
.deps-map {
  font-size: 14px;
}

/* Toolbar */
.deps-toolbar {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.deps-search {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--vp-c-divider);
  border-radius: 6px;
  font-size: 14px;
  background: var(--vp-c-bg);
  color: var(--vp-c-text-1);
  outline: none;
  box-sizing: border-box;
}
.deps-search:focus {
  border-color: var(--vp-c-brand);
}

.deps-filters {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  gap: 4px;
}

.filter-btn {
  padding: 4px 12px;
  border: 1px solid var(--vp-c-divider);
  border-radius: 20px;
  background: var(--vp-c-bg);
  color: var(--vp-c-text-2);
  font-size: 13px;
  cursor: pointer;
  transition: all 0.15s;
}
.filter-btn:hover {
  border-color: var(--vp-c-brand);
  color: var(--vp-c-brand);
}
.filter-btn.active {
  background: var(--vp-c-brand);
  border-color: var(--vp-c-brand);
  color: #fff;
}

/* Layout */
.deps-layout {
  display: flex;
  gap: 16px;
  min-height: 400px;
}

/* Package list */
.deps-list {
  width: 260px;
  flex-shrink: 0;
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  overflow-y: auto;
  max-height: 600px;
}

.no-results {
  padding: 16px;
  color: var(--vp-c-text-3);
  text-align: center;
}

.pkg-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  cursor: pointer;
  border-bottom: 1px solid var(--vp-c-divider);
  transition: background 0.1s;
}
.pkg-item:last-child {
  border-bottom: none;
}
.pkg-item:hover {
  background: var(--vp-c-bg-soft);
}
.pkg-item.selected {
  background: var(--vp-c-brand-soft);
}

.pkg-name {
  font-family: var(--vp-font-family-mono);
  font-size: 13px;
  word-break: break-all;
}

.pkg-badge {
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 10px;
  flex-shrink: 0;
  margin-left: 6px;
}

/* Detail panel */
.deps-detail {
  flex: 1;
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  padding: 20px;
  overflow-y: auto;
  max-height: 600px;
}

.deps-detail-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--vp-c-text-3);
}

.detail-title {
  margin: 0 0 8px 0;
  font-size: 16px;
  font-family: var(--vp-font-family-mono);
}

.detail-type-badge {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 10px;
  display: inline-block;
  margin-bottom: 16px;
}

.detail-section {
  margin-bottom: 16px;
}

.detail-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--vp-c-text-2);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 6px;
}

.detail-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.no-deps {
  color: var(--vp-c-text-3);
  font-size: 13px;
}

/* Chips */
.chip {
  font-family: var(--vp-font-family-mono);
  font-size: 12px;
  padding: 3px 10px;
  border-radius: 12px;
  border: 1px solid transparent;
  display: inline-block;
}
.chip.clickable {
  cursor: pointer;
  transition: opacity 0.15s;
}
.chip.clickable:hover {
  opacity: 0.75;
}

.chip-required {
  background: var(--vp-c-danger-soft);
  border-color: var(--vp-c-danger-1);
  color: var(--vp-c-danger-1);
}
.chip-suggested {
  background: var(--vp-c-warning-soft);
  border-color: var(--vp-c-warning-1);
  color: var(--vp-c-warning-1);
}
.chip-rev {
  background: var(--vp-c-brand-soft);
  border-color: var(--vp-c-brand-1);
  color: var(--vp-c-brand-1);
}
.chip-rev-sug {
  background: var(--vp-c-bg-soft);
  border-color: var(--vp-c-divider);
  color: var(--vp-c-text-2);
}
.chip-os {
  background: var(--vp-c-bg-soft);
  border-color: var(--vp-c-divider);
  color: var(--vp-c-text-1);
}

/* Badges */
.badge-ext {
  background: var(--vp-c-brand-soft);
  color: var(--vp-c-brand-1);
}
.badge-lib {
  background: var(--vp-c-tip-soft);
  color: var(--vp-c-tip-1);
}

/* Mermaid */
.mermaid-wrap {
  overflow-x: auto;
  padding: 8px 0;
}
.mermaid-fallback {
  font-size: 12px;
  white-space: pre-wrap;
}
</style>
