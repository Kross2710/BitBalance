// The app's "day" is defined in the server's timezone (Asia/Bangkok, +07:00),
// which is where intake rows are stamped. Compute "today" in that timezone
// everywhere so the Dashboard and Intake agree on which day is "today".
//
// Do NOT use `new Date().toISOString().slice(0, 10)` for this — that's the UTC
// date, which lags Bangkok by a day for ~7 hours every night and makes the
// Dashboard query yesterday while Intake (correctly) queries today.
const APP_TZ = 'Asia/Bangkok';

// Today's calendar date in the app timezone, as a YYYY-MM-DD string.
// 'en-CA' formats dates as YYYY-MM-DD.
export function appToday() {
  return new Date().toLocaleDateString('en-CA', { timeZone: APP_TZ });
}
