// Builds a production-ready, distributable zip of the Elemacy plugin.
//
// Pipeline:
//   1. Compile the React admin app (tsc + vite) into ../assets/admin.
//   2. Stage a whitelist of runtime files into build/elemacy.
//   3. Force ELEMACY_ENV to 'prod' so the shipped copy never points at the Vite dev server.
//   4. Install a no-dev Composer autoloader (falls back to copying the existing one).
//   5. Zip the staging folder into build/elemacy-<version>.zip.
//
// Cross-platform: pure Node + npm/composer invoked through the shell, no OS-specific tooling.

import { spawnSync } from 'node:child_process';
import { createWriteStream } from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import archiver from 'archiver';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SRC_REACT_DIR = path.resolve(SCRIPT_DIR, '..');
const PLUGIN_ROOT = path.resolve(SRC_REACT_DIR, '..');
const BUILD_DIR = path.join(PLUGIN_ROOT, 'build');
const SLUG = 'elemacy';
const STAGING_DIR = path.join(BUILD_DIR, SLUG);

// Top-level files and folders that ship to production.
const INCLUDE = [
  'elemacy.php',
  'uninstall.php',
  'readme.txt',
  'src',
  'assets',
  'languages',
];

function log(message) {
  console.log(`\n[36m▶ ${message}[0m`);
}

function run(command, args, cwd) {
  const result = spawnSync(command, args, { cwd, stdio: 'inherit', shell: true });
  if (result.status !== 0) {
    throw new Error(`Command failed: ${command} ${args.join(' ')}`);
  }
}

function hasComposer() {
  const result = spawnSync('composer', ['--version'], { stdio: 'ignore', shell: true });
  return result.status === 0;
}

async function readPluginVersion() {
  const main = await fs.readFile(path.join(PLUGIN_ROOT, 'elemacy.php'), 'utf8');
  const match = main.match(/^\s*\*\s*Version:\s*(.+)$/m);
  return match ? match[1].trim() : '0.0.0';
}

async function copyRuntimeFiles() {
  for (const entry of INCLUDE) {
    const from = path.join(PLUGIN_ROOT, entry);
    const to = path.join(STAGING_DIR, entry);
    try {
      await fs.access(from);
    } catch {
      console.warn(`  skipped (missing): ${entry}`);
      continue;
    }
    await fs.cp(from, to, { recursive: true });
    console.log(`  added: ${entry}`);
  }
}

async function forceProdEnv() {
  const target = path.join(STAGING_DIR, 'elemacy.php');
  const contents = await fs.readFile(target, 'utf8');
  const patched = contents.replace(
    /define\(\s*'ELEMACY_ENV'\s*,\s*'dev'\s*\)/,
    "define('ELEMACY_ENV', 'prod')"
  );
  if (patched === contents) {
    console.warn('  warning: ELEMACY_ENV default not found — verify production env manually.');
    return;
  }
  await fs.writeFile(target, patched);
  console.log('  ELEMACY_ENV default set to prod');
}

async function buildVendor() {
  const stagingVendor = path.join(STAGING_DIR, 'vendor');

  if (hasComposer()) {
    // Generate a clean production autoloader from a temporary composer.json copy.
    await fs.copyFile(
      path.join(PLUGIN_ROOT, 'composer.json'),
      path.join(STAGING_DIR, 'composer.json')
    );
    const lock = path.join(PLUGIN_ROOT, 'composer.lock');
    if (await exists(lock)) {
      await fs.copyFile(lock, path.join(STAGING_DIR, 'composer.lock'));
    }

    run('composer', ['install', '--no-dev', '--optimize-autoloader', '--no-interaction'], STAGING_DIR);

    // composer manifests are dev artifacts — don't ship them.
    await fs.rm(path.join(STAGING_DIR, 'composer.json'), { force: true });
    await fs.rm(path.join(STAGING_DIR, 'composer.lock'), { force: true });
    console.log('  vendor: generated no-dev autoloader via composer');
    return;
  }

  // Fallback: copy the existing autoloader without the dev package sources.
  console.warn('  composer not found — copying existing autoloader (dev packages excluded)');
  await fs.mkdir(stagingVendor, { recursive: true });
  await fs.copyFile(
    path.join(PLUGIN_ROOT, 'vendor', 'autoload.php'),
    path.join(stagingVendor, 'autoload.php')
  );
  await fs.cp(
    path.join(PLUGIN_ROOT, 'vendor', 'composer'),
    path.join(stagingVendor, 'composer'),
    { recursive: true }
  );
}

async function exists(target) {
  try {
    await fs.access(target);
    return true;
  } catch {
    return false;
  }
}

function createZip(version) {
  return new Promise((resolve, reject) => {
    const zipPath = path.join(BUILD_DIR, `${SLUG}-${version}.zip`);
    const output = createWriteStream(zipPath);
    const archive = archiver('zip', { zlib: { level: 9 } });

    output.on('close', () => resolve(zipPath));
    archive.on('error', reject);
    archive.on('warning', (err) => {
      if (err.code !== 'ENOENT') reject(err);
    });

    archive.pipe(output);
    // Nest under <slug>/ so the zip unpacks into a proper plugin folder.
    archive.directory(STAGING_DIR, SLUG);
    archive.finalize();
  });
}

async function main() {
  const version = await readPluginVersion();
  log(`Building Elemacy ${version} production package`);

  log('Compiling React admin app');
  run('npm', ['run', 'build'], SRC_REACT_DIR);

  log('Staging runtime files');
  await fs.rm(STAGING_DIR, { recursive: true, force: true });
  await fs.mkdir(STAGING_DIR, { recursive: true });
  await copyRuntimeFiles();

  log('Patching production environment');
  await forceProdEnv();

  log('Preparing Composer autoloader');
  await buildVendor();

  log('Creating zip archive');
  const zipPath = await createZip(version);

  log('Cleaning up');
  await fs.rm(STAGING_DIR, { recursive: true, force: true });

  console.log(`\n[32m✔ Production build ready:[0m ${path.relative(SRC_REACT_DIR, zipPath)}`);
}

main().catch((error) => {
  console.error(`\n[31m✖ Build failed:[0m ${error.message}`);
  process.exit(1);
});
