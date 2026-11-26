import app from 'flarum/forum/app';
import UserPageResolver from 'flarum/forum/resolvers/UserPageResolver';
import PostsUserPage from 'flarum/forum/components/PostsUserPage';

const matches = (attributes, id, slug) => {
  const wanted = slug.toLowerCase();
  return id === slug || (attributes.slug || '').toLowerCase() === wanted || (attributes.username || '').toLowerCase() === wanted;
};

/**
 * Whether /u/:username should open on the dossier: only when this viewer would
 * find something there. `hasDossier` comes with a profile (GET /api/users/:id),
 * never with the users that discussion and post lists carry, so:
 *
 * 1. the page's own preloaded profile, on a fresh load (read, not consumed);
 * 2. a user in the store that came from a profile request before;
 * 3. otherwise the profile is fetched here, and handed to the page as its
 *    preloaded document, so UserPage does not fetch it a second time.
 */
async function opensOnDossier(slug) {
  const doc = app.data.apiDocument;

  if (doc && doc.data && !Array.isArray(doc.data) && doc.data.type === 'users' && matches(doc.data.attributes, doc.data.id, slug)) {
    return !!doc.data.attributes.hasDossier;
  }

  const known = app.store.all('users').find((user) => matches(user.data.attributes || {}, user.id(), slug));

  if (known && known.attribute('hasDossier') !== undefined) return !!known.attribute('hasDossier');

  try {
    const payload = await app.request({
      method: 'GET',
      url: `${app.forum.attribute('apiUrl')}/users/${encodeURIComponent(slug)}`,
      params: { bySlug: true },
      errorHandler: () => {},
    });

    app.data.apiDocument = payload;
    app.initialRoute = window.location.href;

    return !!payload.data.attributes.hasDossier;
  } catch (e) {
    // Let the profile page show its own "not found".
    return false;
  }
}

/** /u/:username: the dossier when there is something to see, else core's posts. */
export default class LandingResolver extends UserPageResolver {
  async onmatch(args) {
    if (await opensOnDossier(String(args.username || ''))) {
      return (await import('./components/DossierPage')).default;
    }

    return PostsUserPage;
  }
}
