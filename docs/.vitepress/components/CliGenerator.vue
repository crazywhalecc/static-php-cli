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
      <input class="input" v-model="filterText" placeholder="Highlight search..." />
      <br>
      <div v-for="item in extFilter" class="ext-item">
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
    <div class="my-btn" @click="checkedExts = []">{{ I18N[lang].selectNone }}</div>

    <details class="details custom-block" open>
      <summary>{{ I18N[lang].buildLibs }}{{ checkedLibs.length > 0 ? (' (' + checkedLibs.length + ')') : '' }}</summary>
      <div class="box">
        <div v-for="(item, index) in libContain" class="ext-item">
          <input type="checkbox" :id="index" :value="item" v-model="checkedLibs" :disabled="libDisableList.indexOf(item) !== -1">
          <label :for="index">{{ item }}</label>
        </div>
      </div>
    </details>
    <div class="tip custom-block">
      <p class="custom-block-title">TIP</p>
      <p>{{ I18N[lang].depTips }}</p>
    </div>
    <h2>{{ I18N[lang].buildTarget }}</h2>
    <div class="box">
      <div v-for="(item) in TARGET" class="ext-item">
        <input type="checkbox" :id="'build_' + item" :value="item" v-model="checkedTargets" @change="onTargetChange">
        <label :for="'build_' + item">{{ item }}</label>
      </div>
    </div>
    <div v-if="selectedPhpVersion === '7.4' && (checkedTargets.indexOf('micro') !== -1 || checkedTargets.indexOf('all') !== -1)" class="warning custom-block">
      <p class="custom-block-title">WARNING</p>
      <p>{{ I18N[lang].microUnavailable }}</p>
    </div>
    <div v-if="selectedSystem === 'windows' && (checkedTargets.indexOf('fpm') !== -1 || checkedTargets.indexOf('embed') !== -1)" class="warning custom-block">
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
            <option v-for="item in availablePhpVersions" :value="item">{{ item }}</option>
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
    <div v-if="selectedEnv === 'spc'" class="command-container">
      <b>{{ I18N[lang].downloadSPCBinaryCommand }}</b>
      <div class="command-preview" v-if="selectedSystem !== 'windows'">
        curl -fsSL -o spc.tgz https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-{{ selectedSystem }}-{{ selectedArch }}.tar.gz && tar -zxvf spc.tgz && rm spc.tgz<br>
      </div>
      <div v-else>
        <div class="warning custom-block">
          <p class="custom-block-title">WARNING</p>
          <p>{{ I18N[lang].windowsDownSPCWarning }}</p>
          <a href="https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe" target="_blank">https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe</a>
        </div>
      </div>
    </div>
    <div v-if="downloadByExt" class="command-container">
      <b>{{ I18N[lang].downloadExtOnlyCommand }}</b>
      <div id="download-ext-cmd" class="command-preview">
        {{ downloadExtCommand }}
      </div>
    </div>
    <div v-else class="command-container">
      <b>{{ I18N[lang].downloadAllCommand }}</b>
      <div id="download-all-cmd" class="command-preview">
        {{ downloadAllCommand }}
      </div>
    </div>
    <div class="command-container" v-if="enableUPX">
      <b>{{ I18N[lang].downloadUPXCommand }}</b>
      <div id="download-pkg-cmd" class="command-preview">
        {{ downloadPkgCommand }}
      </div>
    </div>
    <div class="command-container">
      <b>{{ I18N[lang].compileCommand }}</b>
      <div id="build-cmd" class="command-preview">
        {{ buildCommandString }}
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

const ext = ref(extData);
const extFilter = computed(() => {
  const ls = [];
  for (const [name, item] of Object.entries(ext.value)) {
    if (isSupported(name, selectedSystem.value)) {
      ls.push(name);
    }
  }
  return ls;
});
const lib = ref(libData);
const libContain = ref([]);

defineProps({
  lang: {
    type: String,
    default: 'zh',
  }
});

const osList = [
  { os: 'linux', label: 'Linux', disabled: false },
  { os: 'macos', label: 'macOS', disabled: false },
  { os: 'windows', label: 'Windows', disabled: false },
];

const isSupported = (extName, os) => {
  // Convert os to target: linux->Linux, macos->Darwin, windows->Windows (using map)
  const a = new Map([['linux', 'Linux'], ['macos', 'Darwin'], ['windows', 'Windows']]);
  const osName = a.get(os);
  const osSupport = ext.value[extName]?.support?.[osName] ?? 'yes';
  return osSupport === 'yes' || osSupport === 'partial';
};

