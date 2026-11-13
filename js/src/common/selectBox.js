import Icon from 'flarum/common/components/Icon';

/** Core's Select look (padding, caret) around a native <select>, which phones show as their own picker. */
export default function selectBox(select, className = '') {
  return (
    <span className={'Select ' + className}>
      {select}
      <Icon name="fas fa-sort" className="Select-caret" />
    </span>
  );
}
