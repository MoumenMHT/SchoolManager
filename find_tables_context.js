const fs = require('fs');
const html = fs.readFileSync('docs/SchoolHub_Internship_Report.html', 'utf8');
const lines = html.split('\n');

let tableCount = 0;

for (let i = 0; i < lines.length; i++) {
  if (lines[i].includes('<table class="rt"')) {
    tableCount++;
    console.log(`\n--- TABLE ${tableCount} ---`);
    for(let j = i - 3; j <= i + 1; j++) {
       if (lines[j]) console.log(lines[j].trim());
    }
  }
}
