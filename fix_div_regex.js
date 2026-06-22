const fs = require('fs');
let html = fs.readFileSync('docs/SchoolHub_Internship_Report.html', 'utf8');

// The Table of Figures section is missing its closing </div>
// Currently it is:
//     <div class="toc-row"><span class="toc-num">Fig. 13</span><span class="toc-lbl sub">Gantt Chart (GanttPRO)</span><span class="toc-dots"></span><span class="toc-pg">24</span></div>
// \r\n\r\n  <h1 class="ch" style="margin-top: 40px;">Table of Tables</h1>

// We need to inject `  </div>` right before `<h1 class="ch" style="margin-top: 40px;">Table of Tables</h1>`
// But we ONLY do it if it's missing.

const searchPattern = /<div class="toc-row"><span class="toc-num">Fig\. 13.*?<\/div>\s*<h1 class="ch" style="margin-top: 40px;">Table of Tables<\/h1>/;

if (searchPattern.test(html)) {
    html = html.replace(/(<div class="toc-row"><span class="toc-num">Fig\. 13.*?<\/div>)\s*(<h1 class="ch" style="margin-top: 40px;">Table of Tables<\/h1>)/, '$1\n  </div>\n\n  $2');
    fs.writeFileSync('docs/SchoolHub_Internship_Report.html', html, 'utf8');
    console.log("Fixed the missing </div> successfully!");
} else {
    console.log("Could not find the missing div pattern. Maybe it's already fixed or something else is wrong.");
}
