/**
 * The forum's calendar, when Astrolabe is installed: Dossier stores a month as
 * the Gregorian date it starts on, and works out "which month" in whatever
 * calendar the forum shows. Without Astrolabe this is plain Gregorian, the same
 * as before.
 */

/**
 * Astrolabe's API, or null when it isn't enabled (checked first, so the registry never logs a miss).
 * The registry hands back the module's default export itself; `.default` covers a module object.
 */
const astrolabe = () => {
  if (!window.flarum?.extensions?.['ernestdefoe-astrolabe']) return null;
  const api = window.flarum.reg.get('ernestdefoe-astrolabe', 'common/api');
  return api?.default || api || null;
};

/** The calendar this page shows ("persian", "islamic"…), or null for Gregorian. */
export const calendar = () => {
  const name = astrolabe()?.active().main.calendar;
  return name && name !== 'gregory' ? name : null;
};

const ymd = (value) => {
  const [y, m, d] = String(value).split('-').map(Number);
  return new Date(Date.UTC(y, (m || 1) - 1, d || 1));
};

const iso = (date) => date.toISOString().slice(0, 10);

/** Year and month of a date in a calendar, read with Latin digits so they parse. */
const yearMonth = (date, name) => {
  const parts = new Intl.DateTimeFormat(`en-u-ca-${name}-nu-latn`, { year: 'numeric', month: 'numeric', timeZone: 'UTC' }).formatToParts(date);
  const part = (type) => Number(parts.find((p) => p.type === type)?.value);

  return [part('year'), part('month')];
};

/** A date as months since year 0 of the calendar in use, so durations are a subtraction. */
export const monthIndex = (value, name = calendar()) => {
  const date = typeof value === 'string' ? ymd(value) : value;
  const [year, month] = name ? yearMonth(date, name) : [date.getUTCFullYear(), date.getUTCMonth() + 1];

  return year * 12 + (month - 1);
};

/** The first day of the month (in the calendar in use) that a date falls in, as "YYYY-MM-DD". */
export const monthStart = (value, name = calendar()) => {
  const date = ymd(value);
  if (!name) return iso(new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1)));

  // Step back a day at a time while the month stays the same: at most 30 steps.
  const month = monthIndex(date, name);
  for (let i = 0; i < 31; i++) {
    const before = new Date(date.getTime() - 86400000);
    if (monthIndex(before, name) !== month) break;
    date.setTime(before.getTime());
  }

  return iso(date);
};
