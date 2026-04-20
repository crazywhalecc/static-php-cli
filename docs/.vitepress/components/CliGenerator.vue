<template>
  <div>
    <div v-if="missing" class="warning custom-block" style="margin-bottom: 16px">
      <p class="custom-block-title">WARNING</p>
      <p>Extension list is not generated yet. Run <code>bin/spc dev:gen-ext-docs</code> to generate it.</p>
    </div>

    <h2>{{ I18N[lang].selectedSystem }}</h2>
    <div class="option-line">
      <span v-for="(item, index) in osList" :key="index" style="margin-right: 8px">
        <input type="radio" :id="'os-' + item.os" :value="item.os" v-model="selectedSystem" />
        <label :for="'os-' + item.os">{{ item.label }}</label>
      </span>
    </div>
    <div class="option-line">
      <select v-model="selectedArch">
        <option value="x86_64">x86_64</option>
        <option value="aarch64" :disabled="selectedSystem === 'windows'">aarch64</option>
      </select>
    </div>

    <h2>{{ I18N[lang].selectExt }}{{ checkedExts.length > 0 ? (' (' + checkedExts.length + ')') : '' }}</h2>
    <div class="box">
      <input class="input" v-model="filterText" :placeholder="I18N[lang].searchPlaceholder" />
      <br>
      <div v-for="item in extByOS" :key="item" class="ext-item">
        <span>
          <input type="checkbox" :id="item" :value="item" v-model="checkedExts" />
          <label :for="item">
            <span>{{ highlightItem(item, 0) }}</span>
            <span style="color: orangered; font-weight: bolder">{{ highlightItem(item, 1) }}</span>
            <span>{{ highlightItem(item, 2) }}</span>
          </label>
        </span>
      </div>
    </div>
    <div class="my-btn" v-if="selectedSystem !== 'windows'" @click="selectCommon">{{ I18N[lang].selectCommon }}</div>
    <div class="my-btn" v-if="selectedSystem !== 'windows'" @click="selectAll">{{ I18N[lang].selectAll }}</div>
    <div class="my-btn" @click="checkedExts = []">{{ I18N[lang].selectNone }}</div>

    <h2>{{ I18N[lang].buildTarget }}</h2>
    <div class="box">
      <div v-for="item in TARGET" :key="item" class="ext-item">
        <input type="checkbox" :id="'build_' + item" :value="item" v-model="checkedTargets" />
        <label :for="'build_' + item">{{ item }}</label>
      </div>
    </div>
    <div v-if="selectedSystem === 'windows' && (checkedTargets.includes('fpm') || checkedTargets.includes('embed') || checkedTargets.includes('frankenphp'))" class="warning custom-block">
      <p class="custom-block-title">WARNING</p>
      <p>{{ I18N[lang].windowsSAPIUnavailable }}</p>
    </div>

    <h2>{{ I18N[lang].buildOptions }}</h2>
    <table>
      <!-- Build Environment -->
      <tr>
        <td>{{ I18N[lang].buildEnvironment }}</td>
        <td>
          <select v-model="selectedEnv">
            <option value="spc">{{ I18N[lang].buildEnvSpc }}</option>
            <option value="native">{{ I18N[lang].buildEnvNative }}</option>
          </select>
        </td>
      </tr>
      <!-- PHP Version -->
      <tr>
        <td>{{ I18N[lang].downloadPhpVersion }}</td>
        <td>
          <select v-model="selectedPhpVersion">
            <option v-for="item in availablePhpVersions" :key="item" :value="item">{{ item }}</option>
          </select>
        </td>
      </tr>
      <!-- Verbose log -->
      <tr>
        <td>{{ I18N[lang].useVerbose }}</td>
        <td>
          <select v-model="verbosity">
            <option value="">{{ I18N[lang].verboseNone }}</option>
            <option value="-v">-v</option>
            <option value="-vv">-vv</option>
            <option value="-vvv">-vvv</option>
          </select>
        </td>
      </tr>
      <!-- Enable ZTS -->
      <tr>
        <td>{{ I18N[lang].useZTS }}</td>
        <td>
          <input type="radio" id="zts-yes" :value="true" v-model="zts" />
          <label for="zts-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="zts-no" :value="false" v-model="zts" />
          <label for="zts-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
      <!-- Parallel downloads -->
      <tr>
        <td>{{ I18N[lang].dlParallel }}</td>
        <td>
          <input class="number-input" type="number" v-model.number="dlParallel" min="1" max="50" />
        </td>
      </tr>
      <!-- Retry count -->
      <tr>
        <td>{{ I18N[lang].dlRetry }}</td>
        <td>
          <input class="number-input" type="number" v-model.number="dlRetry" min="0" max="100" />
        </td>
      </tr>
      <!-- Prefer binary (pre-built) -->
      <tr>
        <td>{{ I18N[lang].usePreBuilt }}</td>
        <td>
          <input type="radio" id="pre-built-yes" :value="true" v-model="preBuilt" />
          <label for="pre-built-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="pre-built-no" :value="false" v-model="preBuilt" />
          <label for="pre-built-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
      <!-- Enable UPX (linux/windows only) -->
      <tr v-if="selectedSystem !== 'macos'">
        <td>{{ I18N[lang].useUPX }}</td>
        <td>
          <input type="radio" id="upx-yes" :value="true" v-model="enableUPX" />
          <label for="upx-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="upx-no" :value="false" v-model="enableUPX" />
          <label for="upx-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
      <!-- Keep debug symbols (--no-strip) -->
      <tr>
        <td>{{ I18N[lang].noStrip }}</td>
        <td>
          <input type="radio" id="nostrip-yes" :value="true" v-model="noStrip" />
          <label for="nostrip-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="nostrip-no" :value="false" v-model="noStrip" />
          <label for="nostrip-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
    </table>

    <h2>{{ I18N[lang].hardcodedINI }}</h2>
    <textarea class="textarea" :placeholder="I18N[lang].hardcodedINIPlaceholder" v-model="hardcodedINIData" rows="5" />

    <h2>{{ I18N[lang].resultShow }}</h2>

    <!-- SPC Binary Download Command (spc env only) -->
    <div v-if="selectedEnv === 'spc'" class="command-container">
      <b>{{ I18N[lang].downloadSPCBinaryCommand }}</b>
      <div v-if="selectedSystem !== 'windows'" class="command-preview">
        <div class="command-content">{{ spcDownloadCommand }}</div>
        <button class="copy-btn" @click="copyToClipboard(spcDownloadCommand, 'spcDownload')" :class="{ 'copied': copiedStates.spcDownload }">
          {{ copiedStates.spcDownload ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
      <div v-else>
        <div class="warning custom-block">
          <p class="custom-block-title">WARNING</p>
          <p>{{ I18N[lang].windowsDownSPCWarning }}</p>
          <a href="https://dl.static-php.dev/v3/spc-bin/latest/spc-windows-x86_64.exe" target="_blank">https://dl.static-php.dev/v3/spc-bin/latest/spc-windows-x86_64.exe</a>
        </div>
      </div>
    </div>

    <!-- Doctor Command -->
    <div class="command-container">
      <b>{{ I18N[lang].doctorCommand }}</b>
      <div class="command-preview">
        <div class="command-content">{{ doctorCommandString }}</div>
        <button class="copy-btn" @click="copyToClipboard(doctorCommandString, 'doctor')" :class="{ 'copied': copiedStates.doctor }">
          {{ copiedStates.doctor ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
    </div>

    <!-- Build Command -->
    <div class="command-container">
      <b>{{ I18N[lang].compileCommand }}</b>
      <div class="command-preview">
        <div class="command-content">{{ buildCommandString }}</div>
        <button class="copy-btn" @click="copyToClipboard(buildCommandString, 'build')" :class="{ 'copied': copiedStates.build }">
          {{ copiedStates.build ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
    </div>

    <!-- craft.yml -->
    <div class="command-container">
      <b>craft.yml</b>
      <div class="command-preview pre">
        <div class="command-content">{{ craftCommandString }}</div>
        <button class="copy-btn" @click="copyToClipboard(craftCommandString, 'craft')" :class="{ 'copied': copiedStates.craft }">
          {{ copiedStates.craft ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
export default {
  name: "CliGenerator"
}
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
// @ts-ignore VitePress data loader — transformed at build time
import { data as extDataRaw } from '../extensions.data.js';

// Constants
const TARGET = ['cli', 'fpm', 'micro', 'embed', 'frankenphp', 'cgi'];
const availablePhpVersions = ['8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];

// Props
const props = defineProps({
  lang: {
    type: String,
    default: 'zh',
  }
});

// Extension data
const missing = extDataRaw.missing ?? false;
const allExtensions: Array<{ name: string; linux: boolean; macos: boolean; windows: boolean }> = extDataRaw.extensions ?? [];

// Reactive state
const filterText = ref('');
const checkedExts = ref<string[]>([]);
const checkedTargets = ref<string[]>(['cli']);
const selectedEnv = ref<'spc' | 'native'>('spc');
const selectedPhpVersion = ref('8.4');
const selectedSystem = ref<'linux' | 'macos' | 'windows'>('linux');
const selectedArch = ref<'x86_64' | 'aarch64'>('x86_64');
const verbosity = ref('');
const zts = ref(false);
const preBuilt = ref(true);
const enableUPX = ref(false);
const noStrip = ref(false);
const dlParallel = ref(10);
const dlRetry = ref(5);
const hardcodedINIData = ref('');

// Copy states
const copiedStates = ref<Record<string, boolean>>({
  spcDownload: false,
  doctor: false,
  build: false,
  craft: false,
});

// OS list
const osList = [
  { os: 'linux', label: 'Linux' },
  { os: 'macos', label: 'macOS' },
  { os: 'windows', label: 'Windows' },
];

// Computed: extensions filtered by selected OS
const extByOS = computed(() => {
  return allExtensions
    .filter(item => {
      if (selectedSystem.value === 'linux') return item.linux;
      if (selectedSystem.value === 'macos') return item.macos;
      if (selectedSystem.value === 'windows') return item.windows;
      return true;
    })
    .map(item => item.name);
});

const extList = computed(() => [...checkedExts.value].sort().join(','));

const spcCommand = computed(() => {
  if (selectedEnv.value === 'native') return 'bin/spc';
  return selectedSystem.value === 'windows' ? '.\\spc.exe' : './spc';
});

const spcDownloadCommand = computed(() => {
  const os = selectedSystem.value === 'macos' ? 'macos' : 'linux';
  const arch = selectedArch.value;
  return `curl -#fSL https://dl.static-php.dev/v3/spc-bin/latest/spc-${os}-${arch} -o spc && chmod +x spc`;
});

const doctorCommandString = computed(() => `${spcCommand.value} doctor --auto-fix`);

const displayINI = computed(() => {
  const lines = hardcodedINIData.value.split('\n').filter(x => x.indexOf('=') >= 1);
  return lines.length > 0 ? ' ' + lines.map(x => `-I "${x}"`).join(' ') : '';
});

const buildCommandString = computed(() => {
  const sapi = checkedTargets.value.map(x => `--build-${x}`).join(' ');
  const php = ` --dl-with-php=${selectedPhpVersion.value}`;
  const parallel = ` --dl-parallel=${dlParallel.value}`;
  const retry = ` --dl-retry=${dlRetry.value}`;
  const ignoreCache = ' --dl-ignore-cache=php-src';
  const binary = preBuilt.value ? ' --dl-prefer-binary' : '';
  const strip = noStrip.value ? ' --no-strip' : '';
  const upx = enableUPX.value ? ' --with-upx-pack' : '';
  const ztsFlag = zts.value ? ' --enable-zts' : '';
  const verbose = verbosity.value ? ` ${verbosity.value}` : '';
  return `${spcCommand.value} build:php "${extList.value}" ${sapi}${php}${parallel}${retry}${ignoreCache}${binary}${strip}${upx}${ztsFlag}${displayINI.value}${verbose}`;
});

const craftCommandString = computed(() => {
  let str = `php-version: ${selectedPhpVersion.value}\n`;
  str += `extensions: "${extList.value}"\n`;

  // sapi
  if (checkedTargets.value.length === 1) {
    str += `sapi:\n  - ${checkedTargets.value[0]}\n`;
  } else {
    str += `sapi:\n`;
    checkedTargets.value.forEach(s => { str += `  - ${s}\n`; });
  }

  // verbosity (Symfony OutputInterface constants: 64=-v, 128=-vv, 256=-vvv)
  const verbosityMap: Record<string, number> = { '-v': 64, '-vv': 128, '-vvv': 256 };
  if (verbosity.value && verbosityMap[verbosity.value]) {
    str += `verbosity: ${verbosityMap[verbosity.value]}\n`;
  }

  // download-options
  str += `download-options:\n`;
  str += `  parallel: ${dlParallel.value}\n`;
  str += `  retry: ${dlRetry.value}\n`;
  str += `  ignore-cache: php-src\n`;
  if (preBuilt.value) str += `  prefer-binary: true\n`;

  // build-options (only when needed)
  const buildOpts: string[] = [];
  if (noStrip.value) buildOpts.push(`  no-strip: true`);
  if (enableUPX.value) buildOpts.push(`  with-upx-pack: true`);
  if (zts.value) buildOpts.push(`  enable-zts: true`);

  const iniLines = hardcodedINIData.value.split('\n').filter(x => x.indexOf('=') >= 1);
  if (iniLines.length > 0) {
    buildOpts.push(`  with-hardcoded-ini:`);
    iniLines.forEach(line => buildOpts.push(`    - "${line}"`));
  }

  if (buildOpts.length > 0) {
    str += `build-options:\n${buildOpts.join('\n')}\n`;
  }

  return str;
});

// Methods
const selectCommon = () => {
  const common = [
    'apcu', 'bcmath', 'calendar', 'ctype', 'curl', 'dba', 'dom', 'exif',
    'filter', 'fileinfo', 'gd', 'iconv', 'intl', 'mbstring', 'mbregex',
    'mysqli', 'mysqlnd', 'openssl', 'opcache', 'pcntl', 'pdo', 'pdo_mysql',
    'pdo_sqlite', 'pdo_pgsql', 'pgsql', 'phar', 'posix', 'readline', 'redis',
    'session', 'simplexml', 'sockets', 'sodium', 'sqlite3', 'tokenizer',
    'xml', 'xmlreader', 'xmlwriter', 'xsl', 'zip', 'zlib',
  ];
  const supported = new Set(extByOS.value);
  checkedExts.value = common.filter(e => supported.has(e));
};

const selectAll = () => {
  checkedExts.value = [...extByOS.value];
};

const highlightItem = (item: string, step: number) => {
  if (!filterText.value || !item.includes(filterText.value)) {
    return step === 0 ? item : '';
  }
  const index = item.indexOf(filterText.value);
  switch (step) {
    case 0: return item.substring(0, index);
    case 1: return filterText.value;
    case 2: return item.substring(index + filterText.value.length);
    default: return '';
  }
};

const copyToClipboard = async (text: string, key: string) => {
  try {
    await navigator.clipboard.writeText(text);
    copiedStates.value[key] = true;
    setTimeout(() => { copiedStates.value[key] = false; }, 2000);
  } catch {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
  }
};

// Watchers
watch(selectedSystem, () => {
  if (selectedSystem.value === 'windows') {
    selectedArch.value = 'x86_64';
    enableUPX.value = false;
  }
  checkedExts.value = [];
});

// I18N
const I18N: Record<string, Record<string, string>> = {
  zh: {
    selectExt: '选择扩展',
    buildTarget: '选择编译目标',
    buildOptions: '编译选项',
    buildEnvironment: '编译环境',
    buildEnvNative: '本地构建（Git 源码）',
    buildEnvSpc: '本地构建（独立 spc 二进制）',
    useVerbose: '是否输出详细日志',
    verboseNone: '不输出（默认）',
    yes: '是',
    no: '否',
    resultShow: '结果展示',
    selectCommon: '选择常用扩展',
    selectAll: '选择全部',
    selectNone: '全部取消选择',
    useZTS: '是否编译线程安全版',
    hardcodedINI: '硬编码 INI 选项',
    hardcodedINIPlaceholder: '如需要硬编码 ini，每行写一个，例如：memory_limit=2G',
    compileCommand: '编译命令',
    downloadPhpVersion: '下载 PHP 版本',
    downloadSPCBinaryCommand: '下载 spc 二进制命令',
    selectedSystem: '选择操作系统',
    windowsSAPIUnavailable: 'Windows 目前不支持 fpm、embed、frankenphp 构建！',
    useUPX: '是否开启 UPX 压缩（减小二进制体积）',
    windowsDownSPCWarning: 'Windows 下请手动下载 spc.exe 二进制文件！',
    usePreBuilt: '如果可能，使用预编译的依赖库（减少编译时间）',
    searchPlaceholder: '搜索扩展...',
    copy: '复制',
    copied: '已复制',
    doctorCommand: '自动检查和准备构建环境命令',
    dlParallel: '并行下载数（1-50）',
    dlRetry: '失败重试次数',
    noStrip: '保留调试符号（--no-strip）',
  },
  en: {
    selectExt: 'Select Extensions',
    buildTarget: 'Build Target',
    buildOptions: 'Build Options',
    buildEnvironment: 'Build Environment',
    buildEnvNative: 'Native build (Git source code)',
    buildEnvSpc: 'Native build (standalone spc binary)',
    useVerbose: 'Verbose log output',
    verboseNone: 'None (default)',
    yes: 'Yes',
    no: 'No',
    resultShow: 'Result',
    selectCommon: 'Select common extensions',
    selectAll: 'Select all',
    selectNone: 'Unselect all',
    useZTS: 'Enable ZTS (thread-safe)',
    hardcodedINI: 'Hardcoded INI options',
    hardcodedINIPlaceholder: 'If you need to hardcode ini, write one per line, for example: memory_limit=2G',
    compileCommand: 'Build command',
    downloadPhpVersion: 'PHP version',
    downloadSPCBinaryCommand: 'Download spc binary',
    selectedSystem: 'Select OS',
    windowsSAPIUnavailable: 'Windows does not support fpm, embed and frankenphp build!',
    useUPX: 'Enable UPX compression (reduce binary size)',
    windowsDownSPCWarning: 'Please download the spc.exe binary manually on Windows!',
    usePreBuilt: 'Use pre-built dependencies where available (reduce compile time)',
    searchPlaceholder: 'Search extensions...',
    copy: 'Copy',
    copied: 'Copied',
    doctorCommand: 'Auto-check and prepare build environment',
    dlParallel: 'Parallel downloads (1-50)',
    dlRetry: 'Retry count on failure',
    noStrip: 'Keep debug symbols (--no-strip)',
  }
};
</script>

<style scoped>
.number-input {
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  width: 80px;
  padding: 6px 10px;
  background-color: var(--vp-c-bg-soft);
  color: var(--vp-c-text-1);
  font-size: 14px;
  outline: none;
  transition: all 0.2s ease;
}

.number-input:hover {
  border-color: var(--vp-c-brand-1);
}

.number-input:focus {
  border-color: var(--vp-c-brand-1);
  box-shadow: 0 0 0 3px var(--vp-c-brand-soft);
}

.box {
  display: flex;
  flex-wrap: wrap;
  max-width: 100%;
}

.ext-item {
  margin: 4px 8px;
}

h2 {
  margin-bottom: 8px;
}

.command-preview {
  position: relative;
  padding: 1.2rem;
  background: var(--vp-c-divider);
  border-radius: 8px;
  word-break: break-all;
  font-family: monospace;
  overflow-wrap: break-word;
}

.command-content {
  padding-right: 80px;
}

.copy-btn {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--vp-button-brand-bg);
  color: var(--vp-button-brand-text);
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.2s ease;
}

.copy-btn:hover {
  background: var(--vp-button-brand-hover-bg);
  transform: translateY(-1px);
}

.copy-btn.copied {
  background: var(--vp-c-green-1);
  color: white;
}

.pre {
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: break-word;
}

.option-line {
  padding: 4px 8px;
}

.option-title {
  margin: 4px 8px 4px 4px;
  font-weight: bold;
}

select {
  border-radius: 8px;
  border: 1px solid var(--vp-c-divider);
  padding: 8px 12px;
  width: 300px;
  background-color: var(--vp-c-bg-soft);
  color: var(--vp-c-text-1);
  font-size: 14px;
  transition: all 0.2s ease;
  cursor: pointer;
  outline: none;
}

select:hover {
  border-color: var(--vp-c-brand-1);
  background-color: var(--vp-c-bg);
}

select:focus {
  border-color: var(--vp-c-brand-1);
  box-shadow: 0 0 0 3px var(--vp-c-brand-soft);
}

.my-btn {
  color: var(--vp-button-alt-text);
  background-color: var(--vp-button-alt-bg);
  border-radius: 8px;
  padding: 0 16px;
  line-height: 32px;
  font-size: 14px;
  display: inline-block;
  text-align: center;
  font-weight: 600;
  margin-right: 8px;
  white-space: nowrap;
  transition: color 0.25s, border-color 0.25s, background-color 0.25s;
  cursor: pointer;
  border: 1px solid var(--vp-button-alt-border);
}

.my-btn:hover {
  border-color: var(--vp-button-alt-hover-border);
  color: var(--vp-button-alt-hover-text);
  background-color: var(--vp-button-alt-hover-bg);
}

.my-btn:active {
  border-color: var(--vp-button-alt-active-border);
  color: var(--vp-button-alt-active-text);
  background-color: var(--vp-button-alt-active-bg);
}

.textarea {
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  width: calc(100% - 24px);
  padding: 12px;
  background-color: var(--vp-c-bg-soft);
  color: var(--vp-c-text-1);
  font-size: 14px;
  font-family: var(--vp-font-family-mono);
  line-height: 1.5;
  transition: all 0.2s ease;
  outline: none;
  resize: vertical;
}

.textarea:hover {
  border-color: var(--vp-c-brand-1);
  background-color: var(--vp-c-bg);
}

.textarea:focus {
  border-color: var(--vp-c-brand-1);
  box-shadow: 0 0 0 3px var(--vp-c-brand-soft);
}

.input {
  display: block;
  border: 1px solid var(--vp-c-divider);
  border-radius: 8px;
  width: 100%;
  padding: 10px 12px;
  background-color: var(--vp-c-bg-soft);
  color: var(--vp-c-text-1);
  font-size: 14px;
  transition: all 0.2s ease;
  outline: none;
  box-sizing: border-box;
}

.input:hover {
  border-color: var(--vp-c-brand-1);
  background-color: var(--vp-c-bg);
}

.input:focus {
  border-color: var(--vp-c-brand-1);
  box-shadow: 0 0 0 3px var(--vp-c-brand-soft);
}

/* Radio button styles */
input[type="radio"] {
  appearance: none;
  width: 18px;
  height: 18px;
  border: 2px solid var(--vp-c-border);
  border-radius: 50%;
  background-color: var(--vp-c-bg);
  cursor: pointer;
  position: relative;
  vertical-align: middle;
  margin-right: 6px;
  transition: all 0.2s ease;
}

input[type="radio"]:hover {
  border-color: var(--vp-c-brand-1);
  background-color: var(--vp-c-bg-soft);
}

input[type="radio"]:checked {
  border-color: var(--vp-c-brand-1);
  background-color: var(--vp-c-brand-1);
}

input[type="radio"]:checked::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--vp-c-bg);
}

input[type="radio"]:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Checkbox styles */
input[type="checkbox"] {
  appearance: none;
  width: 18px;
  height: 18px;
  border: 2px solid var(--vp-c-border);
  border-radius: 4px;
  background-color: var(--vp-c-bg);
  cursor: pointer;
  position: relative;
  vertical-align: middle;
  margin-right: 6px;
  transition: all 0.2s ease;
}

input[type="checkbox"]:hover {
  border-color: var(--vp-c-brand-1);
  background-color: var(--vp-c-bg-soft);
}

input[type="checkbox"]:checked {
  border-color: var(--vp-c-brand-1);
  background-color: var(--vp-c-brand-1);
}

input[type="checkbox"]:checked::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 5px;
  width: 4px;
  height: 8px;
  border: solid var(--vp-c-bg);
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

input[type="checkbox"]:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Label styles */
label {
  cursor: pointer;
  user-select: none;
  color: var(--vp-c-text-1);
  font-size: 14px;
  line-height: 1.5;
  transition: color 0.2s ease;
}

label:hover {
  color: var(--vp-c-brand-1);
}

input[type="radio"]:disabled + label,
input[type="checkbox"]:disabled + label {
  opacity: 0.5;
  cursor: not-allowed;
}

input[type="radio"]:disabled + label:hover,
input[type="checkbox"]:disabled + label:hover {
  color: var(--vp-c-text-1);
}

.command-container {
  margin-bottom: 24px;
}

.modal-button {
  padding: 4px 8px;
  border-radius: 4px;
  border-color: var(--vp-button-alt-border);
  color: var(--vp-button-alt-text);
  background-color: var(--vp-button-alt-bg);
}

.modal-button:hover {
  border-color: var(--vp-button-alt-hover-border);
  color: var(--vp-button-alt-hover-text);
  background-color: var(--vp-button-alt-hover-bg)
}

.modal-button:active {
  border-color: var(--vp-button-alt-active-border);
  color: var(--vp-button-alt-active-text);
  background-color: var(--vp-button-alt-active-bg)
}

@media (max-width: 768px) {
  .command-preview {
    padding: 1rem;
  }

  .copy-btn {
    position: static;
    margin-top: 0.5rem;
    width: 100%;
  }

  .command-content {
    padding-right: 0;
  }

  .box {
    flex-direction: column;
  }

  .ext-item {
    margin: 2px 4px;
  }
}
</style>
