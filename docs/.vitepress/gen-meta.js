#!/usr/bin/env node
/**
 * docs:gen-meta — Pre-build metadata generator for VitePress docs.
 *
 * Checks that the environment is ready (PHP installed, composer dependencies
 * present, bin/spc executable), then runs:
 *   bin/spc dev:gen-deps-data   → docs/.vitepress/deps-data.json
 *   bin/spc dev:gen-ext-docs    → docs/.vitepress/ext-data.json
 */

'use strict'

const { execSync, spawnSync } = require('child_process')
const fs = require('fs')
const path = require('path')

// __dirname is docs/.vitepress/, so two levels up is the project root
const ROOT = path.resolve(__dirname, '../..')

function fail(msg) {
  console.error(`\x1b[31m[gen-meta] ERROR: ${msg}\x1b[0m`)
  process.exit(1)
}

function info(msg) {
  console.log(`\x1b[36m[gen-meta] ${msg}\x1b[0m`)
}

function ok(msg) {
  console.log(`\x1b[32m[gen-meta] ${msg}\x1b[0m`)
}

// --- 1. Check PHP ------------------------------------------------------------
info('Checking PHP installation...')
const phpResult = spawnSync('php', ['--version'], { encoding: 'utf8' })
if (phpResult.status !== 0 || phpResult.error) {
  fail(
    'PHP is not installed or not in PATH.\n' +
    '  Please install PHP 8.1+ and ensure it is available in your PATH.'
  )
}
const phpVersion = phpResult.stdout.split('\n')[0].trim()
ok(`Found: ${phpVersion}`)

// --- 2. Check composer CLI ---------------------------------------------------
info('Checking composer...')
const composerCheck = spawnSync('composer', ['--version'], { encoding: 'utf8' })
if (composerCheck.status !== 0 || composerCheck.error) {
  fail(
    'composer is not installed or not in PATH.\n' +
    '  Please install Composer: https://getcomposer.org/download/'
  )
}
ok(`Found: ${composerCheck.stdout.split('\n')[0].trim()}`)

// --- 3. Install composer dependencies if missing ----------------------------
info('Checking composer dependencies...')
const autoload = path.join(ROOT, 'vendor', 'autoload.php')
if (!fs.existsSync(autoload)) {
  info('vendor/autoload.php not found — running composer install --no-dev ...')
  const installResult = spawnSync('composer', ['install', '--no-dev'], {
    cwd: ROOT,
    stdio: 'inherit',
  })
  if (installResult.status !== 0) {
    fail('composer install --no-dev failed (exit code ' + installResult.status + ').')
  }
  ok('Composer dependencies installed.')
} else {
  ok('Composer vendor directory found.')
}

// --- 4. Check bin/spc --------------------------------------------------------
info('Checking bin/spc...')
const spc = path.join(ROOT, 'bin', 'spc')
if (!fs.existsSync(spc)) {
  fail('bin/spc not found. Make sure you are in the project root.')
}
// Quick sanity check — list commands
const spcCheck = spawnSync('php', [spc, 'list', '--format=txt'], {
  cwd: ROOT,
  encoding: 'utf8',
  env: { ...process.env, SPC_EXECUTION_SOURCE: '1' },
})
if (spcCheck.status !== 0) {
  fail(
    'bin/spc failed to run.\n' +
    (spcCheck.stderr ?? '') +
    '\n  Make sure PHP extensions required by static-php-cli are available.'
  )
}
ok('bin/spc is operational.')

// --- 5. Run dev:gen-deps-data ------------------------------------------------
info('Running bin/spc dev:gen-deps-data ...')
const depsResult = spawnSync('php', [spc, 'dev:gen-deps-data'], {
  cwd: ROOT,
  stdio: 'inherit',
  env: { ...process.env, SPC_EXECUTION_SOURCE: '1' },
})
if (depsResult.status !== 0) {
  fail('dev:gen-deps-data failed (exit code ' + depsResult.status + ').')
}
ok('deps-data.json generated.')

// --- 6. Run dev:gen-ext-docs -------------------------------------------------
info('Running bin/spc dev:gen-ext-docs ...')
const extResult = spawnSync('php', [spc, 'dev:gen-ext-docs'], {
  cwd: ROOT,
  stdio: 'inherit',
  env: { ...process.env, SPC_EXECUTION_SOURCE: '1' },
})
if (extResult.status !== 0) {
  fail('dev:gen-ext-docs failed (exit code ' + extResult.status + ').')
}
ok('ext-data.json generated.')

ok('Metadata generation complete. Proceeding to VitePress build...')
