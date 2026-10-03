import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import humanTime from 'flarum/common/helpers/humanTime';
import classList from 'flarum/common/utils/classList';
import textContrastClass from 'flarum/common/helpers/textContrastClass';
import { stateFor, load } from '../similar';

/**
 * "Similar discussions": a calm card of links.
 *
 * Asks for nothing until it is about to be seen — an IntersectionObserver on
 * its own (empty, zero-height) placeholder — and draws nothing at all when
 * there is nothing similar.
 *
 * Attrs: discussion, variant ('foot' | 'sidebar')
 */
export default class SimilarDiscussions extends Component {
  oncreate(vnode) {
    super.oncreate(vnode);
    this.watch(vnode.dom);
  }

  onremove(vnode) {
    super.onremove(vnode);
    if (this.observer) this.observer.disconnect();
  }

  watch(dom) {
    const id = this.attrs.discussion.id();

    if (stateFor(id).status !== 'idle') return;

    if (typeof IntersectionObserver === 'undefined') {
      load(id);
      return;
    }

    // Start a screen early, so the list is there by the time the reader is.
    this.observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          this.observer.disconnect();
          load(id);
        }
      },
      { rootMargin: '0px 0px 600px 0px' }
    );
    this.observer.observe(dom);
  }

  view() {
    const variant = this.attrs.variant || 'foot';
    const state = stateFor(this.attrs.discussion.id());
    const items = state.status === 'done' ? state.items : [];

    if (!items.length) {
      // Kept in the page (empty, no height) so it can be watched.
      return <div className={`Kindred Kindred--${variant} Kindred--empty`} aria-hidden="true" />;
    }

    const headingId = `Kindred-heading-${variant}`;

    return (
      <section className={`Kindred Kindred--${variant}`} aria-labelledby={headingId}>
        <h3 className="Kindred-heading" id={headingId}>
          <Icon name="fas fa-layer-group" className="Kindred-headingIcon" />
          {app.translator.trans('ernestdefoe-kindred.forum.heading')}
        </h3>
        <ul className="Kindred-list">
          {items.map((item) => (
            <li className="Kindred-item" key={item.id}>
              <Link className="Kindred-link" href={app.route('discussion', { id: item.slug })}>
                <span className="Kindred-title">{item.title}</span>
                <span className="Kindred-meta">
                  {variant === 'foot' && item.tags && item.tags.length ? (
                    <span className="Kindred-tags">{item.tags.map((tag) => this.tagLabel(tag))}</span>
                  ) : null}
                  <span className="Kindred-replies">
                    <Icon name="far fa-comment" />
                    {app.translator.trans('ernestdefoe-kindred.forum.replies', { count: item.replyCount })}
                  </span>
                  {item.lastPostedAt ? <span className="Kindred-time">{humanTime(new Date(item.lastPostedAt))}</span> : null}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    );
  }

  /** The same markup flarum/tags draws, so the theme styles it — but a span, inside our link. */
  tagLabel(tag) {
    const style = {};
    let className = 'TagLabel';

    if (tag.color) {
      style['--tag-bg'] = tag.color;
      className = classList(className, 'colored', textContrastClass(tag.color));
    }
    if (tag.isChild) className += ' TagLabel--child';

    return (
      <span className={className} style={style}>
        <span className="TagLabel-text">
          {tag.icon ? <Icon name={tag.icon} className="TagLabel-icon" /> : null}
          <span className="TagLabel-name">{tag.name}</span>
        </span>
      </span>
    );
  }
}
