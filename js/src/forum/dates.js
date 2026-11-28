import { t } from './util';
import { monthIndex } from './calendar';

/** CEFR levels and the plain words for them (keys under languages.levels). */
export const LEVELS = { A1: 'a1', A2: 'a2', B1: 'b1', B2: 'b2', C1: 'c1', C2: 'c2', native: 'native' };

export { monthIndex };

/** This month, as months since year 0 of the calendar in use. */
export const currentMonth = () => {
  const now = new Date();
  return monthIndex(new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())));
};

/**
 * "May 2022", in the reader's language. Saved as the day the month starts
 * ("2022-05-01"; "2022-05" before 1.6); with Astrolabe, dayjs names it in the
 * forum's calendar.
 */
export const monthLabel = (value) => dayjs(String(value).length === 7 ? `${value}-01` : value).format('MMM YYYY');

/** "2 years 3 months". */
export const durationLabel = (months) => {
  const years = Math.floor(months / 12);
  const rest = months % 12;
  const parts = [];
  if (years) parts.push(t('timeline.years', { count: years }));
  if (rest || !years) parts.push(t('timeline.months', { count: Math.max(rest, 1) }));
  return parts.join(' ');
};
