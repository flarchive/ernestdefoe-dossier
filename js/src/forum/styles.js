import css from '../../generated/pageCss';

/** The dossier's styles, added once, the first time the page or one of its windows is shown. */
export default function useStyles() {
  if (document.getElementById('dossier-styles')) return;
  const style = document.createElement('style');
  style.id = 'dossier-styles';
  style.textContent = css;
  document.head.appendChild(style);
}
