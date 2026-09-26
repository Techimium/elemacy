// Manifest-driven release staging. Each plugin owns this script and its manifest.
import { spawnSync } from 'node:child_process';
import { createWriteStream } from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import archiver from 'archiver';

const scripts = path.dirname(fileURLToPath(import.meta.url));
const frontend = path.resolve(scripts, '..');
const root = path.resolve(frontend, '..');
const manifest = JSON.parse(await fs.readFile(path.join(scripts, 'package-manifest.json'), 'utf8'));
const build = path.join(root, 'build');
const staging = path.join(build, manifest.slug);
const hash = data => createHash('sha256').update(data).digest('hex');

function run(command, args, cwd, capture = false) {
  const result = spawnSync(command, args, { cwd, encoding: 'utf8', stdio: capture ? 'pipe' : 'inherit', shell: false });
  if (result.error || result.status !== 0) {
    throw new Error(`Required command failed: ${command} ${args.join(' ')}: ${result.error?.message || result.stderr || result.status}`);
  }
  return result.stdout?.trim() || '';
}

async function list(directory) {
  const files = [];
  for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
    const filename = path.join(directory, entry.name);
    if (entry.isSymbolicLink()) throw new Error(`Release inputs must not be symlinks: ${filename}`);
    if (entry.isDirectory()) files.push(...await list(filename));
    else if (entry.isFile()) files.push(filename);
  }
  return files.sort();
}

async function stage() {
  const files = new Set(manifest.requiredFiles);
  for (const [directory, extensions] of Object.entries(manifest.trees)) {
    for (const absolute of await list(path.join(root, directory))) {
      const relative = path.relative(root, absolute).split(path.sep).join('/');
      if (relative.split('/').some(part => part.startsWith('.') || ['tests', 'test', '__tests__', 'node_modules'].includes(part))) continue;
      if (manifest.exclude.some(excluded => relative === excluded || relative.startsWith(excluded))) continue;
      if (extensions.includes(path.extname(relative))) files.add(relative);
    }
  }
  for (const relative of [...files].sort()) {
    const destination = path.join(staging, relative);
    await fs.mkdir(path.dirname(destination), { recursive: true });
    await fs.copyFile(path.join(root, relative), destination);
  }
  for (const required of manifest.requiredRuntime) await fs.access(path.join(staging, required));
  if (manifest.slug === 'elemacy') {
    const bootstrap = await fs.readFile(path.join(staging, 'elemacy.php'), 'utf8');
    if (!/define\(\s*'ELEMACY_ENV'\s*,\s*'production'\s*\)/.test(bootstrap)) {
      throw new Error('ELEMACY_ENV must explicitly default to production.');
    }
  }
}

async function vendor() {
  for (const name of ['composer.json', 'composer.lock']) await fs.copyFile(path.join(root, name), path.join(staging, name));
  run('composer', ['install', '--no-dev', '--no-scripts', '--no-plugins', '--prefer-dist', '--optimize-autoloader', '--no-interaction'], staging);
  run('php', ['-r', `
    $root = $argv[1];
    require $root . '/vendor/autoload.php';
    foreach (['autoload_classmap.php', 'autoload_files.php'] as $name) {
      $file = $root . '/vendor/composer/' . $name;
      if (!file_exists($file)) continue;
      foreach (require $file as $target) {
        if (!is_file($target) || strpos(realpath($target), realpath($root) . DIRECTORY_SEPARATOR) !== 0) {
          throw new RuntimeException('Autoload metadata references an absent/external file: ' . $target);
        }
      }
    }
    foreach (require $root . '/vendor/composer/autoload_psr4.php' as $paths) {
      foreach ($paths as $target) if (!is_dir($target)) throw new RuntimeException('Missing autoload directory.');
    }
  `, staging], staging);
  // composer.json stays beside vendor/ so the package describes its own dependencies;
  // the lock also pins development packages that are not shipped.
  await fs.rm(path.join(staging, 'composer.lock'));
}

async function zip(filename, files) {
  await new Promise((resolve, reject) => {
    const output = createWriteStream(filename);
    const archive = archiver('zip', { zlib: { level: 9 } });
    output.on('close', resolve);
    output.on('error', reject);
    archive.on('error', reject);
    archive.on('warning', reject);
    archive.pipe(output);
    // Fixed timestamps and ordering keep identical staged inputs reproducible.
    for (const file of files) archive.file(path.join(staging, file.path), {
      name: `${manifest.slug}/${file.path}`, date: new Date('2000-01-01T00:00:00Z'), mode: 0o644,
    });
    archive.finalize().catch(reject);
  });
}

try {
  // Fail before modifying previous artifacts if any required tooling/input is missing.
  for (const command of ['composer', 'php', 'npm', 'git']) run(command, ['--version'], root, true);
  for (const name of ['composer.lock', 'src-react/package-lock.json']) await fs.access(path.join(root, name));
  const main = await fs.readFile(path.join(root, `${manifest.slug}.php`), 'utf8');
  const version = main.match(/^\s*\*\s*Version:\s*([0-9][^\s]*)\s*$/m)?.[1];
  if (!version || !/^[0-9A-Za-z.+-]+$/.test(version)) throw new Error('Missing or invalid plugin version.');
  const revision = run('git', ['rev-parse', 'HEAD'], root, true);
  const dirty = run('git', ['status', '--porcelain', '--untracked-files=normal'], root, true) !== '';
  run('npm', ['run', 'build'], frontend);
  await fs.rm(staging, { recursive: true, force: true });
  await fs.mkdir(staging, { recursive: true });
  await stage();
  await vendor();
  const files = [];
  for (const filename of await list(staging)) {
    files.push({ path: path.relative(staging, filename).split(path.sep).join('/'), sha256: hash(await fs.readFile(filename)) });
  }
  const filename = path.join(build, `${manifest.slug}-${version}.zip`);
  const temporary = filename + '.partial';
  await zip(temporary, files);
  await fs.rename(temporary, filename);
  const checksum = hash(await fs.readFile(filename));
  const evidence = {
    slug: manifest.slug, version, revision, dirty,
    composerLock: hash(await fs.readFile(path.join(root, 'composer.lock'))),
    frontendLock: hash(await fs.readFile(path.join(frontend, 'package-lock.json'))),
    zipSha256: checksum, files,
  };
  await fs.writeFile(filename + '.manifest.json', JSON.stringify(evidence, null, 2) + '\n');
  await fs.writeFile(filename + '.sha256', `${checksum}  ${path.basename(filename)}\n`);
  await fs.rm(staging, { recursive: true, force: true });
  console.log(`Built ${filename} (${files.length} files); manifest and SHA-256 retained. Source revision ${revision}${dirty ? ' with working-tree changes' : ''}.`);
} catch (error) {
  console.error(`Package build failed: ${error.message}`);
  process.exitCode = 1;
}
