import selectBox from '../../common/selectBox';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Tooltip from 'flarum/common/components/Tooltip';
import Icon from 'flarum/common/components/Icon';
import DossierModal from './DossierModal';
import { t } from '../util';
import catalogue, { forgetCatalogue } from './catalogue';

const NEW = 'new';

/** A skill's name as the server compares it (Skill::slugFor): trimmed, spaces collapsed, lower case. */
const slug = (name) => String(name).trim().replace(/\s+/g, ' ').toLowerCase();

/** Pick a skill from the catalogue (or type one of your own) and a level. */
export default class SkillModal extends DossierModal {
  oninit(vnode) {
    super.oninit(vnode);
    const entry = this.attrs.entry;
    this.values = { skill: entry ? String(entry.skillId) : '', name: '', categoryId: '', level: entry ? entry.level : 50 };
    this.data = null;
    catalogue().then((data) => {
      this.data = data;
      m.redraw();
    });
  }

  title() {
    return t(`modals.skills.${this.attrs.entry ? 'edit' : 'add'}`);
  }

  fields() {
    if (!this.data) return <LoadingIndicator />;

    const v = this.values;
    const entry = this.attrs.entry;
    const taken = new Set((this.attrs.page.dossier.skills || []).filter((s) => !entry || s.id !== entry.id).map((s) => s.skillId));
    const skills = this.data.skills.filter((skill) => !taken.has(skill.id));
    // The skill being edited may be someone's own skill that is not in the list.
    if (entry && !skills.some((s) => s.id === entry.skillId)) skills.push({ id: entry.skillId, name: entry.name, categoryId: entry.categoryId });

    const earned = this.earned(skills);
    const verified = new Map((this.attrs.page.dossier.earnedSkills || []).map((skill) => [slug(skill.name), skill]));

    const groups = this.data.categories.map((category) => ({ label: category.name, skills: skills.filter((s) => s.categoryId === category.id) }));
    const known = new Set(this.data.categories.map((c) => c.id));
    groups.push({ label: t('skills.other'), skills: skills.filter((s) => !known.has(s.categoryId)) });

    return [
      !entry && earned.length ? this.earnedPicker(earned) : null,
      this.field(
        'skillId',
        t('modals.skills.skill'),
        selectBox(
          <select className="Select-input FormControl" value={v.skill} onchange={(e) => (v.skill = e.target.value)}>
            <option value="" disabled>
              {t('modals.skills.pick')}
            </option>
            {groups
              .filter((group) => group.skills.length)
              .map((group) => (
                <optgroup label={group.label}>
                  {group.skills.map((skill) => (
                    <option value={String(skill.id)}>
                      {verified.has(slug(skill.name)) ? t('modals.skills.verified_option', { name: skill.name }) : skill.name}
                    </option>
                  ))}
                </optgroup>
              ))}
            {this.data.customSkills ? (
              <option value={NEW}>{t('modals.skills.new_option')}</option>
            ) : v.skill === NEW && this.isEarned(v.name) ? (
              // Typed skills are off, but a skill a course earned can still be added.
              <option value={NEW}>{t('modals.skills.verified_option', { name: v.name })}</option>
            ) : null}
          </select>
        )
      ),
      v.skill === NEW && this.data.customSkills ? (
        <div className="DossierModal-row">
          {this.field('name', t('modals.skills.name'), this.text('name', { maxlength: 60 }), t('modals.skills.name_help'))}
          {this.field('categoryId', t('modals.skills.category'), this.select('categoryId', this.categoryOptions()))}
        </div>
      ) : null,
      this.field(
        'level',
        t('modals.skills.level'),
        <div className="DossierModal-level">
          <input
            type="range"
            min="0"
            max="100"
            step="5"
            value={v.level}
            oninput={(e) => (v.level = Number(e.target.value))}
            aria-label={t('modals.skills.level')}
          />
          <input
            className="FormControl DossierModal-number"
            type="number"
            inputmode="numeric"
            min="0"
            max="100"
            value={v.level}
            aria-label={t('modals.skills.level_percent')}
            oninput={(e) => (v.level = e.target.value === '' ? '' : Number(e.target.value))}
          />
          <span aria-hidden="true">%</span>
        </div>
      ),
    ];
  }

  isEarned(name) {
    return (this.attrs.page.dossier.earnedSkills || []).some((skill) => slug(skill.name) === slug(name));
  }

  /**
   * The skills the member's courses earned that aren't on the dossier yet,
   * each matched to its catalogue skill when there is one (by name, any case).
   */
  earned(available) {
    const onDossier = new Set((this.attrs.page.dossier.skills || []).map((s) => slug(s.name)));
    const byName = new Map(available.map((skill) => [slug(skill.name), skill]));

    return (this.attrs.page.dossier.earnedSkills || [])
      .filter((skill) => !onDossier.has(slug(skill.name)))
      .map((skill) => ({ ...skill, catalogue: byName.get(slug(skill.name)) || null }));
  }

  /** One button per earned skill, badged "Verified"; picking one fills in the skill. */
  earnedPicker(earned) {
    const v = this.values;

    return (
      <fieldset className="Form-group DossierEarned">
        <legend>{t('modals.skills.earned')}</legend>
        <div className="DossierEarned-list">
          {earned.map((skill) => {
            const chosen = skill.catalogue ? v.skill === String(skill.catalogue.id) : v.skill === NEW && v.name === skill.name;
            const label = t('skills.verified_by', { courses: skill.courses.join(', ') });

            // The tooltip sits on the badge: on the button it would replace the button's name for screen readers.
            return (
              <button
                type="button"
                className={'Button DossierEarned-skill' + (chosen ? ' DossierEarned-skill--chosen' : '')}
                aria-pressed={chosen ? 'true' : 'false'}
                onclick={() => {
                  if (skill.catalogue) {
                    v.skill = String(skill.catalogue.id);
                  } else {
                    v.skill = NEW;
                    v.name = skill.name;
                  }
                }}
              >
                <span className="DossierEarned-name">{skill.name}</span>
                <Tooltip text={label}>
                  <span className="DossierVerified" aria-label={label}>
                    <Icon name="fas fa-check-circle" /> {t('skills.verified')}
                  </span>
                </Tooltip>
              </button>
            );
          })}
        </div>
      </fieldset>
    );
  }

  categoryOptions() {
    const options = { '': t('skills.other') };
    this.data.categories.forEach((category) => (options[category.id] = category.name));
    return options;
  }

  onsubmit(e) {
    e.preventDefault();
    const v = this.values;
    const entry = this.attrs.entry;
    const body = v.skill === NEW ? { name: v.name, categoryId: v.categoryId || null, level: v.level } : { skillId: v.skill || null, level: v.level };

    if (!entry) body.section = 'skills';

    this.run(entry ? 'PATCH' : 'POST', entry ? `/entries/${entry.id}` : '/entries', body).then(
      () => {
        if (v.skill === NEW) forgetCatalogue();
        this.hide();
      },
      () => {}
    );
  }
}
