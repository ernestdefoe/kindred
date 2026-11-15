import app from 'flarum/forum/app';
import type { KindredItem, KindredResponse } from '../common/types';

export interface SimilarState {
  id: string;
  status: 'idle' | 'loading' | 'done';
  items: KindredItem[];
}

/**
 * One answer per discussion, shared by the foot of the page and the sidebar,
 * so however many places show it, there is one request per discussion view.
 *
 * Kept only for the discussion being read: open another and the old one goes,
 * so coming back asks again (and sees anything new since).
 */
let current: SimilarState | null = null;

export function stateFor(discussionId: string): SimilarState {
  if (!current || current.id !== discussionId) {
    current = { id: discussionId, status: 'idle', items: [] };
  }

  return current;
}

export function load(discussionId: string): void {
  const state = stateFor(discussionId);
  if (state.status !== 'idle') return;

  state.status = 'loading';

  app
    .request<KindredResponse>({
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
