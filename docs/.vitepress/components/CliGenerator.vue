<template>
  <div>
    <h2>{{ I18N[lang].selectedSystem }}</h2>
    <div class="option-line">
      <span v-for="(item, index) in osList" :key="index" style="margin-right: 8px">
        <input type="radio" :id="'os-' + item.os" :value="item.os" :disabled="item.disabled === true" v-model="selectedSystem" />
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
      <div v-for="item in extFilter" :key="item" class="ext-item">
        <span>
          <input type="checkbox" :id="item" :value="item" v-model="checkedExts" :disabled="extDisableList.indexOf(item) !== -1">
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

    <details class="details custom-block" open>
      <summary>{{ I18N[lang].buildLibs }}{{ checkedLibs.length > 0 ? (' (' + checkedLibs.length + ')') : '' }}</summary>
      <div class="box">
        <div v-for="(item, index) in libContain" :key="index" class="ext-item">
          <input type="checkbox" :id="index" :value="item" v-model="checkedLibs" :disabled="libDisableList.indexOf(item) !== -1">
          <label :for="index">{{ item }}</label>
        </div>
      </div>
    </details>
    <div class="tip custom-block">
      <p class="custom-block-title">TIP</p>
      <p>{{ I18N[lang].depTips }}</p>
      <p>{{ I18N[lang].depTips2 }}</p>
    </div>
    <h2>{{ I18N[lang].buildTarget }}</h2>
    <div class="box">
      <div v-for="item in TARGET" :key="item" class="ext-item">
        <input type="checkbox" :id="'build_' + item" :value="item" v-model="checkedTargets" @change="onTargetChange">
        <label :for="'build_' + item">{{ item }}</label>
      </div>
    </div>
    <div v-if="selectedPhpVersion === '7.4' && (checkedTargets.indexOf('micro') !== -1 || checkedTargets.indexOf('all') !== -1)" class="warning custom-block">
      <p class="custom-block-title">WARNING</p>
      <p>{{ I18N[lang].microUnavailable }}</p>
    </div>
    <div v-if="selectedSystem === 'windows' && (checkedTargets.indexOf('fpm') !== -1 || checkedTargets.indexOf('embed') !== -1 || checkedTargets.indexOf('frankenphp') !== -1)" class="warning custom-block">
      <p class="custom-block-title">WARNING</p>
      <p>{{ I18N[lang].windowsSAPIUnavailable }}</p>
    </div>
    <h2>{{ I18N[lang].buildOptions }}</h2>
    <!-- Refactor all build options in table -->
    <table>
      <!-- buildEnvironment -->
      <tr>
        <td>{{ I18N[lang].buildEnvironment }}</td>
        <td>
          <select v-model="selectedEnv">
            <option value="native">{{ I18N[lang].buildEnvNative }}</option>
            <option value="spc">{{ I18N[lang].buildEnvSpc }}</option>
            <option value="docker" v-if="selectedSystem !== 'windows'">{{ I18N[lang].buildEnvDocker }}</option>
          </select>
        </td>
      </tr>
      <!-- Download PHP version -->
      <tr>
        <td>{{ I18N[lang].downloadPhpVersion }}</td>
        <td>
          <select v-model="selectedPhpVersion">
            <option v-for="item in availablePhpVersions" :key="item" :value="item">{{ item }}</option>
          </select>
        </td>
      </tr>
      <!-- Enable debug message -->
      <tr>
        <td>{{ I18N[lang].useDebug }}</td>
        <td>
          <input type="radio" id="debug-yes" :value="1" v-model="debug" />
          <label for="debug-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="debug-no" :value="0" v-model="debug" />
          <label for="debug-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
      <!-- Enable ZTS -->
      <tr>
        <td>{{ I18N[lang].useZTS }}</td>
        <td>
          <input type="radio" id="zts-yes" :value="1" v-model="zts" />
          <label for="zts-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="zts-no" :value="0" v-model="zts" />
          <label for="zts-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
      <!-- download corresponding extensions -->
      <tr>
        <td>{{ I18N[lang].resultShowDownload }}</td>
        <td>
          <input type="radio" id="show-download-yes" :value="1" v-model="downloadByExt" />
          <label for="show-download-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="show-download-no" :value="0" v-model="downloadByExt" />
          <label for="show-download-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
      <!-- Download pre-built -->
      <tr>
        <td>{{ I18N[lang].usePreBuilt }}</td>
        <td>
          <input type="radio" id="pre-built-yes" :value="1" v-model="preBuilt" />
          <label for="pre-built-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="pre-built-no" :value="0" v-model="preBuilt" />
          <label for="pre-built-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
      <!-- Enable UPX -->
      <tr v-if="selectedSystem !== 'macos'">
        <td>{{ I18N[lang].useUPX }}</td>
        <td>
          <input type="radio" id="upx-yes" :value="1" v-model="enableUPX" />
          <label for="upx-yes">{{ I18N[lang].yes }}</label>
          <input type="radio" id="upx-no" :value="0" v-model="enableUPX" />
          <label for="upx-no">{{ I18N[lang].no }}</label>
        </td>
      </tr>
    </table>

    <h2>{{ I18N[lang].hardcodedINI }}</h2>
    <textarea class="textarea" :placeholder="I18N[lang].hardcodedINIPlacehoder" v-model="hardcodedINIData" rows="5" />
    <h2>{{ I18N[lang].resultShow }}</h2>

    <!-- SPC Binary Download Command -->
    <div v-if="selectedEnv === 'spc'" class="command-container">
      <b>{{ I18N[lang].downloadSPCBinaryCommand }}</b>
      <div v-if="selectedSystem !== 'windows'" class="command-preview">
        <div class="command-content">
          {{ spcDownloadCommand }}
        </div>
        <button class="copy-btn" @click="copyToClipboard(spcDownloadCommand)" :class="{ 'copied': copiedStates.spcDownload }">
          {{ copiedStates.spcDownload ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
      <div v-else>
        <div class="warning custom-block">
          <p class="custom-block-title">WARNING</p>
          <p>{{ I18N[lang].windowsDownSPCWarning }}</p>
          <a href="https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe" target="_blank">https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe</a>
        </div>
      </div>
    </div>

    <!-- Download Commands -->
    <div v-if="downloadByExt" class="command-container">
      <b>{{ I18N[lang].downloadExtOnlyCommand }}</b>
      <div class="command-preview">
        <div class="command-content">
          {{ downloadExtCommand }}
        </div>
        <button class="copy-btn" @click="copyToClipboard(downloadExtCommand)" :class="{ 'copied': copiedStates.downloadExt }">
          {{ copiedStates.downloadExt ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
    </div>
    <div v-else class="command-container">
      <b>{{ I18N[lang].downloadAllCommand }}</b>
      <div class="command-preview">
        <div class="command-content">
          {{ downloadAllCommand }}
        </div>
        <button class="copy-btn" @click="copyToClipboard(downloadAllCommand)" :class="{ 'copied': copiedStates.downloadAll }">
          {{ copiedStates.downloadAll ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
    </div>

    <!-- UPX Download Command -->
    <div class="command-container" v-if="enableUPX">
      <b>{{ I18N[lang].downloadUPXCommand }}</b>
      <div class="command-preview">
        <div class="command-content">
          {{ downloadPkgCommand }}
        </div>
        <button class="copy-btn" @click="copyToClipboard(downloadPkgCommand)" :class="{ 'copied': copiedStates.downloadPkg }">
          {{ copiedStates.downloadPkg ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
    </div>

    <!-- Doctor Command -->
    <div class="command-container">
      <b>{{ I18N[lang].doctorCommand }}</b>
      <div class="command-preview">
        <div class="command-content">
          {{ doctorCommandString }}
        </div>
        <button class="copy-btn" @click="copyToClipboard(doctorCommandString)" :class="{ 'copied': copiedStates.doctor }">
          {{ copiedStates.doctor ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
    </div>

    <!-- Build Command -->
    <div class="command-container">
      <b>{{ I18N[lang].compileCommand }}</b>
      <div class="command-preview">
        <div class="command-content">
          {{ buildCommandString }}
        </div>
        <button class="copy-btn" @click="copyToClipboard(buildCommandString)" :class="{ 'copied': copiedStates.build }">
          {{ copiedStates.build ? I18N[lang].copied : I18N[lang].copy }}
        </button>
      </div>
    </div>

    <!-- Craft.yml -->
    <div class="command-container">
      <b>craft.yml</b>
      <div class="command-preview pre">
        <div class="command-content">
          {{ craftCommandString }}
        </div>
        <button class="copy-btn" @click="copyToClipboard(craftCommandString)" :class="{ 'copied': copiedStates.craft }">
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
import {computed, ref, watch} from "vue";
import extData from '../config/ext.json';
import libData from '../config/lib.json';
import { getAllExtLibsByDeps } from './DependencyUtil.js';

// Constants
const OS_MAP = new Map([['linux', 'Linux'], ['macos', 'Darwin'], ['windows', 'Windows']]);
const TARGET = ['cli', 'fpm', 'micro', 'embed', 'frankenphp', 'all'];
const availablePhpVersions = ['8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];

// Props
const props = defineProps({
  lang: {
    type: String,
    default: 'zh',
  }
});

// Reactive data
const ext = ref(extData);
const lib = ref(libData);
const libContain = ref([]);
const filterText = ref('');
const checkedExts = ref([]);
const checkedLibs = ref([]);
const extDisableList = ref([]);
const libDisableList = ref([]);
const checkedTargets = ref(['cli']);
const selectedEnv = ref('spc');
const selectedPhpVersion = ref('8.4');
const selectedSystem = ref('linux');
const selectedArch = ref('x86_64');
const debug = ref(0);
const zts = ref(0);
const downloadByExt = ref(1);
const preBuilt = ref(1);
const enableUPX = ref(0);
const hardcodedINIData = ref('');
const buildCommand = ref('--build-cli');

// Copy states
const copiedStates = ref({
  spcDownload: false,
  downloadExt: false,
  downloadAll: false,
  downloadPkg: false,
  build: false,
  craft: false,
  doctor: false
});

// OS list
const osList = [
  { os: 'linux', label: 'Linux', disabled: false },
  { os: 'macos', label: 'macOS', disabled: false },
  { os: 'windows', label: 'Windows', disabled: false },
];

// Computed properties
const extFilter = computed(() => {
  return Object.entries(ext.value)
    .filter(([name]) => isSupported(name, selectedSystem.value))
    .map(([name]) => name);
});

const extList = computed(() => checkedExts.value.join(','));

const additionalLibs = computed(() => {
  const ls = checkedLibs.value.filter(item => libDisableList.value.indexOf(item) === -1);
  return ls.length > 0 ? ` --with-libs="${ls.join(',')}"` : '';
});

const spcCommand = computed(() => {
  switch (selectedEnv.value) {
    case 'native':
      return 'bin/spc';
    case 'spc':
      return selectedSystem.value === 'windows' ? '.\\spc.exe' : './spc';
    case 'docker':
      return 'bin/spc-alpine-docker';
    default:
      return '';
  }
});

const spcDownloadCommand = computed(() => {
  if (selectedSystem.value === 'windows') return '';
  return `curl -fsSL -o spc.tgz https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-${selectedSystem.value}-${selectedArch.value}.tar.gz && tar -zxvf spc.tgz && rm spc.tgz`;
});

const displayINI = computed(() => {
  const split = hardcodedINIData.value.split('\n');
  const validLines = split.filter(x => x.indexOf('=') >= 1);
  return validLines.length > 0 ? ' ' + validLines.map(x => `-I "${x}"`).join(' ') : '';
});

const downloadAllCommand = computed(() => {
  return `${spcCommand.value} download --all --with-php=${selectedPhpVersion.value}${preBuilt.value ? ' --prefer-pre-built' : ''}${debug.value ? ' --debug' : ''}`;
});

const downloadExtCommand = computed(() => {
  return `${spcCommand.value} download --with-php=${selectedPhpVersion.value} --for-extensions "${extList.value}"${preBuilt.value ? ' --prefer-pre-built' : ''}${debug.value ? ' --debug' : ''}`;
});

const downloadPkgCommand = computed(() => {
  return `${spcCommand.value} install-pkg upx${debug.value ? ' --debug' : ''}`;
});

const doctorCommandString = computed(() => {
  return `${spcCommand.value} doctor --auto-fix${debug.value ? ' --debug' : ''}`;
});

const buildCommandString = computed(() => {
  return `${spcCommand.value} build ${buildCommand.value} "${extList.value}"${additionalLibs.value}${debug.value ? ' --debug' : ''}${zts.value ? ' --enable-zts' : ''}${enableUPX.value ? ' --with-upx-pack' : ''}${displayINI.value}`;
});

const craftCommandString = computed(() => {
  let str = `php-version: ${selectedPhpVersion.value}\n`;
  str += `extensions: "${extList.value}"\n`;

  if (checkedTargets.value.join(',') === 'all') {
    str += 'sapi: ' + ['cli', 'fpm', 'micro', 'embed', 'frankenphp'].join(',') + '\n';
  } else {
    str += `sapi: ${checkedTargets.value.join(',')}\n`;
  }

  if (additionalLibs.value) {
    str += `libs: ${additionalLibs.value.replace('--with-libs="', '').replace('"', '').trim()}\n`;
  }

  if (debug.value) {
    str += 'debug: true\n';
  }

  str += '{{position_hold}}';

  if (enableUPX.value) {
    str += '  with-upx-pack: true\n';
  }
  if (zts.value) {
    str += '  enable-zts: true\n';
  }
  if (preBuilt.value) {
    str += '  prefer-pre-built: true\n';
  }

  if (!str.endsWith('{{position_hold}}')) {
    str = str.replace('{{position_hold}}', 'build-options:\n');
  } else {
    str = str.replace('{{position_hold}}', '');
  }

  return str;
});

// Methods
const isSupported = (extName: string, os: string) => {
  const osName = OS_MAP.get(os);
  const osSupport = ext.value[extName]?.support?.[osName] ?? 'yes';
  return osSupport === 'yes' || osSupport === 'partial';
};

const selectCommon = () => {
  checkedExts.value = [
    'apcu', 'bcmath', 'calendar', 'ctype', 'curl', 'dba', 'dom', 'exif',
    'filter', 'fileinfo', 'gd', 'iconv', 'intl', 'mbstring', 'mbregex',
    'mysqli', 'mysqlnd', 'openssl', 'opcache', 'pcntl', 'pdo', 'pdo_mysql',
    'pdo_sqlite', 'pdo_pgsql', 'pgsql', 'phar', 'posix', 'readline', 'redis',
    'session', 'simplexml', 'sockets', 'sodium', 'sqlite3', 'tokenizer',
    'xml', 'xmlreader', 'xmlwriter', 'xsl', 'zip', 'zlib',
  ];
};

const selectAll = () => {
  checkedExts.value = [...extFilter.value];
};

const onTargetChange = (event: Event) => {
  const target = (event.target as HTMLInputElement).value;
  if (target === 'all') {
    checkedTargets.value = ['all'];
  } else {
    const allIndex = checkedTargets.value.indexOf('all');
    if (allIndex !== -1) {
      checkedTargets.value.splice(allIndex, 1);
    }
  }
  buildCommand.value = checkedTargets.value.map(x => `--build-${x}`).join(' ');
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

const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text);
    // Find which command was copied and update its state
    const commandMap = {
      [spcDownloadCommand.value]: 'spcDownload',
      [downloadExtCommand.value]: 'downloadExt',
      [downloadAllCommand.value]: 'downloadAll',
      [downloadPkgCommand.value]: 'downloadPkg',
      [doctorCommandString.value]: 'doctor',
      [buildCommandString.value]: 'build',
      [craftCommandString.value]: 'craft'
    };

    const key = commandMap[text];
    if (key) {
      copiedStates.value[key] = true;
      setTimeout(() => {
        copiedStates.value[key] = false;
      }, 2000);
    }
  } catch (err) {
    console.error('Failed to copy text: ', err);
    // Fallback for older browsers
    const textArea = document.createElement('textarea');
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
  }
};

const calculateExtDepends = (input: string[]) => {
  const result = new Set<string>();

  const dfs = (node: string) => {
    let depends: string[] = [];

    if (selectedSystem.value === 'linux') {
      depends = ext.value[node]?.['ext-depends-linux'] ?? ext.value[node]?.['ext-depends-unix'] ?? ext.value[node]?.['ext-depends'] ?? [];
    } else if (selectedSystem.value === 'macos') {
      depends = ext.value[node]?.['ext-depends-macos'] ?? ext.value[node]?.['ext-depends-unix'] ?? ext.value[node]?.['ext-depends'] ?? [];
    } else if (selectedSystem.value === 'windows') {
      depends = ext.value[node]?.['ext-depends-windows'] ?? ext.value[node]?.['ext-depends'] ?? [];
    }

    if (depends.length === 0) return;

    depends.forEach(dep => {
      result.add(dep);
      dfs(dep);
    });
  };

  input.forEach(dfs);
  return Array.from(result);
};

const calculateExtLibDepends = (input: string[]) => {
  const result = new Set<string>();

  const dfsLib = (node: string) => {
    let depends: string[] = [];

    if (selectedSystem.value === 'linux') {
      depends = lib.value[node]?.['lib-depends-linux'] ?? lib.value[node]?.['lib-depends-unix'] ?? lib.value[node]?.['lib-depends'] ?? [];
    } else if (selectedSystem.value === 'macos') {
      depends = lib.value[node]?.['lib-depends-macos'] ?? lib.value[node]?.['lib-depends-unix'] ?? lib.value[node]?.['lib-depends'] ?? [];
    } else if (selectedSystem.value === 'windows') {
      depends = lib.value[node]?.['lib-depends-windows'] ?? lib.value[node]?.['lib-depends'] ?? [];
    }

    if (depends.length === 0) return;

    depends.forEach(dep => {
      result.add(dep);
      dfsLib(dep);
    });
  };

  const dfsExt = (node: string) => {
    let depends: string[] = [];

    if (selectedSystem.value === 'linux') {
      depends = ext.value[node]?.['lib-depends-linux'] ?? ext.value[node]?.['lib-depends-unix'] ?? ext.value[node]?.['lib-depends'] ?? [];
    } else if (selectedSystem.value === 'macos') {
      depends = ext.value[node]?.['lib-depends-macos'] ?? ext.value[node]?.['lib-depends-unix'] ?? ext.value[node]?.['lib-depends'] ?? [];
    } else if (selectedSystem.value === 'windows') {
      depends = ext.value[node]?.['lib-depends-windows'] ?? ext.value[node]?.['lib-depends'] ?? [];
    }

    if (depends.length === 0) return;

    depends.forEach(dep => {
      result.add(dep);
      dfsLib(dep);
    });
  };

  input.forEach(dfsExt);
  return Array.from(result);
};

// Watchers
watch(selectedSystem, () => {
  if (selectedSystem.value === 'windows') {
    selectedArch.value = 'x86_64';
  }
  // Reset related values when OS changes
  checkedExts.value = [];
  enableUPX.value = 0;
});

watch(checkedExts, (newValue) => {
  // Apply ext-depends
  extDisableList.value = calculateExtDepends(newValue);
  extDisableList.value.forEach(x => {
    if (checkedExts.value.indexOf(x) === -1) {
      checkedExts.value.push(x);
    }
  });

  checkedExts.value.sort();

  const calculated = getAllExtLibsByDeps({ ext: ext.value, lib: lib.value, os: selectedSystem.value }, checkedExts.value);
  libContain.value = calculated.libs.sort();

  // Apply lib-depends
  checkedLibs.value = [];
  libDisableList.value = calculateExtLibDepends(calculated.exts);
  libDisableList.value.forEach(x => {
    if (checkedLibs.value.indexOf(x) === -1) {
      checkedLibs.value.push(x);
    }
  });
}, { deep: true });

// I18N
const I18N = {
  zh: {
    selectExt: '选择扩展',
    buildTarget: '选择编译目标',
    buildOptions: '编译选项',
    buildEnvironment: '编译环境',
    buildEnvNative: '本地构建（Git 源码）',
    buildEnvSpc: '本地构建（独立 spc 二进制）',
    buildEnvDocker: 'Alpine Docker 构建',
    useDebug: '是否开启调试输出',
    yes: '是',
    no: '否',
    resultShow: '结果展示',
    selectCommon: '选择常用扩展',
    selectAll: '选择全部',
    selectNone: '全部取消选择',
    useZTS: '是否编译线程安全版',
    hardcodedINI: '硬编码 INI 选项',
    hardcodedINIPlacehoder: '如需要硬编码 ini，每行写一个，例如：memory_limit=2G',
    resultShowDownload: '是否展示仅下载对应扩展依赖的命令',
    downloadExtOnlyCommand: '只下载对应扩展的依赖包命令',
    downloadAllCommand: '下载所有依赖包命令',
    downloadUPXCommand: '下载 UPX 命令',
    compileCommand: '编译命令',
    downloadPhpVersion: '下载 PHP 版本',
    downloadSPCBinaryCommand: '下载 spc 二进制命令',
    selectedArch: '选择系统架构',
    selectedSystem: '选择操作系统',
    buildLibs: '要构建的库',
    depTips: '选择扩展后，不可选中的项目为必需的依赖，编译的依赖库列表中可选的为现有扩展和依赖库的可选依赖。选择可选依赖后，将生成 --with-libs 参数。',
    depTips2: '无法同时构建所有扩展，因为有些扩展之间相互冲突。请根据需要选择扩展。',
    microUnavailable: 'micro 不支持 PHP 7.4 及更早版本！',
    windowsSAPIUnavailable: 'Windows 目前不支持 fpm、embed、frankenphp 构建！',
    useUPX: '是否开启 UPX 压缩（减小二进制体积）',
    windowsDownSPCWarning: 'Windows 下请手动下载 spc.exe 二进制文件，解压到当前目录并重命名为 spc.exe！',
    usePreBuilt: '如果可能，下载预编译的依赖库（减少编译时间）',
    searchPlaceholder: '搜索扩展...',
    copy: '复制',
    copied: '已复制',
    doctorCommand: '自动检查和准备构建环境命令',
  },
  en: {
    selectExt: 'Select Extensions',
    buildTarget: 'Build Target',
    buildOptions: 'Build Options',
    buildEnvironment: 'Build Environment',
    buildEnvNative: 'Native build (Git source code)',
    buildEnvSpc: 'Native build (standalone spc binary)',
    buildEnvDocker: 'Alpine docker build',
    useDebug: 'Enable debug message',
    yes: 'Yes',
    no: 'No',
    resultShow: 'Result',
    selectCommon: 'Select common extensions',
    selectAll: 'Select all',
    selectNone: 'Unselect all',
    useZTS: 'Enable ZTS',
    hardcodedINI: 'Hardcoded INI options',
    hardcodedINIPlacehoder: 'If you need to hardcode ini, write one per line, for example: memory_limit=2G',
    resultShowDownload: 'Download with corresponding extension dependencies',
    downloadExtOnlyCommand: 'Download sources by extensions command',
    downloadAllCommand: 'Download all sources command',
    downloadUPXCommand: 'Download UPX command',
    compileCommand: 'Compile command',
    downloadPhpVersion: 'Download PHP version',
    downloadSPCBinaryCommand: 'Download spc binary command',
    selectedArch: 'Select build architecture',
    selectedSystem: 'Select Build OS',
    buildLibs: 'Select Dependencies',
    depTips: 'After selecting the extensions, the unselectable items are essential dependencies. In the compiled dependencies list, optional dependencies consist of existing extensions and optional dependencies of libraries. Optional dependencies will be added in --with-libs parameter.',
    depTips2: 'It is not possible to build all extensions at the same time, as some extensions conflict with each other. Please select the extensions you need.',
    microUnavailable: 'Micro does not support PHP 7.4 and earlier versions!',
    windowsSAPIUnavailable: 'Windows does not support fpm, embed and frankenphp build!',
    useUPX: 'Enable UPX compression (reduce binary size)',
    windowsDownSPCWarning: 'Please download the binary file manually, extract it to the current directory and rename to spc.exe on Windows!',
    usePreBuilt: 'Download pre-built dependencies if possible (reduce compile time)',
    searchPlaceholder: 'Search extensions...',
    copy: 'Copy',
    copied: 'Copied',
    doctorCommand: 'Auto-check and prepare build environment command',
  }
};
</script>

<style scoped>
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
  border-radius: 4px;
  border: 1px solid var(--vp-c-divider);
  padding: 0 4px;
  width: 300px;
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
  border-radius: 4px;
  width: calc(100% - 12px);
  padding: 4px 8px;
}

.input {
  display: block;
  border: 1px solid var(--vp-c-divider);
  border-radius: 4px;
  width: 100%;
  padding: 4px 8px;
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
