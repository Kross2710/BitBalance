// Locale registry — mirrors include/i18n/locales.php. `en` stays first: it is
// the fallback locale, and any key missing from another table falls back to it.
// `native` is the language's own name (what the switcher shows); `htmlLang`
// feeds the document <html lang> attribute.
//
// French exists in the PHP app but has no Vue catalog yet — adding it later is
// just one entry here plus a fr.js next to en.js / vi.js.
export const locales = {
  en: { native: 'English', english: 'English', htmlLang: 'en' },
  vi: { native: 'Tiếng Việt', english: 'Vietnamese', htmlLang: 'vi' },
};

export const FALLBACK = 'en';
