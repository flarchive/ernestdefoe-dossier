import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';
import { t, showsTab } from './util';
import LandingResolver from './landing';

app.initializers.add('ernestdefoe-dossier', () => {
  // "Open profiles on Dossier": the bare profile address decides between the
  // dossier and the posts, and the posts move to /u/:username/posts. Core
  // answers that address on the server too (/u/{username}[/{filter}]).
  app.beforeMount(() => {
    if (!app.forum.attribute('dossierDefault') || !app.routes['user.posts']) return;

    app.routes['user.posts'] = { ...app.routes['user.posts'], path: '/u/:username/posts' };
    app.routes.user = { path: '/u/:username', component: () => import('./components/DossierPage'), resolverClass: LandingResolver };
  });

  extend('flarum/forum/components/UserPage', 'navItems', function (items) {
    const user = this.user;
    if (!user) return;

    const landing = this.attrs.routeName === 'user';

    if (showsTab(user)) {
      items.add(
        'dossier',
        <LinkButton
          href={app.route('user.dossier', { username: user.slug() })}
          icon="fas fa-id-card"
          active={(landing && this.dossierPage) || undefined}
        >
          {t('nav')}
        </LinkButton>,
        110
      );
    }

    // The bare address opened the posts: that tab is the current one.
    if (landing && !this.dossierPage && items.has('posts')) {
      items.get('posts').attrs.active = true;
    }
  });

  // The headline under the member's name, on the dossier only.
  extend('flarum/forum/components/UserCard', 'infoItems', function (items) {
    if (this.attrs.dossierHeadline) {
      items.add('dossierHeadline', <span className="UserCard-dossierHeadline">{this.attrs.dossierHeadline}</span>, 200);
    }
  });
});
