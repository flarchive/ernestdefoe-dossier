import selectBox from '../../common/selectBox';
import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import UserCard from 'flarum/forum/components/UserCard';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Tooltip from 'flarum/common/components/Tooltip';
import { t, apiUrl } from '../util';
import useStyles from '../styles';
import Timeline from './Timeline';
import { monthLabel, LEVELS } from '../dates';

const SECTIONS = ['about', 'timeline', 'skills', 'languages', 'projects', 'certificates', 'links', 'open_to'];
const VISIBILITY_ICONS = { everyone: 'fas fa-globe', members: 'fas fa-user-friends', private: 'fas fa-lock' };
const LISTS = ['timeline', 'skills', 'languages', 'certificates', 'links'];

/** Does the dossier hold anything at all (as this viewer sees it)? */
export const isEmpty = (d) => !d.about && !(d.openTo || []).length && LISTS.every((key) => !(d[key] || []).length);

/**
 * Profile → Dossier: the member's professional profile, and the place they
 * edit it. Edit mode adds, changes, deletes and orders entries per section,
 * through modals fetched when first opened.
 */
export default class DossierPage extends UserPage {
  oninit(vnode) {
    super.oninit(vnode);
    useStyles();
    this.dossierPage = true;
    this.dossier = null;
    this.failed = false;
    this.editing = false;
    this.loadUser(m.route.param('username'));
  }

  show(user) {
    super.show(user);
    if (this.loadedFor === user.id()) return;
    this.loadedFor = user.id();

    app
      .request({ method: 'GET', url: apiUrl(`/dossiers/${user.id()}`), errorHandler: () => {} })
      .then((response) => this.update(response))
      .catch(() => {
        this.failed = true;
        m.redraw();
      });
  }

  /** Every write answers with the whole dossier again: it replaces what is shown. */
  update(response) {
    this.dossier = response.data;

    if (this.dossier.canEdit && this.user) {
      this.user.pushAttributes({ hasDossier: !isEmpty(this.dossier) });
    }

    m.redraw();
    return response;
  }

  save(method, path, body, options = {}) {
    return app.request({ method, url: apiUrl(`/dossiers/${this.user.id()}${path}`), body, ...options }).then((response) => this.update(response));
  }

  hero() {
    const headline = this.dossier?.about?.headline;

    return (
      <UserCard
        user={this.user}
        className="Hero UserHero"
        editable={this.user.canEdit() || this.user.canEditAvatar()}
        controlsButtonClassName="Button"
        dossierHeadline={headline}
      />
    );
  }

  content() {
    const d = this.dossier;

    if (this.failed) {
      return <p className="DossierPage-message">{t('page.unavailable')}</p>;
    }

    if (!d) return <LoadingIndicator />;

    const own = app.session.user === this.user;
    const empty = isEmpty(d);

    return (
      <div className={'DossierPage' + (this.editing ? ' DossierPage--editing' : '')}>
        {d.canEdit && !(empty && !this.editing) ? (
          <div className="DossierPage-toolbar">
            {this.editing ? <p className="DossierPage-editHint">{t('page.edit_hint')}</p> : null}
            <Button
              className={'Button ' + (this.editing ? 'Button--primary' : '')}
              icon={this.editing ? 'fas fa-check' : 'fas fa-pen'}
              onclick={() => (this.editing = !this.editing)}
            >
              {this.editing ? t('page.done') : t('page.edit')}
            </Button>
          </div>
        ) : null}

        {empty && !this.editing ? (
          d.canEdit ? (
            <div className="DossierEmpty DossierEmpty--cta">
              <Icon name="fas fa-id-card" className="DossierEmpty-icon" />
              <h2 className="DossierEmpty-title">{own ? t('page.cta_title') : t('page.cta_title_other')}</h2>
              <p className="DossierEmpty-text">{t('page.cta_text')}</p>
              <Button className="Button Button--primary" icon="fas fa-pen" onclick={() => (this.editing = true)}>
                {own ? t('page.cta_button') : t('page.edit')}
              </Button>
            </div>
          ) : (
            <p className="DossierPage-message">{t('page.empty')}</p>
          )
        ) : (
          SECTIONS.map((section) => this.section(section)).filter(Boolean)
        )}
      </div>
    );
  }

  section(key) {
    const d = this.dossier;
    const data = key === 'open_to' ? d.openTo : d[key];

    // null: hidden from this viewer, or (projects) Projects is not installed.
    if (data === null || data === undefined) {
      if (!(this.editing && key === 'about')) return null;
    }

    const filled = Array.isArray(data) ? data.length > 0 : !!data;
    if (!filled && !this.editing) return null;

    return (
      <section className={`DossierSection DossierSection--${key}`} key={key}>
        <header className="DossierSection-header">
          <h3 className="DossierSection-title">{t(`sections.${key}`)}</h3>
          {this.editing ? this.sectionControls(key) : null}
        </header>
        {filled ? (
          this[`render_${key}`](data)
        ) : (
          <p className="DossierSection-none">{t(key === 'projects' ? 'projects.none' : 'page.section_empty')}</p>
        )}
      </section>
    );
  }

