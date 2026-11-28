import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import { t } from '../util';
import { monthIndex, currentMonth, monthLabel, durationLabel } from '../dates';

const YEARS = 40; // width of the year column
const LANE = 14; // one lane of bars
const GAP = 12; // between cards
const KIND_ICONS = { work: 'fas fa-briefcase', education: 'fas fa-graduation-cap' };

/**
 * Work and education on one vertical time scale, newest at the top. Each entry
 * is a bar as long as it lasted; entries that overlap in time get lanes side by
 * side. The cards sit beside the scale at the height their entry ended, pushed
 * down only as far as the card above needs, with a line back to their bar.
 *
 * The scale is chosen after the cards are measured, so the chart is about as
 * tall as the cards it holds: a long career does not become a tall empty rail,
 * and a busy year does not pile its cards far from their bars.
 */
export default class Timeline extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.layout = null;
    this.measured = '';
  }

  /** Entries with their months, newest first, and the lane each one runs in. */
  items() {
    const now = currentMonth() + 1;
    const items = this.attrs.entries.map((entry) => {
      const start = monthIndex(entry.start);
      const end = entry.end ? monthIndex(entry.end) + 1 : now;
      return { entry, start, end: Math.max(end, start + 1), lane: 0 };
    });

    // Lanes: in start order, each entry takes the first lane free by then.
    const lanes = [];
    [...items]
      .sort((a, b) => a.start - b.start || b.end - a.end)
      .forEach((item) => {
        let lane = lanes.findIndex((end) => end <= item.start);
        if (lane === -1) lane = lanes.push(0) - 1;
        lanes[lane] = item.end;
        item.lane = lane;
      });

    items.sort((a, b) => b.end - a.end || b.start - a.start);

    return { items, lanes: Math.max(lanes.length, 1) };
  }

  view() {
    const { items, lanes } = this.items();
    const first = Math.min(...items.map((i) => i.start));
    const last = Math.max(...items.map((i) => i.end));
    const rail = YEARS + lanes * LANE;
    const layout = this.layout && this.layout.tops.length === items.length ? this.layout : null;
    const y = (month) => (last - month) * layout.scale;
    const kinds = new Set(items.map((i) => i.entry.kind));

    return (
      <div className={'DossierTimeline' + (layout ? ' DossierTimeline--ready' : '')} style={{ '--dossier-rail': rail + 'px' }}>
        {kinds.size > 1 ? (
          <ul className="DossierTimeline-legend" aria-hidden="true">
            {['work', 'education'].map((kind) => (
              <li className={`DossierTimeline-key DossierTimeline-key--${kind}`}>{t(`timeline.kinds.${kind}`)}</li>
            ))}
          </ul>
        ) : null}
        <div className="DossierTimeline-chart" style={layout ? { height: layout.height + 'px' } : null}>
          {layout ? (
            <div className="DossierTimeline-rail" aria-hidden="true">
              {this.years(first, last, layout.scale).map((year) => (
                <span className="DossierTimeline-year" style={{ top: y(year * 12) + 'px' }}>
                  {year}
                </span>
              ))}
              {items.map((item) => (
                <span
                  className={`DossierTimeline-bar DossierTimeline-bar--${item.entry.kind}`}
                  title={item.entry.title}
                  style={{
                    top: y(item.end) + 'px',
                    height: Math.max((item.end - item.start) * layout.scale, 4) + 'px',
                    left: YEARS + item.lane * LANE + 'px',
                  }}
                />
              ))}
              <svg className="DossierTimeline-links" width={rail + 14} height={layout.height}>
                {items.map((item, i) => {
                  const x1 = YEARS + item.lane * LANE + 4;
                  const y1 = y(item.end) + 2;
                  const x2 = rail + 14;
                  const y2 = layout.tops[i] + 17;
                  const mid = (x1 + x2) / 2;
                  return (
                    <path
                      className={`DossierTimeline-link DossierTimeline-link--${item.entry.kind}`}
                      d={`M${x1} ${y1} C${mid} ${y1} ${mid} ${y2} ${x2} ${y2}`}
                    />
                  );
                })}
              </svg>
            </div>
          ) : null}
          <ol className="DossierTimeline-cards">{items.map((item, i) => this.card(item, layout ? layout.tops[i] : null))}</ol>
        </div>
      </div>
    );
  }

  card(item, top) {
    const { entry } = item;
    const place = [entry.organization, entry.location].filter(Boolean).join(' · ');

    return (
      <li className={`DossierTimelineCard DossierTimelineCard--${entry.kind}`} key={entry.id} style={top === null ? null : { top: top + 'px' }}>
        <span className="DossierTimelineCard-dot" aria-hidden="true" />
        <div className="DossierTimelineCard-head">
          <Icon name={KIND_ICONS[entry.kind]} className="DossierTimelineCard-icon" />
          <span className="visually-hidden">{t(`timeline.kinds.${entry.kind}`)}: </span>
          <span className="DossierTimelineCard-title">{entry.title}</span>
        </div>
        {place ? <div className="DossierTimelineCard-place">{place}</div> : null}
        <div className="DossierTimelineCard-dates">
          {monthLabel(entry.start)} – {entry.end ? monthLabel(entry.end) : t('timeline.present')}
          <span className="DossierTimelineCard-duration"> · {durationLabel(item.end - item.start)}</span>
        </div>
        {entry.description ? <p className="DossierTimelineCard-description">{entry.description}</p> : null}
        {this.attrs.controls(entry)}
      </li>
    );
  }

  /** Year marks, spaced so their labels never touch. */
  years(first, last, scale) {
    const step = [1, 2, 5, 10, 20].find((s) => s * 12 * scale >= 22) || 50;
    const years = [];
    for (let year = Math.ceil(first / 12 / step) * step; year * 12 <= last; year += step) years.push(year);
    return years;
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    this.measure();
    // A new width wraps the cards differently: lay them out again.
    if (window.ResizeObserver) {
      this.observer = new ResizeObserver(() => this.measure());
      this.observer.observe(this.element);
    }
  }

  onupdate(vnode) {
    super.onupdate(vnode);
    this.measure();
  }

  onremove(vnode) {
    super.onremove(vnode);
    if (this.observer) this.observer.disconnect();
  }

  measure() {
    const cards = [...this.element.querySelectorAll('.DossierTimelineCard')];
    const heights = cards.map((card) => card.offsetHeight);
    const { items } = this.items();
    const key = heights.join(',') + '|' + items.map((i) => `${i.entry.id}:${i.start}:${i.end}`).join(',');

    if (!cards.length || key === this.measured || heights.some((h) => !h)) return;
    this.measured = key;

    const first = Math.min(...items.map((i) => i.start));
    const last = Math.max(...items.map((i) => i.end));
    const span = Math.max(last - first, 12);
    const total = heights.reduce((sum, h) => sum + h + GAP, 0);
    const scale = Math.min(Math.max(total / span, 1.5), 14);

    const tops = [];
    let bottom = -GAP;
    items.forEach((item, i) => {
      const top = Math.max((last - item.end) * scale, bottom + GAP);
      tops.push(top);
      bottom = top + heights[i];
    });

    this.layout = { scale, tops, height: Math.max(span * scale, bottom) + 4 };
    m.redraw();
  }
}
