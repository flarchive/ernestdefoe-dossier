import selectBox from '../common/selectBox';
import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { t, api } from './util';

/**
 * The skill catalogue members pick from: categories (renamed, sorted,
 * removed) and their skills, and below it the skills members typed
 * themselves, to add to the catalogue or remove.
 */
export default class CatalogueEditor extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.data = null;
    this.newCategory = '';
    this.newSkill = {};
    this.pendingCategory = {};
    this.load();
  }

  load() {
    return app.request({ method: 'GET', url: api('/dossier-catalogue'), params: { manage: 1 } }).then((response) => {
      this.data = response.data;
      m.redraw();
    });
  }

  call(method, path, body) {
    return app.request({ method, url: api(`/dossier-catalogue${path}`), body }).then(() => this.load());
  }

  view() {
    if (!this.data) return <LoadingIndicator />;

    const { categories, skills } = this.data;
    const approved = skills.filter((skill) => skill.approved);
    const pending = skills.filter((skill) => !skill.approved);
    const known = new Set(categories.map((c) => c.id));
    const loose = approved.filter((skill) => !known.has(skill.categoryId));

    return (
      <div className="DossierAdmin-catalogue">
        <h3>{t('catalogue.heading')}</h3>
        <p className="helpText">{t('catalogue.help')}</p>

        {categories.map((category, index) =>
          this.category(
            category,
            index,
            approved.filter((s) => s.categoryId === category.id)
          )
        )}
        {loose.length ? this.category(null, -1, loose) : null}

        <form
          className="DossierAdmin-add"
          onsubmit={(e) => {
            e.preventDefault();
            if (!this.newCategory.trim()) return;
            this.call('POST', '/categories', { name: this.newCategory }).then(() => (this.newCategory = ''));
          }}
        >
          <input
            className="FormControl"
            type="text"
            maxlength="60"
            placeholder={t('catalogue.new_category')}
            aria-label={t('catalogue.new_category')}
            value={this.newCategory}
            oninput={(e) => (this.newCategory = e.target.value)}
          />
          <Button type="submit" className="Button" icon="fas fa-plus">
            {t('catalogue.add_category')}
          </Button>
        </form>

        <h3 className="DossierAdmin-pendingHeading">{t('pending.heading')}</h3>
        <p className="helpText">{t('pending.help')}</p>
        {pending.length ? (
          <table className="DossierAdmin-pending">
            <thead>
              <tr>
                <th>{t('pending.skill')}</th>
                <th>{t('pending.uses')}</th>
                <th>{t('pending.category')}</th>
                <th>
                  <span className="visually-hidden">{t('pending.actions')}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {pending.map((skill) => (
                <tr key={skill.id}>
                  <td>{skill.name}</td>
                  <td>{skill.uses}</td>
                  <td>
                    {selectBox(
                      <select
                        className="Select-input FormControl"
                        aria-label={t('pending.category')}
                        value={this.pendingCategory[skill.id] ?? skill.categoryId ?? ''}
                        onchange={(e) => (this.pendingCategory[skill.id] = e.target.value)}
                      >
                        <option value="">{t('catalogue.no_category')}</option>
                        {categories.map((c) => (
                          <option value={c.id}>{c.name}</option>
                        ))}
                      </select>
                    )}
                  </td>
                  <td className="DossierAdmin-pendingActions">
                    <Button
                      className="Button Button--primary"
                      icon="fas fa-check"
                      onclick={() =>
                        this.call('PATCH', `/skills/${skill.id}`, {
                          approved: true,
                          categoryId: (this.pendingCategory[skill.id] ?? skill.categoryId) || null,
                        })
                      }
                    >
                      {t('pending.promote')}
                    </Button>
                    <Button
                      className="Button Button--icon"
                      icon="fas fa-trash-alt"
                      aria-label={t('pending.delete')}
                      onclick={() => this.remove(skill)}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="DossierAdmin-none">{t('pending.none')}</p>
        )}
      </div>
    );
  }

  category(category, index, skills) {
    const id = category ? category.id : 'none';
    const count = this.data.categories.length;
    const move = (by) => {
      const ids = this.data.categories.map((c) => c.id);
      ids.splice(index, 1);
      ids.splice(index + by, 0, category.id);
      this.call('POST', '/categories/order', { ids });
    };

    return (
      <div className="DossierAdmin-category" key={id}>
        <div className="DossierAdmin-categoryHead">
          {category ? (
            <input
              className="FormControl"
              type="text"
              maxlength="60"
              aria-label={t('catalogue.category_name')}
              value={category.name}
              onchange={(e) => this.call('PATCH', `/categories/${category.id}`, { name: e.target.value })}
            />
          ) : (
            <strong>{t('catalogue.no_category')}</strong>
          )}
          {category ? (
            <div className="DossierAdmin-buttons">
              <Button
                className="Button Button--icon"
                icon="fas fa-arrow-up"
                aria-label={t('catalogue.move_up')}
                disabled={index === 0}
                onclick={() => move(-1)}
              />
              <Button
                className="Button Button--icon"
                icon="fas fa-arrow-down"
                aria-label={t('catalogue.move_down')}
                disabled={index === count - 1}
                onclick={() => move(1)}
              />
              <Button
                className="Button Button--icon"
                icon="fas fa-trash-alt"
                aria-label={t('catalogue.delete_category')}
                onclick={() => {
                  if (confirm(t('catalogue.delete_category_confirm', { name: category.name }))) this.call('DELETE', `/categories/${category.id}`);
                }}
              />
            </div>
          ) : null}
        </div>
        <ul className="DossierAdmin-skills">
          {skills.map((skill) => (
            <li className="DossierAdmin-skill" key={skill.id}>
              <span>{skill.name}</span>
              {skill.uses ? <span className="DossierAdmin-uses">{t('catalogue.uses', { count: skill.uses })}</span> : null}
              <button
                type="button"
                className="DossierAdmin-remove"
                aria-label={t('catalogue.delete_skill', { name: skill.name })}
                onclick={() => this.remove(skill)}
              >
                <i className="fas fa-times" aria-hidden="true" />
              </button>
            </li>
          ))}
        </ul>
        {category ? (
          <form
            className="DossierAdmin-add"
            onsubmit={(e) => {
              e.preventDefault();
              const name = (this.newSkill[id] || '').trim();
              if (!name) return;
              this.call('POST', '/skills', { name, categoryId: category.id }).then(() => (this.newSkill[id] = ''));
            }}
          >
            <input
              className="FormControl"
              type="text"
              maxlength="60"
              placeholder={t('catalogue.new_skill')}
              aria-label={t('catalogue.new_skill_in', { category: category.name })}
              value={this.newSkill[id] || ''}
              oninput={(e) => (this.newSkill[id] = e.target.value)}
            />
            <Button type="submit" className="Button" icon="fas fa-plus">
              {t('catalogue.add_skill')}
            </Button>
          </form>
        ) : null}
      </div>
    );
  }

  remove(skill) {
    const message = skill.uses
      ? t('catalogue.delete_skill_used', { name: skill.name, count: skill.uses })
      : t('catalogue.delete_skill_confirm', { name: skill.name });
    if (confirm(message)) this.call('DELETE', `/skills/${skill.id}`);
  }
}
