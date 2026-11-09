import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

/** A forum string of Dossier's. */
/** Plain strings (none of these carry markup), so they work in attributes and joins too. */
export const t = (key, params) => extractText(app.translator.trans(`ernestdefoe-dossier.forum.${key}`, params));

export const apiUrl = (path) => `${app.forum.attribute('apiUrl')}${path}`;

/** Whether the viewer sees the Dossier tab on this profile. Someone else's empty dossier has none. */
export function showsTab(user) {
  if (!user) return false;
  if (app.session.user === user && app.forum.attribute('canHaveDossier')) return true;

  return !!user.attribute('hasDossier');
}
