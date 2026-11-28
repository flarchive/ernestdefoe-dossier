import Icon from 'flarum/common/components/Icon';
import DossierModal from './DossierModal';
import { t } from '../util';

/**
 * Which finished builds come first on the dossier, and in what order. The
 * rest follow, newest finished first. Up and down buttons reorder the pins;
 * focus stays on the button pressed, so the keyboard can keep moving it.
 */
export default class PinBuildsModal extends DossierModal {
  oninit(vnode) {
    super.oninit(vnode);
    const d = this.attrs.page.dossier;
    this.limit = d.pinLimit;
    this.choices = d.buildChoices || [];
    this.byId = new Map(this.choices.map((build) => [build.id, build]));
    this.pinned = this.choices.filter((build) => build.pinned).map((build) => build.id);
    this.focus = null;
  }

  title() {
    return t('modals.pins.title');
  }

  fields() {
    const full = this.pinned.length >= this.limit;

    return [
      <fieldset className="Form-group DossierModal-checks DossierPins-choices">
        <legend>{t('modals.pins.help', { max: this.limit })}</legend>
        {this.choices.map((build) => {
          const checked = this.pinned.includes(build.id);

          return (
            <label className="checkbox DossierModal-check">
              <input
                type="checkbox"
                name="pinnedBuilds"
                value={build.id}
                checked={checked}
                disabled={full && !checked}
                onchange={(e) => (this.pinned = e.target.checked ? [...this.pinned, build.id] : this.pinned.filter((id) => id !== build.id))}
              />
              <span className="DossierPins-name">{build.title}</span>
            </label>
          );
        })}
      </fieldset>,
      <div className="Form-group DossierPins-order">
        <h4 className="DossierPins-heading">{t('modals.pins.order')}</h4>
        {this.pinned.length ? (
          <ol className="DossierPins-list">
            {this.pinned.map((id, index) => {
              const name = this.byId.get(id).title;

              return (
                <li className="DossierPins-item" key={id}>
                  <span className="DossierPins-position">{index + 1}</span>
                  <span className="DossierPins-name">{name}</span>
                  {this.moveButton(id, index, -1, 'fas fa-arrow-up', t('modals.pins.move_up', { title: name }))}
                  {this.moveButton(id, index, 1, 'fas fa-arrow-down', t('modals.pins.move_down', { title: name }))}
                </li>
              );
            })}
          </ol>
        ) : (
          <p className="helpText">{t('modals.pins.none')}</p>
        )}
      </div>,
    ];
  }

  moveButton(id, index, by, icon, label) {
    const name = `${id}:${by}`;
    // Focus follows the moved build; at the top or bottom it goes to the button that still works.
    const refocus = (vnode) => {
      if (this.focus !== name) return;
      this.focus = null;
      (vnode.dom.disabled ? vnode.dom.parentNode.querySelector('button:not([disabled])') : vnode.dom)?.focus();
    };

    // A plain <button>: its oncreate/onupdate run on the element itself.
    return (
      <button
        type="button"
        className="Button Button--icon Button--flat hasIcon"
        aria-label={label}
        disabled={index + by < 0 || index + by >= this.pinned.length}
        oncreate={refocus}
        onupdate={refocus}
        onclick={() => {
          const pinned = [...this.pinned];
          pinned.splice(index, 1);
          pinned.splice(index + by, 0, id);
          this.pinned = pinned;
          this.focus = name;
        }}
      >
        <Icon name={icon} className="Button-icon" />
      </button>
    );
  }

  onsubmit(e) {
    e.preventDefault();
    this.run('PATCH', '', { pinnedBuilds: this.pinned }).then(
      () => this.hide(),
      () => {}
    );
  }
}
