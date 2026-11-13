import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import saveSettings from 'flarum/admin/utils/saveSettings';
import extractText from 'flarum/common/utils/extractText';
import { t } from './util';

const KEY = 'ernestdefoe-dossier.open_to';
const DEFAULT = '[{"id":"collaboration"},{"id":"mentoring"},{"id":"freelance"},{"id":"jobs"}]';
const BUILT_IN = ['collaboration', 'mentoring', 'freelance', 'jobs'];

/**
 * The "Open to" chips members can pick. The four built-in ones are named by
 * the forum's language until renamed here; removing a chip removes it from
 * every dossier that had it.
 */
export default class OpenToEditor extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    let rows = [];
    try {
      rows = JSON.parse(app.data.settings[KEY] || DEFAULT);
    } catch (e) {
      rows = JSON.parse(DEFAULT);
    }
    this.rows = (Array.isArray(rows) ? rows : []).map((row) => ({ id: String(row.id || ''), label: row.label || '' }));
    this.saving = false;
    this.saved = false;
  }

  view() {
    return (
      <div className="DossierAdmin-openTo">
        <h3>{t('open_to.heading')}</h3>
        <p className="helpText">{t('open_to.help')}</p>
        <ul className="DossierAdmin-rows">
          {this.rows.map((row, index) => (
            <li className="DossierAdmin-row" key={row.id}>
              <input
                className="FormControl"
                type="text"
                maxlength="60"
                aria-label={t('open_to.label')}
                placeholder={
                  BUILT_IN.includes(row.id) ? extractText(app.translator.trans(`ernestdefoe-dossier.lib.open_to.${row.id}`)) : t('open_to.label')
                }
                value={row.label}
                oninput={(e) => {
                  row.label = e.target.value;
                  this.saved = false;
                }}
              />
              <Button
                className="Button Button--icon"
                icon="fas fa-times"
                aria-label={t('open_to.remove')}
                onclick={() => {
                  this.rows.splice(index, 1);
                  this.saved = false;
                }}
              />
            </li>
          ))}
        </ul>
        <div className="DossierAdmin-actions">
          <Button
            className="Button"
            icon="fas fa-plus"
            onclick={() => {
              this.rows.push({ id: 'o' + Math.random().toString(36).slice(2, 10), label: '' });
              this.saved = false;
            }}
          >
            {t('open_to.add')}
          </Button>
          <Button className="Button Button--primary" loading={this.saving} onclick={() => this.save()}>
            {this.saved ? t('saved') : t('save')}
          </Button>
        </div>
      </div>
    );
  }

  save() {
    // A new chip needs a name; a built-in one falls back to its translated name.
    const rows = this.rows
      .map((row) => ({ id: row.id, label: row.label.trim() }))
      .filter((row) => row.label || BUILT_IN.includes(row.id))
      .map((row) => (row.label ? row : { id: row.id }));

    this.saving = true;
    saveSettings({ [KEY]: JSON.stringify(rows) }).then(
      () => {
        this.saving = false;
        this.saved = true;
        this.rows = rows.map((row) => ({ id: row.id, label: row.label || '' }));
        m.redraw();
      },
      () => {
        this.saving = false;
        m.redraw();
      }
    );
  }
}
