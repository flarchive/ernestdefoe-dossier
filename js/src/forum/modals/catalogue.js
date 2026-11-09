import app from 'flarum/forum/app';
import { apiUrl } from '../util';

let request = null;

/** The skill catalogue and open-to chips, fetched once per visit (again after a failure). */
export default function catalogue() {
  if (!request) {
    request = app
      .request({ method: 'GET', url: apiUrl('/dossier-catalogue') })
      .then((response) => response.data)
      .catch((error) => {
        request = null;
        throw error;
      });
  }

  return request;
}

/** After a member adds a skill of their own, the next window lists it. */
export function forgetCatalogue() {
  request = null;
}
