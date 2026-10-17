import path from 'node:path';
import os from 'node:os';

export const HARNESS_VERSION = 1;

export function captureOptions(env = process.env) {
  const result = {};
  for (const [key, fallback] of [['screenshots', 'failure'], ['video', 'off'], ['trace', 'failure']]) {
    const value = env[`ELEMACY_CAPTURE_${key.toUpperCase()}`] || fallback;
    if (!['off', 'failure', 'all'].includes(value)) {
      throw new Error(`Invalid ${key} capture: ${value}. Choose off, failure, or all.`);
    }
    result[key] = value;
  }
  result.output = path.resolve(env.ELEMACY_TEST_OUTPUT || path.join(os.tmpdir(), `elemacy-journeys-${Date.now()}`));
  return result;
}

export function playwrightCapture(options) {
  return {
    screenshot: { mode: { off: 'off', failure: 'only-on-failure', all: 'on' }[options.screenshots], timeout: 15000 },
    video: { off: 'off', failure: 'retain-on-failure', all: 'on' }[options.video],
    trace: { off: 'off', failure: 'retain-on-failure', all: 'on' }[options.trace],
  };
}
