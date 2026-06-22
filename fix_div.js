const fs = require('fs');
let html = fs.readFileSync('docs/SchoolHub_Internship_Report.html', 'utf8');

if (!html.includes('</div>\n\n  <h1 class="ch" style="margin-top: 40px;">Table of Tables</h1>')) {
    html = html.replace(/\s*<h1 class="ch" style="margin-top: 40px;">Table of Tables<\/h1>/, '\n  </div>\n\n  <h1 class="ch" style="margin-top: 40px;">Table of Tables</h1>');
    fs.writeFileSync('docs/SchoolHub_Internship_Report.html', html, 'utf8');
    console.log("Fixed missing closing div");
} else {
    console.log("Div was already there");
}
