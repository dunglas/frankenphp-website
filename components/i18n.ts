import en from "../i18n/en.json";
import es from "../i18n/es.json";
import fr from "../i18n/fr.json";
import ja from "../i18n/ja.json";
import ptBr from "../i18n/pt-br.json";
import ru from "../i18n/ru.json";
import tr from "../i18n/tr.json";
import zh from "../i18n/zh.json";

export type Strings = typeof en;

export const LOCALES = {
  en: { label: "English", strings: en },
  fr: { label: "Français", strings: fr },
  es: { label: "Español", strings: es },
  zh: { label: "简体中文", strings: zh },
  ja: { label: "日本語", strings: ja },
  "pt-br": { label: "Português (Brasil)", strings: ptBr },
  ru: { label: "Русский", strings: ru },
  tr: { label: "Türkçe", strings: tr },
} as const;

export type Locale = keyof typeof LOCALES;
export const DEFAULT_LOCALE: Locale = "en";

const isLocale = (value: string | undefined): value is Locale =>
  value !== undefined && value in LOCALES;

export const stringsFor = (locale: string | undefined): Strings =>
  LOCALES[isLocale(locale) ? locale : DEFAULT_LOCALE].strings as Strings;

export const localizePath = (path: string, locale: string): string =>
  locale === DEFAULT_LOCALE || !path.startsWith("/")
    ? path
    : `/${locale}${path === "/" ? "" : path}`;

export const localeOfRoute = (route: string): Locale => {
  const first = route.split("/")[1];
  return isLocale(first) ? first : DEFAULT_LOCALE;
};

export const unlocalizedRoute = (route: string): string => {
  const locale = localeOfRoute(route);
  if (locale === DEFAULT_LOCALE) return route;
  return route.slice(locale.length + 1) || "/";
};

export const localeSwitch = (path: string, current: string) =>
  Object.entries(LOCALES).map(([code, { label }]) => ({
    code,
    label,
    dir: "ltr" as const,
    href: localizePath(path, code),
    current: code === current,
    untranslated: false,
  }));