  sectionControls(key) {
    const level = (this.dossier.visibility || {})[key] || 'everyone';
    const add = {
      about: () => this.open('AboutModal'),
      open_to: () => this.open('OpenToModal'),
      skills: () => this.open('SkillModal'),
      projects: null,
    }[key];
    const addEntry = LISTS.includes(key) && key !== 'skills' ? () => this.open('EntryModal', { section: key }) : null;
    const action = add !== undefined ? add : addEntry;

    return (
      <div className="DossierSection-controls">
        <label className="DossierVisibility">
          <Icon name={VISIBILITY_ICONS[level]} />
          <span className="visually-hidden">{t('visibility.label')}</span>
          {selectBox(
            <select
              className="Select-input FormControl"
              value={level}
              aria-label={t('visibility.label_for', { section: t(`sections.${key}`) })}
              onchange={(e) => this.save('PATCH', '', { visibility: { [key]: e.target.value } })}
            >
              {['everyone', 'members', 'private'].map((value) => (
                <option value={value}>{t(`visibility.${value}`)}</option>
              ))}
            </select>,
            'DossierVisibility-select'
          )}
        </label>
        {action ? (
          <Button
            className="Button Button--primary DossierSection-add"
            icon={key === 'about' || key === 'open_to' ? 'fas fa-pen' : 'fas fa-plus'}
            onclick={action}
          >
            {key === 'about' || key === 'open_to' ? t('page.edit_section') : t('page.add')}
          </Button>
        ) : null}
      </div>
    );
  }

  /** The editor's modals are their own chunk, fetched the first time one opens. */
  open(name, attrs = {}) {
    app.modal.show(() => import('../modals').then((modals) => ({ default: modals[name] })), { page: this, ...attrs });
  }

  /** Edit, up, down and delete buttons on one entry, in edit mode. */
  entryControls(section, entry, list) {
    if (!this.editing) return null;
    const index = list.indexOf(entry);
    const move = (by) => {
      const ids = list.map((e) => e.id);
      ids.splice(index, 1);
      ids.splice(index + by, 0, entry.id);
      this.save('POST', '/order', { section, ids });
    };
    const canMove = section !== 'timeline';

    return (
      <div className="DossierEntry-controls">
        <Tooltip text={t('page.edit_entry')}>
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-pen"
            aria-label={t('page.edit_entry')}
            onclick={() => this.open(section === 'skills' ? 'SkillModal' : 'EntryModal', { section, entry })}
          />
        </Tooltip>
        {canMove ? (
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-arrow-up"
            aria-label={t('page.move_up')}
            disabled={index === 0}
            onclick={() => move(-1)}
          />
        ) : null}
        {canMove ? (
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-arrow-down"
            aria-label={t('page.move_down')}
            disabled={index === list.length - 1}
            onclick={() => move(1)}
          />
        ) : null}
        <Tooltip text={t('page.delete_entry')}>
          <Button
            className="Button Button--icon Button--flat DossierEntry-delete"
            icon="fas fa-trash-alt"
            aria-label={t('page.delete_entry')}
            onclick={() => {
              if (confirm(t('page.delete_confirm'))) this.save('DELETE', `/entries/${entry.id}`);
            }}
          />
        </Tooltip>
      </div>
    );
  }

  render_about(about) {
    return (
      <div className="DossierAbout">
        {this.editing && about.headline ? (
          <p className="DossierAbout-headline">
            <span className="DossierLabel">{t('about.headline')}</span> {about.headline}
          </p>
        ) : null}
        {about.role ? (
          <p className="DossierAbout-role">
            <Icon name="fas fa-briefcase" /> <span className="DossierLabel">{t('about.role')}</span> {about.role}
          </p>
        ) : null}
        {about.bio ? <p className="DossierAbout-bio">{about.bio}</p> : null}
        {this.chips(t('about.specializations'), about.specializations)}
        {this.chips(t('about.interests'), about.interests)}
      </div>
    );
  }

  chips(label, list) {
    if (!list || !list.length) return null;

    return (
      <div className="DossierChips">
        <span className="DossierLabel">{label}</span>
        <ul className="DossierChips-list">
          {list.map((chip) => (
            <li className="DossierChip">{chip}</li>
          ))}
        </ul>
      </div>
    );
  }

  render_timeline(entries) {
    return <Timeline entries={entries} controls={(entry) => this.entryControls('timeline', entry, entries)} />;
  }

