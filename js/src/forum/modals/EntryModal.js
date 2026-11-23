import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import DossierModal from './DossierModal';
import MonthField from './MonthField';
import { t, apiUrl } from '../util';

const FIELDS = {
  timeline: { kind: 'work', title: '', organization: '', location: '', start: '', end: '', ongoing: false, description: '' },
  languages: { name: '', level: 'B2', percent: '' },
  certificates: { title: '', issuer: '', issued: '', url: '' },
  links: { kind: 'website', url: '', label: '', visibility: 'everyone' },
};

/** Add or change a job or degree, a language, a certificate or a link. */
export default class EntryModal extends DossierModal {
  oninit(vnode) {
    super.oninit(vnode);
    this.section = this.attrs.section;
    const entry = this.attrs.entry;
    this.values = { ...FIELDS[this.section] };

    if (entry) {
      Object.keys(this.values).forEach((key) => {
        if (entry[key] !== undefined && entry[key] !== null) this.values[key] = entry[key];
      });
      if (this.section === 'timeline') this.values.ongoing = !entry.end;
    }

    this.file = null;
    this.preview = entry ? entry.thumb : null;
    this.removeImage = false;
  }

  title() {
    return t(`modals.${this.section}.${this.attrs.entry ? 'edit' : 'add'}`);
  }

  fields() {
    return this[`fields_${this.section}`]();
  }

  fields_timeline() {
    const v = this.values;

    return [
      this.field(
        'kind',
        t('modals.timeline.kind'),
        this.select('kind', { work: t('timeline.kinds.work'), education: t('timeline.kinds.education') })
      ),
      this.field(
        'title',
        v.kind === 'education' ? t('modals.timeline.degree') : t('modals.timeline.title_label'),
        this.text('title', {
          maxlength: 120,
          placeholder: v.kind === 'education' ? t('modals.timeline.degree_placeholder') : t('modals.timeline.title_placeholder'),
        })
      ),
      this.field(
        'organization',
        v.kind === 'education' ? t('modals.timeline.school') : t('modals.timeline.organization'),
        this.text('organization', { maxlength: 120 })
      ),
      this.field(
        'location',
        t('modals.timeline.location'),
        this.text('location', { maxlength: 120, placeholder: t('modals.timeline.location_placeholder') })
      ),
      <div className="DossierModal-row">
        {this.field('start', t('modals.timeline.start'), <MonthField value={v.start} onchange={(value) => (v.start = value)} />)}
        {this.field(
          'end',
          t('modals.timeline.end'),
          <MonthField value={v.ongoing ? '' : v.end} disabled={v.ongoing} onchange={(value) => (v.end = value)} />
        )}
      </div>,
      <div className="Form-group">
        <Switch state={v.ongoing} onchange={(value) => (v.ongoing = value)}>
          {v.kind === 'education' ? t('modals.timeline.ongoing_education') : t('modals.timeline.ongoing')}
        </Switch>
      </div>,
      this.field('description', t('modals.timeline.description'), this.textarea('description', { maxlength: 1500 })),
    ];
  }

  fields_languages() {
    const levels = { native: t('languages.native') };
    ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'].forEach((level) => (levels[level] = `${level} · ${t(`languages.levels.${level.toLowerCase()}`)}`));

    return [
      this.field('name', t('modals.languages.name'), this.text('name', { maxlength: 60, placeholder: t('modals.languages.name_placeholder') })),
      this.field('level', t('modals.languages.level'), this.select('level', levels)),
      this.field(
        'percent',
        t('modals.languages.percent'),
        <input
          className="FormControl DossierModal-number"
          type="number"
          inputmode="numeric"
          min="0"
          max="100"
          value={this.values.percent ?? ''}
          oninput={(e) => (this.values.percent = e.target.value)}
        />,
        t('modals.languages.percent_help')
      ),
    ];
  }

  fields_certificates() {
    return [
      this.field(
        'title',
        t('modals.certificates.title_label'),
        this.text('title', { maxlength: 150, placeholder: t('modals.certificates.title_placeholder') })
      ),
      this.field('issuer', t('modals.certificates.issuer'), this.text('issuer', { maxlength: 120 })),
      this.field(
        'issued',
        t('modals.certificates.issued'),
        <MonthField value={this.values.issued} onchange={(value) => (this.values.issued = value)} />
      ),
      this.field(
        'url',
        t('modals.certificates.url'),
        this.text('url', { type: 'url', inputmode: 'url', placeholder: 'https://' }),
        t('modals.certificates.url_help')
      ),
      this.field(
        'image',
        t('modals.certificates.image'),
        <div className="DossierModal-image">
          {this.preview && !this.removeImage ? <img src={this.preview} alt="" /> : null}
          <input
            className="FormControl"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onchange={(e) => {
              this.file = e.target.files[0] || null;
              this.removeImage = false;
              this.preview = this.file ? URL.createObjectURL(this.file) : this.attrs.entry?.thumb;
            }}
          />
          {this.preview && !this.removeImage ? (
            <Button
              className="Button Button--link"
              icon="fas fa-times"
              onclick={() => {
                this.file = null;
                this.removeImage = true;
              }}
            >
              {t('modals.certificates.remove_image')}
            </Button>
          ) : null}
        </div>,
        t('modals.certificates.image_help')
      ),
    ];
  }

  fields_links() {
    const kinds = {};
    ['website', 'linkedin', 'github', 'gitlab', 'x', 'mastodon', 'custom'].forEach((kind) => (kinds[kind] = t(`links.kinds.${kind}`)));
    const labelled = this.values.kind === 'website' || this.values.kind === 'custom';

    return [
      this.field('kind', t('modals.links.kind'), this.select('kind', kinds)),
      this.field('url', t('modals.links.url'), this.text('url', { type: 'url', inputmode: 'url', placeholder: 'https://' })),
      labelled ? this.field('label', t('modals.links.label'), this.text('label', { maxlength: 60 }), t('modals.links.label_help')) : null,
      this.field(
        'visibility',
        t('modals.links.visibility'),
        this.select('visibility', { everyone: t('visibility.everyone'), members: t('visibility.members'), private: t('visibility.private') }),
        t('modals.links.visibility_help')
      ),
    ];
  }

  onsubmit(e) {
    e.preventDefault();
    const entry = this.attrs.entry;
    const body = { ...this.values };

    if (this.section === 'timeline' && body.ongoing) body.end = null;
    if (this.section === 'links' && body.kind !== 'website' && body.kind !== 'custom') body.label = null;

    this.run(entry ? 'PATCH' : 'POST', entry ? `/entries/${entry.id}` : '/entries', entry ? body : { section: this.section, ...body })
      .then((response) => {
        if (this.section !== 'certificates' || !(this.file || this.removeImage)) return;
        // A new certificate is the one with the highest id.
        const id = entry ? entry.id : Math.max(...response.data.certificates.map((c) => c.id));
        return this.image(id);
      })
      .then(
        () => this.hide(),
        () => {}
      );
  }

  image(id) {
    const url = `/entries/${id}/image`;

    if (this.removeImage) return this.run('DELETE', url);

    const data = new FormData();
    data.append('image', this.file);
    this.loading = true;

    return app
      .request({
        method: 'POST',
        url: apiUrl(`/dossiers/${this.attrs.page.user.id()}${url}`),
        serialize: (raw) => raw,
        body: data,
        errorHandler: this.onerror.bind(this),
      })
      .then((response) => this.attrs.page.update(response))
      .catch((error) => {
        this.loading = false;
        m.redraw();
        throw error;
      });
  }
}
