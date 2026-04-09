const fs = require('fs');
const vueCode = fs.readFileSync('Web/src/views/Classes.vue', 'utf8');
const regex = /(?:t|\$t)\('common\.([^']+)'/g;
let match;
const keys = new Set();
while ((match = regex.exec(vueCode)) !== null) {
  keys.add(match[1]);
}
['en.json', 'fr.json', 'ar.json'].forEach(lang => {
  const json = JSON.parse(fs.readFileSync(`Web/src/i18n/${lang}`, 'utf8'));
  const commonKeys = json.common || {};
  const missing = [];
  for (const key of keys) {
    if (commonKeys[key] === undefined) {
      missing.push(key);
    }
  }
  console.log(`Missing common keys in ${lang}:`, missing);
});