const availablePhpVersions = [
  '7.4',
  '8.0',
  '8.1',
  '8.2',
  '8.3',
];

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
    microUnavailable: 'micro 不支持 PHP 7.4 及更早版本！',
    windowsSAPIUnavailable: 'Windows 目前不支持 fpm、embed 构建！',
    useUPX: '是否开启 UPX 压缩（减小二进制体积）',
    windowsDownSPCWarning: 'Windows 下请手动下载 spc.exe 二进制文件，解压到当前目录并重命名为 spc.exe！',
    usePreBuilt: '如果可能，下载预编译的依赖库（减少编译时间）',
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
    microUnavailable: 'Micro does not support PHP 7.4 and earlier versions!',
    windowsSAPIUnavailable: 'Windows does not support fpm and embed build!',
    useUPX: 'Enable UPX compression (reduce binary size)',
    windowsDownSPCWarning: 'Please download the binary file manually, extract it to the current directory and rename to spc.exe on Windows!',
    usePreBuilt: 'Download pre-built dependencies if possible (reduce compile time)',
  }
};

const TARGET = [
  'cli',
  'fpm',
  'micro',
  'embed',
  'all',
];

const selectCommon = () => {
  checkedExts.value = [
      'apcu', 'bcmath',
      'calendar', 'ctype', 'curl',
      'dba', 'dom', 'exif',
      'filter', 'fileinfo',
      'gd', 'iconv', 'intl',
      'mbstring', 'mbregex', 'mysqli', 'mysqlnd',
      'openssl', 'opcache',
      'pcntl', 'pdo',
      'pdo_mysql', 'pdo_sqlite', 'pdo_pgsql',
      'pgsql', 'phar', 'posix',
      'readline', 'redis',
      'session', 'simplexml', 'sockets',
      'sodium', 'sqlite3', 'tokenizer',
      'xml', 'xmlreader', 'xmlwriter',
      'xsl', 'zip', 'zlib',
  ];
};

const extList = computed(() => {
  return checkedExts.value.join(',');
});

const additionalLibs = computed(() => {
  const ls = checkedLibs.value.filter(item => libDisableList.value.indexOf(item) === -1);
  if (ls.length > 0) {
    return ' --with-libs="' + ls.join(',') + '"';
  }
  return '';
});

// chosen extensions
const checkedExts = ref([]);

const checkedLibs = ref([]);

const extDisableList = ref([]);
const libDisableList = ref([]);

// chose targets
const checkedTargets = ref(['cli']);

// chosen env
const selectedEnv = ref('spc');

// chosen php version
const selectedPhpVersion = ref('8.2');

// chosen debug
const debug = ref(0);

// chosen zts
const zts = ref(0);

// chosen download by extensions
const downloadByExt = ref(1);

// use pre-built
const preBuilt = ref(1);

// chosen upx
const enableUPX = ref(0);

const hardcodedINIData = ref('');

const selectedSystem = ref('linux');

watch(selectedSystem, () => {
  if (selectedSystem.value === 'windows') {
    selectedArch.value = 'x86_64';
  }
});

const selectedArch = ref('x86_64');

// spc command string, alt: spc-alpine-docker, spc
const spcCommand = computed(() => {
  switch (selectedEnv.value) {
    case 'native':
      return 'bin/spc';
    case 'spc':
      if (selectedSystem.value === 'windows') {
        return '.\\spc.exe';
      }
      return './spc';
    case 'docker':
      return 'bin/spc-alpine-docker';
    default:
      return '';
  }
});

// build target string
const buildCommand = ref('--build-cli');

const displayINI = computed(() => {
  const split = hardcodedINIData.value.split('\n');
  let str = [];
  split.forEach((x) => {
    if (x.indexOf('=') >= 1) {
      str.push(x);
    }
  });
  return ' ' + str.map((x) => '-I "' + x + '"').join(' ');
});

const filterText = ref('');

const highlightItem = (item, step) => {
  if (item.includes(filterText.value)) {
    if (step === 0) {
      return item.substring(0, item.indexOf(filterText.value));
    } else if (step === 1) {
      return filterText.value;
    } else {
      return item.substring(item.indexOf(filterText.value) + filterText.value.length);
    }
  } else {
    if (step === 0) {
      return item;
    }
    return '';
  }
};

const onTargetChange = (event) => {
  let id;
  if (checkedTargets.value.indexOf('all') !== -1 && event.target.value === 'all') {
    checkedTargets.value = ['all'];
  } else if ((id = checkedTargets.value.indexOf('all')) !== -1 && event.target.value !== 'all') {
    checkedTargets.value.splice(id, 1);
  }
  buildCommand.value = checkedTargets.value.map((x) => '--build-' + x).join(' ');
};

