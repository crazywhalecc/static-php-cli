export function getMetaList(meta, type, name, list_name) {
  if (meta.os === 'linux') {
    return meta[type][name][list_name + '-linux'] ?? meta[type][name][list_name + '-unix'] ?? meta[type][name][list_name] ?? [];
  }
  if (meta.os === 'macos') {
    return meta[type][name][list_name + '-macos'] ?? meta[type][name][list_name + '-unix'] ?? meta[type][name][list_name] ?? [];
  }
  if (meta.os === 'windows') {
    return meta[type][name][list_name + '-windows'] ?? meta[type][name][list_name] ?? [];
  }
  return [];
}

export function getExtDepends(meta, ext_name) {
  return getMetaList(meta, 'ext', ext_name, 'ext-depends');
}

export function getExtSuggests(meta, ext_name) {
  return getMetaList(meta, 'ext', ext_name, 'ext-suggests');
}

export function getExtLibDepends(meta, ext_name) {
  const ls = getMetaList(meta, 'ext', ext_name, 'lib-depends');
  return ls;
}

export function getExtLibSuggests(meta, ext_name) {
  return getMetaList(meta, 'ext', ext_name, 'lib-suggests');
}

export function getLibDepends(meta, lib_name) {
  return getMetaList(meta, 'lib', lib_name, 'lib-depends');
}

export function getLibSuggests(meta, lib_name) {
  return getMetaList(meta, 'lib', lib_name, 'lib-suggests');
}

/**
 * Obtain the dependent lib list according to the required ext list, and sort according to the dependency
 * @param meta
 * @param exts
 */
export function getExtLibsByDeps(meta, exts) {
  const sorted = [];
  const visited = new Set();
  const notIncludedExts = [];
  exts.forEach((ext) => {
    if (!visited.has(ext)) {
      visitExtDeps(meta, ext, visited, sorted);
    }
  });

  const sortedSuggests = [];
  const visitedSuggests = new Set();
  const final = [];
  exts.forEach((ext) => {
    if (!visited.has(ext)) {
      visitExtAllDeps(meta, ext, visitedSuggests, sortedSuggests);
    }
  });
  sortedSuggests.forEach((suggest) => {
    if (sorted.indexOf(suggest) !== -1) {
      final.push(suggest);
    }
  });
  const libs = [];
  final.forEach((ext) => {
    if (exts.indexOf(ext) === -1) {
      notIncludedExts.push(ext);
    }
    getExtLibDepends(meta, ext).forEach((lib) => {
      if (libs.indexOf(lib) === -1) {
        libs.push(lib);
      }
    });
  });

  return { exts: final, libs: getLibsByDeps(meta, libs), notIncludedExts: notIncludedExts };
}

export function getAllExtLibsByDeps(meta, exts) {
  const sorted = [];
  const visited = new Set();
  const notIncludedExts = [];
  exts.forEach((ext) => {
    if (!visited.has(ext)) {
      visitExtAllDeps(meta, ext, visited, sorted);
    }
  });
  const libs = [];
  sorted.forEach((ext) => {
    if (exts.indexOf(ext) === -1) {
      notIncludedExts.push(ext);
    }
    const allLibs = [...getExtLibDepends(meta, ext), ...getExtLibSuggests(meta, ext)];
    allLibs.forEach((dep) => {
      if (libs.indexOf(dep) === -1) {
        libs.push(dep);
      }
    });
  });
  return { exts: sorted, libs: getAllLibsByDeps(meta, libs), notIncludedExts: notIncludedExts };
}

export function getAllLibsByDeps(meta, libs) {
  const sorted = [];
  const visited = new Set();

  libs.forEach((lib) => {
    if (!visited.has(lib)) {
      console.log('before visited');
      console.log(visited);
      visitLibAllDeps(meta, lib, visited, sorted);
      console.log('after visited');
      console.log(visited);
    }
  });
  return sorted;
}

export function getLibsByDeps(meta, libs) {
  const sorted = [];
  const visited = new Set();

  libs.forEach((lib) => {
    if (!visited.has(lib)) {
      visitLibDeps(meta, lib, visited, sorted);
    }
  });

  const sortedSuggests = [];
  const visitedSuggests = new Set();
  const final = [];
  libs.forEach((lib) => {
    if (!visitedSuggests.has(lib)) {
      visitLibAllDeps(meta, lib, visitedSuggests, sortedSuggests);
    }
  });
  sortedSuggests.forEach((suggest) => {
    if (sorted.indexOf(suggest) !== -1) {
      final.push(suggest);
    }
  });
  return final;
}

export function visitLibAllDeps(meta, lib_name, visited, sorted) {
  if (visited.has(lib_name)) {
    return;
  }
  visited.add(lib_name);
  const allLibs = [...getLibDepends(meta, lib_name), ...getLibSuggests(meta, lib_name)];
  allLibs.forEach((dep) => {
    visitLibDeps(meta, dep, visited, sorted);
  });
  sorted.push(lib_name);
}

export function visitLibDeps(meta, lib_name, visited, sorted) {
  if (visited.has(lib_name)) {
    return;
  }
  visited.add(lib_name);
  getLibDepends(meta, lib_name).forEach((dep) => {
    visitLibDeps(meta, dep, visited, sorted);
  });
  sorted.push(lib_name);
}

export function visitExtDeps(meta, ext_name, visited, sorted) {
  if (visited.has(visited)) {
    return;
  }
  visited.add(ext_name);
  getExtDepends(meta, ext_name).forEach((dep) => {
    visitExtDeps(meta, dep, visited, sorted);
  });
  sorted.push(ext_name);
}

export function visitExtAllDeps(meta, ext_name, visited, sorted) {
  if (visited.has(ext_name)) {
    return;
  }
  visited.add(ext_name);

  const allExts = [...getExtDepends(meta, ext_name), ...getExtSuggests(meta, ext_name)];
  allExts.forEach((dep) => {
    visitExtDeps(meta, dep, visited, sorted);
  });
  sorted.push(ext_name);
}