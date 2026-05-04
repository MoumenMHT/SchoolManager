import { createI18n } from 'vue-i18n';
import en from './en.json';
import fr from './fr.json';
import ar from './ar.json';

const savedLocale = localStorage.getItem('locale') || 'en';

const i18n = createI18n({
    legacy: false,
    locale: savedLocale,
    fallbackLocale: 'en',
    messages: {
        en,
        fr,
        ar
    }
});

export default i18n;
export const SUPPORTED_LOCALES = [
    { code: 'en', label: 'English', flag: '🇬🇧', dir: 'ltr' },
    { code: 'fr', label: 'Français', flag: '🇫🇷', dir: 'ltr' },
    { code: 'ar', label: 'العربية', flag: '🇸🇦', dir: 'rtl' }
];
