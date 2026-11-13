import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import DossierModal from './DossierModal';
import { t } from '../util';
import catalogue from './catalogue';

/** The chips the admin offers: collaboration, mentoring and so on. */
export default class OpenToModal extends DossierModal {
  oninit(vnode) {
    super.oninit(vnode);
    this.chosen = new Set((this.attrs.page.dossier.openTo || []).map((option) => option.id));
    this.options = null;
    catalogue().then((data) => {
      this.options = data.openTo;
      m.redraw();
    });
  }

  title() {
    return t('modals.open_to.title');
  }

  fields() {
    if (!this.options) return <LoadingIndicator />;

    return (
      <fieldset className="Form-group DossierModal-checks">
        <legend>{t('modals.open_to.help')}</legend>
        {this.options.map((option) => (
          <label className="checkbox DossierModal-check">
            <input
              type="checkbox"
              name="openTo"
              value={option.id}
              checked={this.chosen.has(option.id)}
              onchange={(e) => (e.target.checked ? this.chosen.add(option.id) : this.chosen.delete(option.id))}
            />
            {option.label}
          </label>
        ))}
      </fieldset>
    );
  }

  onsubmit(e) {
    e.preventDefault();
    this.run('PATCH', '', { openTo: [...this.chosen] }).then(
      () => this.hide(),
      () => {}
    );
  }
}
