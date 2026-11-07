import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isEditorNavigationCancellation } from './diagnostics.mjs';

const navigation = { from: 'http://elemacy-release.test/wp-admin/admin.php?page=elemacy',
  to: 'http://elemacy-release.test/wp-admin/post.php?post=5&action=elementor', time: 10000 };
const cancellation = { name: 'AbortError', message: 'Transition was skipped', stack: '', time: 10100 };

test('native cancellation is recognized only around the Elemacy-to-editor navigation', () => {
  assert.equal(isEditorNavigationCancellation(cancellation, [navigation]), true);
  for (const change of [{ name: 'Error' }, { message: 'Submission aborted' }, { stack: 'at plugin.js:1' }, { time: 20000 }]) {
    assert.equal(isEditorNavigationCancellation({ ...cancellation, ...change }, [navigation]), false);
  }
  for (const change of [{ from: 'http://elemacy-release.test/wp-admin/index.php' },
    { to: 'http://elemacy-release.test/wp-admin/post.php?post=5&action=edit' },
    { to: 'http://other.test/wp-admin/post.php?action=elementor' }]) {
    assert.equal(isEditorNavigationCancellation(cancellation, [{ ...navigation, ...change }]), false);
  }
  assert.equal(isEditorNavigationCancellation(cancellation, []), false);
});
