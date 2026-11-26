import Extend from 'flarum/common/extenders';
import UserPageResolver from 'flarum/forum/resolvers/UserPageResolver';

export default [
  // The page (and its editor) is a chunk of its own, fetched when a dossier is opened.
  new Extend.Routes().add('user.dossier', '/u/:username/dossier', () => import('./components/DossierPage'), UserPageResolver),
];
