import selectBox from '../../common/selectBox';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import { t } from '../util';

/**
 * What the dossier's windows share: a form of fields, a Save button, and a
 * save that goes through the page (which then shows the new dossier) and
 * reports errors in the window.
 */
export default class DossierModal extends FormModal {
  className() {
    return 'DossierModal Modal--medium';
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.fields()}
          <div className="Form-group DossierModal-actions">
            <Button type="submit" className="Button Button--primary" loading={this.loading}>
              {t('modals.save')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  /** A labelled field; `input` is given the id the label points at. */
  field(name, label, input, help) {
    const id = `dossier-${name}`;
    // A wrapped <select> gets the id itself, so the label points at the control.
    const control = input.tag === 'span' && Array.isArray(input.children) ? input.children[0] : input;
    control.attrs = { id, name, ...control.attrs };

    return (
      <div className={`Form-group DossierModal-field DossierModal-field--${name}`}>
        <label htmlFor={id}>{label}</label>
        {input}
        {help ? <p className="helpText">{help}</p> : null}
      </div>
    );
  }

  /** A text input bound to this.values[name]. */
  text(name, attrs = {}) {
    return (
      <input className="FormControl" type="text" value={this.values[name] ?? ''} oninput={(e) => (this.values[name] = e.target.value)} {...attrs} />
    );
  }

  textarea(name, attrs = {}) {
    return (
      <textarea className="FormControl" rows="4" value={this.values[name] ?? ''} oninput={(e) => (this.values[name] = e.target.value)} {...attrs} />
    );
  }

  /** A native select (works with every screen reader and phone picker). */
  select(name, options, attrs = {}) {
    return selectBox(
      <select className="Select-input FormControl" value={this.values[name] ?? ''} onchange={(e) => (this.values[name] = e.target.value)} {...attrs}>
        {Object.entries(options).map(([value, label]) => (
          <option value={value}>{label}</option>
        ))}
      </select>
    );
  }

  run(method, path, body) {
    this.loading = true;
    this.alertAttrs = null;

    return this.attrs.page
      .save(method, path, body, { errorHandler: this.onerror.bind(this) })
      .then((response) => {
        this.loading = false;
        return response;
      })
      .catch((error) => {
        this.loading = false;
        m.redraw();
        throw error;
      });
  }
}
