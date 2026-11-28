import app from 'flarum/admin/app';
import extractText from 'flarum/common/utils/extractText';

/** Plain strings (none of these carry markup), so they work in attributes and joins too. */
export const t = (key, params) => extractText(app.translator.trans(`ernestdefoe-dossier.admin.${key}`, params));

export const api = (path) => `${app.forum.attribute('apiUrl')}${path}`;
