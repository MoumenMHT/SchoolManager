const fs = require('fs');
const html = fs.readFileSync('docs/SchoolHub_Internship_Report.html', 'utf8');
const lines = html.split('\n');

let tableCount = 0;
let figureCount = 0;

for (let i = 0; i < lines.length; i++) {
  if (lines[i].includes('<table class="rt"')) {
    tableCount++;
    console.log(`Table ${tableCount} is at line ${i + 1}`);
  }
}
