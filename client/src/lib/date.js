// Today's calendar date in the USER's local (browser) timezone, as YYYY-MM-DD.
// The server resolves the same browser zone from the X-Timezone header (see
// lib/api.js) and groups days to match, so Dashboard and Intake agree on "today".
//
// Do NOT use `new Date().toISOString().slice(0, 10)` for this — that's the UTC
// date, which can be a day off from the user's local date near midnight.
// 'en-CA' formats dates as YYYY-MM-DD.
export function appToday() {
  return new Date().toLocaleDateString('en-CA');
}
