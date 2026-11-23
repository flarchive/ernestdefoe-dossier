import selectBox from '../../common/selectBox';
import Component from 'flarum/common/Component';
import { t } from '../util';

/**
 * A month and a year ("2022-05"): a month list and a year box. Works the same
 * in every browser, where <input type="month"> is a text box in Safari and
 * Firefox on the desktop.
 */
export default class MonthField extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    // Its own copy of both halves: each change reports the pair, whatever was redrawn in between.
    [this.year, this.month] = (this.attrs.value || '').split('-');
  }

  view() {
    const { year, month } = this;
    const set = (y, mo) => {
      this.year = y;
      this.month = mo;
      this.attrs.onchange(y && mo ? `${y}-${mo}` : y || mo ? `${y || ''}-${mo || ''}` : '');
    };

    return (
      <div className="DossierMonth">
        {selectBox(
          <select
            className="Select-input FormControl"
            id={this.attrs.id}
            name={this.attrs.name}
            aria-label={t('modals.month')}
            value={month || ''}
            onchange={(e) => set(this.year, e.target.value)}
            disabled={this.attrs.disabled}
          >
            <option value="">{t('modals.month')}</option>
            {Array.from({ length: 12 }, (_, i) => (
              <option value={String(i + 1).padStart(2, '0')}>{dayjs().month(i).format('MMMM')}</option>
            ))}
          </select>,
          'DossierMonth-month'
        )}
        <input
          className="FormControl DossierMonth-year"
          type="number"
          inputmode="numeric"
          min="1900"
          max={new Date().getFullYear() + 10}
          placeholder={t('modals.year')}
          aria-label={t('modals.year')}
          value={year || ''}
          oninput={(e) => set(e.target.value, this.month)}
          disabled={this.attrs.disabled}
        />
      </div>
    );
  }
}
