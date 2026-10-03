import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import SimilarDiscussions from './components/SimilarDiscussions';

/** The element with this class among a vnode's children, searched depth-first. */
function findByClass(vnode, className, depth = 0) {
  if (!vnode || typeof vnode !== 'object' || depth > 6) return null;
  const attrs = vnode.attrs || {};
  const cls = String(attrs.className || attrs.class || '');
  if (typeof vnode.tag === 'string' && cls.split(/\s+/).includes(className)) return vnode;

  const children = Array.isArray(vnode.children) ? vnode.children : [];
  for (const child of children) {
    const found = findByClass(child, className, depth + 1);
    if (found) return found;
  }

  return null;
}

app.initializers.add('ernestdefoe-kindred', () => {
  /*
   * Below the post stream, inside the page's content column. DiscussionPage is
   * code-split, so it is extended by path. The block is only added once the
   * stream holds the discussion's last post — before that, the end is
   * somewhere the reader hasn't got to.
   */
  extend('flarum/forum/components/DiscussionPage', 'view', function (vnode) {
    try {
      const discussion = this.discussion;
      const stream = this.stream;
      if (!discussion || !stream || !stream.viewingEnd()) return;

      const holder = findByClass(vnode, 'DiscussionPage-stream');
      if (!holder) return;

      if (!Array.isArray(holder.children)) holder.children = holder.children == null ? [] : [holder.children];
      holder.children.push(<SimilarDiscussions discussion={discussion} variant="foot" />);
    } catch (e) {
      if (app.forum && app.forum.attribute('debug')) console.warn('Kindred:', e);
    }
  });

  /*
   * Optionally the sidebar too, on wide screens (the sidebar folds away on
   * phones, and CSS hides it there). Off by default.
   */
  extend('flarum/forum/components/DiscussionPage', 'sidebarItems', function (items) {
    try {
      if (!this.discussion || !app.forum.attribute('kindredSidebar')) return;
      items.add('kindred', <SimilarDiscussions discussion={this.discussion} variant="sidebar" />, -110);
    } catch (e) {
      if (app.forum && app.forum.attribute('debug')) console.warn('Kindred:', e);
    }
  });
});
