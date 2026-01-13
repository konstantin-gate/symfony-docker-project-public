import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import Backend from 'i18next-http-backend';
import LanguageDetector from 'i18next-browser-languagedetector';

i18n
  .use(Backend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'cs', // Default to Czech for this project
    supportedLngs: ['cs', 'en', 'ru'],
    debug: process.env.NODE_ENV === 'development',
    
    interpolation: {
      escapeValue: false, // not needed for react as it escapes by default
    },

    backend: {
      // Path to translation files
      // We will serve them via Symfony public folder or Webpack CopyPlugin
      loadPath: '/build/locales/{{lng}}/translation.json',
    },
    
    detection: {
        order: ['querystring', 'localStorage', 'navigator'],
        lookupQuerystring: 'lang',
        lookupLocalStorage: 'i18nextLng',
        caches: ['localStorage'],
    }
  });

export default i18n;
