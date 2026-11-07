import { spawn } from 'node:child_process';
import { mkdirSync, createWriteStream } from 'node:fs';
import path from 'node:path';
import { environment, integration, php } from './environment.mjs';
import { captureOptions } from './options.mjs';

const site = environment();
const capture = captureOptions();
mkdirSync(capture.output, { recursive: true, mode: 0o700 });
const log = createWriteStream(path.join(capture.output, 'server.log'), { flags: 'a', mode: 0o600 });
const server = spawn(php, ['-S', new URL(site.url).host, '-t', site.root, integration + 'router.php']);
server.stdout.pipe(log);
server.stderr.pipe(log);
server.on('error', error => { console.error(error.message); process.exitCode = 1; });
server.on('exit', code => { process.exitCode = code ?? 0; });
for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, () => server.kill(signal));