const calculateExtDepends = (input) => {
  const result = new Set();

  const dfs = (node) => {
    let depends = [];
    // 计算深度前，先要确认fallback的 ext-depends
    if (selectedSystem.value === 'linux') {
      depends = ext.value[node]['ext-depends-linux'] ?? ext.value[node]['ext-depends-unix'] ?? ext.value[node]['ext-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    } else if (selectedSystem.value === 'macos') {
      depends = ext.value[node]['ext-depends-macos'] ?? ext.value[node]['ext-depends-unix'] ?? ext.value[node]['ext-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    } else if (selectedSystem.value === 'windows') {
      depends = ext.value[node]['ext-depends-windows'] ?? ext.value[node]['ext-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    }

    depends.forEach((dep) => {
      result.add(dep);
      dfs(dep);
    });
  };

  input.forEach((item) => {
    dfs(item);
  });

  return Array.from(result);
};

const downloadAllCommand = computed(() => {
  return `${spcCommand.value} download --all --with-php=${selectedPhpVersion.value}${preBuilt.value ? ' --prefer-pre-built' : ''}${debug.value ? ' --debug' : ''}`;
});

const downloadExtCommand = computed(() => {
  return `${spcCommand.value} download --with-php=${selectedPhpVersion.value} --for-extensions "${extList.value}"${preBuilt.value ? ' --prefer-pre-built' : ''}${debug.value ? ' --debug' : ''}`;
});

const downloadPkgCommand = computed(() => {
  return `${spcCommand.value} install-pkg upx${debug.value ? ' --debug' : ''}`;
});

const buildCommandString = computed(() => {
  return `${spcCommand.value} build ${buildCommand.value} "${extList.value}"${additionalLibs.value}${debug.value ? ' --debug' : ''}${zts.value ? ' --enable-zts' : ''}${enableUPX.value ? ' --with-upx-pack' : ''}${displayINI.value}`;
});

const calculateExtLibDepends = (input) => {
  const result = new Set();

  const dfsLib = (node) => {
    let depends = [];
    if (selectedSystem.value === 'linux') {
      depends = lib.value[node]['lib-depends-linux'] ?? lib.value[node]['lib-depends-unix'] ??lib.value[node]['lib-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    } else if (selectedSystem.value === 'macos') {
      depends = lib.value[node]['lib-depends-macos'] ?? lib.value[node]['lib-depends-unix'] ??lib.value[node]['lib-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    } else if (selectedSystem.value === 'windows') {
      depends = lib.value[node]['lib-depends-windows'] ?? lib.value[node]['lib-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    }

    depends.forEach((dep) => {
      result.add(dep);
      dfsLib(dep);
    });
  };

  const dfsExt = (node) => {
    let depends = [];
    // 计算深度前，先要确认fallback的 lib-depends
    if (selectedSystem.value === 'linux') {
      depends = ext.value[node]['lib-depends-linux'] ?? ext.value[node]['lib-depends-unix'] ?? ext.value[node]['lib-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    } else if (selectedSystem.value === 'macos') {
      depends = ext.value[node]['lib-depends-macos'] ?? ext.value[node]['lib-depends-unix'] ?? ext.value[node]['lib-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    } else if (selectedSystem.value === 'windows') {
      depends = ext.value[node]['lib-depends-windows'] ?? ext.value[node]['lib-depends'] ?? [];
      if (depends.length === 0) {
        return;
      }
    }

    depends.forEach((dep) => {
      result.add(dep);
      dfsLib(dep);
    });
  };

  input.forEach((item) => {
    dfsExt(item);
  });

  return Array.from(result);
};

// change os, clear ext
watch(selectedSystem, () => checkedExts.value = []);

// change os, reset upx
watch(selectedSystem, () => enableUPX.value = 0);

// selected ext change, calculate deps
watch(
    checkedExts,
    (newValue) => {
      // apply ext-depends
      extDisableList.value = calculateExtDepends(newValue);
      extDisableList.value.forEach((x) => {
        if (checkedExts.value.indexOf(x) === -1) {
          checkedExts.value.push(x);
        }
      });

      checkedExts.value.sort();
      console.log('检测到变化！');
      console.log(newValue);

      const calculated = getAllExtLibsByDeps({ ext: ext.value, lib: lib.value, os: selectedSystem.value }, checkedExts.value);
      libContain.value = calculated.libs.sort();
      // apply lib-depends
      checkedLibs.value = [];
      libDisableList.value = calculateExtLibDepends(calculated.exts);
      libDisableList.value.forEach((x) => {
        if (checkedLibs.value.indexOf(x) === -1) {
          checkedLibs.value.push(x);
        }
      });
    },
);
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
  padding: 1.2rem;
  background: var(--vp-c-divider);
  border-radius: 8px;
  word-break: break-all;
  font-family: monospace;
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
</style>
