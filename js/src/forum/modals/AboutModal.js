import DossierModal from './DossierModal';
import { t } from '../util';

/** Headline, current role, bio, specializations and interests. */
export default class AboutModal extends DossierModal {
  oninit(vnode) {
    super.oninit(vnode);
    const about = this.attrs.page.dossier.about || {};
    this.values = {
      headline: about.headline || '',
      role: about.role || '',
      bio: about.bio || '',
      specializations: (about.specializations || []).join(', '),
      interests: (about.interests || []).join(', '),
    };
  }

  title() {
    return t('modals.about.title');
  }

  fields() {
    return [
      this.field(
        'headline',
        t('about.headline'),
        this.text('headline', { maxlength: 150, placeholder: t('modals.about.headline_placeholder') }),
        t('modals.about.headline_help')
      ),
      this.field('role', t('about.role'), this.text('role', { maxlength: 150, placeholder: t('modals.about.role_placeholder') })),
      this.field('bio', t('about.bio'), this.textarea('bio', { maxlength: 3000, rows: 6 }), t('modals.about.bio_help')),
      this.field(
        'specializations',
        t('about.specializations'),
        this.text('specializations', { placeholder: t('modals.about.specializations_placeholder') }),
        t('modals.about.list_help')
      ),
      this.field(
        'interests',
        t('about.interests'),
        this.text('interests', { placeholder: t('modals.about.interests_placeholder') }),
        t('modals.about.list_help')
      ),
    ];
  }

  onsubmit(e) {
    e.preventDefault();
    this.run('PATCH', '', this.values).then(
      () => this.hide(),
      () => {}
    );
  }
}
