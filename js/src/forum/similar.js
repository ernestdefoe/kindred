import app from 'flarum/forum/app';

/**
 * One answer per discussion, shared by the foot of the page and the sidebar,
 * so however many places show it, there is one request per discussion view.
 *
 * Kept only for the discussion being read: open another and the old one goes,
 * so coming back asks again (and sees anything new since).
 */
let current = null;

export function stateFor(discussionId) {
  if (!current || current.id !== discussionId) {
    current = { id: discussionId, status: 'idle', items: [] };
  }

  return current;
}

export function load(discussionId) {
  const state = stateFor(discussionId);
  if (state.status !== 'idle') return;

  state.status = 'loading';

  app
    .request({
      method: 'GET',
      url: `${app.forum.attribute('apiUrl')}/kindred/${encodeURIComponent(discussionId)}`,
      // A missing list is never worth an error alert on someone's reading.
      errorHandler: () => {},
    })
    .then(
      (response) => {
        state.items = Array.isArray(response && response.data) ? response.data : [];
        state.status = 'done';
      },
      () => {
        state.items = [];
        state.status = 'done';
      }
    )
    .finally(() => m.redraw());
}
