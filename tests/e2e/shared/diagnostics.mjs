// Chromium rejects the outgoing cross-document transition's ready promise
// when that document is hidden. Retain this specific native cancellation as
// evidence; never treat application exceptions or other AbortErrors as benign.
export function isEditorNavigationCancellation(error, navigations) {
  if (error.name !== 'AbortError' || error.message !== 'Transition was skipped' || error.stack !== '') return false;
  return navigations.some(navigation => {
    const from = new URL(navigation.from);
    const to = new URL(navigation.to);
    return Math.abs(error.time - navigation.time) <= 2000
      && from.origin === to.origin
      && from.pathname === '/wp-admin/admin.php' && from.searchParams.get('page') === 'elemacy'
      && to.pathname === '/wp-admin/post.php' && to.searchParams.get('action') === 'elementor';
  });
}
