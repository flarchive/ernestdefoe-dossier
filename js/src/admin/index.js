import app from 'flarum/admin/app';
import { Admin } from 'flarum/common/extenders';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import CatalogueEditor from './CatalogueEditor';
import OpenToEditor from './OpenToEditor';
import { t } from './util';

/** Settings, the "Open to" chips, the skill catalogue, then permissions. */
class DossierPage extends ExtensionPage {
  sections(vnode) {
    const items = super.sections(vnode);
    items.setPriority('content', 40);
    items.add(
      'openTo',
      <div className="ExtensionPage-settings DossierAdmin-section">
        <div className="container">
          <OpenToEditor />
        </div>
      </div>,
      30
    );
    items.add(
      'catalogue',
      <div className="ExtensionPage-settings DossierAdmin-section">
        <div className="container">
          <CatalogueEditor />
        </div>
      </div>,
      20
    );
    items.setPriority('permissions', 10);
    return items;
  }
}

app.initializers.add('ernestdefoe-dossier', () => {});

// Callbacks, not values: this module runs before the app has booted.
export const extend = [
  new Admin()
    .page(DossierPage)
    .setting(() => ({
      setting: 'ernestdefoe-dossier.default_tab',
      type: 'boolean',
      label: t('settings.default_tab_label'),
      help: t('settings.default_tab_help'),
    }))
    .setting(() => ({
      setting: 'ernestdefoe-dossier.custom_skills',
      type: 'boolean',
      label: t('settings.custom_skills_label'),
      help: t('settings.custom_skills_help'),
    }))
    .permission(
      () => ({ icon: 'fas fa-id-card', label: t('permissions.view'), permission: 'ernestdefoe-dossier.view', allowGuest: true }),
      'view',
      90
    )
    .permission(() => ({ icon: 'fas fa-id-card', label: t('permissions.have'), permission: 'ernestdefoe-dossier.have' }), 'start', 90)
    .permission(() => ({ icon: 'fas fa-user-edit', label: t('permissions.edit_any'), permission: 'ernestdefoe-dossier.editAny' }), 'moderate', 90),
];