  render_skills(skills) {
    const groups = new Map();
    const order = [...skills].sort((a, b) => (a.categoryPosition ?? 9999) - (b.categoryPosition ?? 9999));
    order.forEach((skill) => {
      const key = skill.category || '';
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(skill);
    });

    return (
      <div className="DossierSkills">
        {[...groups.entries()].map(([category, list]) => (
          <div className="DossierSkills-group">
            <h4 className="DossierSkills-category">{category || t('skills.other')}</h4>
            <ul className="DossierBars">
              {list.map((skill) => (
                <li className="DossierBar">
                  <div className="DossierBar-label">
                    <span className="DossierBar-name">{skill.name}</span>
                    <span className="DossierBar-value">{skill.level}%</span>
                  </div>
                  <div
                    className="DossierBar-track"
                    role="meter"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow={skill.level}
                    aria-label={skill.name}
                  >
                    <div className="DossierBar-fill" style={{ width: skill.level + '%' }} />
                  </div>
                  {this.entryControls('skills', skill, skills)}
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    );
  }

  render_languages(languages) {
    return (
      <ul className="DossierBars DossierLanguages">
        {languages.map((language) => (
          <li className="DossierBar">
            <div className="DossierBar-label">
              <span className="DossierBar-name">{language.name}</span>
              <span className="DossierLevel">{language.level === 'native' ? t('languages.native') : language.level}</span>
              {language.percent !== null && language.percent !== undefined ? <span className="DossierBar-value">{language.percent}%</span> : null}
            </div>
            {language.percent !== null && language.percent !== undefined ? (
              <div
                className="DossierBar-track"
                role="meter"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow={language.percent}
                aria-label={language.name}
              >
                <div className="DossierBar-fill" style={{ width: language.percent + '%' }} />
              </div>
            ) : null}
            {LEVELS[language.level] ? <span className="DossierLanguages-meaning">{t(`languages.levels.${LEVELS[language.level]}`)}</span> : null}
            {this.entryControls('languages', language, languages)}
          </li>
        ))}
      </ul>
    );
  }

  render_projects(projects) {
    const show = app.routes['projects.show'];
    const all = app.routes['user.projects'];

    return (
      <div className="DossierProjects">
        <ul className="DossierProjects-list">
          {projects.map((project) => (
            <li className="DossierProject">
              <Link className="DossierProject-link" href={show ? app.route('projects.show', { slug: project.slug }) : `/projects/p/${project.slug}`}>
                <span className="DossierProject-image">
                  {project.image ? <img src={project.image} alt="" loading="lazy" decoding="async" /> : <Icon name="fas fa-cube" />}
                </span>
                <span className="DossierProject-text">
                  <span className="DossierProject-title">{project.title}</span>
                  {project.excerpt ? <span className="DossierProject-excerpt">{project.excerpt}</span> : null}
                </span>
              </Link>
            </li>
          ))}
        </ul>
        {all ? (
          <Link className="DossierMore" href={app.route('user.projects', { username: this.user.slug() })}>
            {t('projects.all')} <Icon name="fas fa-arrow-right" />
          </Link>
        ) : null}
      </div>
    );
  }

  render_certificates(certificates) {
    return (
      <ul className="DossierCertificates">
        {certificates.map((certificate) => (
          <li className="DossierCertificate">
            {certificate.thumb ? (
              <a className="DossierCertificate-image" href={certificate.image} target="_blank" rel="noopener">
                <img src={certificate.thumb} alt={t('certificates.picture_of', { title: certificate.title })} loading="lazy" decoding="async" />
              </a>
            ) : (
              <span className="DossierCertificate-image DossierCertificate-image--none">
                <Icon name="fas fa-award" />
              </span>
            )}
            <div className="DossierCertificate-text">
              <span className="DossierCertificate-title">{certificate.title}</span>
              <span className="DossierCertificate-meta">
                {[certificate.issuer, certificate.issued ? monthLabel(certificate.issued) : null].filter(Boolean).join(' · ')}
              </span>
              {certificate.url ? (
                <a className="DossierCertificate-verify" href={certificate.url} target="_blank" rel="nofollow noopener noreferrer">
                  <Icon name="fas fa-external-link-alt" /> {t('certificates.verify')}
                </a>
              ) : null}
            </div>
            {this.entryControls('certificates', certificate, certificates)}
          </li>
        ))}
      </ul>
    );
  }

  render_links(links) {
    return (
      <ul className="DossierLinks">
        {links.map((link) => (
          <li className="DossierLinks-item">
            <a className="DossierLink" href={link.url} target="_blank" rel="me nofollow noopener noreferrer">
              <Icon name={link.icon} />
              <span className="DossierLink-label">
                {link.label || (link.kind === 'website' || link.kind === 'custom' ? link.site : t(`links.kinds.${link.kind}`))}
              </span>
            </a>
            {this.editing && link.visibility ? (
              <Tooltip text={t(`visibility.${link.visibility}`)}>
                <span className="DossierLinks-visibility" aria-label={t(`visibility.${link.visibility}`)}>
                  <Icon name={VISIBILITY_ICONS[link.visibility]} />
                </span>
              </Tooltip>
            ) : null}
            {this.entryControls('links', link, links)}
          </li>
        ))}
      </ul>
    );
  }

  render_open_to(options) {
    return (
      <ul className="DossierChips-list DossierOpenTo">
        {options.map((option) => (
          <li className="DossierChip DossierChip--open">
            <Icon name="fas fa-check" /> {option.label}
          </li>
        ))}
      </ul>
    );
  }
}
