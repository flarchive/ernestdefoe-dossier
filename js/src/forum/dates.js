import { t } from './util';

/** CEFR levels and the plain words for them (keys under languages.levels). */
export const LEVELS = { A1: 'a1', A2: 'a2', B1: 'b1', B2: 'b2', C1: 'c1', C2: 'c2', native: 'native' };

/** "2022-05" → months since year 0, so durations are a subtraction. */
export const monthIndex = (value) => {
  const [year, month] = String(value).split('-').map(Number);
  return year * 12 + (month - 1);
};

export const currentMonth = () => {
  const now = new Date();
  return now.getFullYear() * 12 + now.getMonth();
};

/** "May 2022", in the reader's language. */
export const monthLabel = (value) => dayjs(`${value}-01`).format('MMM YYYY');

/** "2 years 3 months". */
export const durationLabel = (months) => {
  const years = Math.floor(months / 12);
  const rest = months % 12;
  const parts = [];
  if (years) parts.push(t('timeline.years', { count: years }));
  if (rest || !years) parts.push(t('timeline.months', { count: Math.max(rest, 1) }));
  return parts.join(' ');
};
