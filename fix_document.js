const fs = require('fs');

const filePath = 'docs/SchoolHub_Internship_Report.html';
let html = fs.readFileSync(filePath, 'utf8');

// 1. Add captions to tables
let tableCaptions = [
  "System Actors & Access Levels",
  "Database Schema (Tables & Relationships)",
  "Grades Analytics Aggregation Metrics",
  "Levels Table Structure",
  "Role Visibility & Data Scoping Matrix",
  "Authentication & RBAC Token Expiry",
  "Web Dashboard Views Implemented",
  "Vue.js Key Design Patterns",
  "Mobile App Feature Summary",
  "Backend PHPUnit Test Suites",
  "Project Planning Phases & Deliverables"
];

let tableCount = 0;
// We will replace '</table>' with '</table>\n<div class="diagram-cap"><strong>Table X:</strong> Title</div>' ONLY if it doesn't already have one.
let lines = html.split('\n');
for (let i = 0; i < lines.length; i++) {
  if (lines[i].includes('<table class="rt"')) {
    tableCount++;
    // Find the closing table tag
    let j = i;
    while(j < lines.length && !lines[j].includes('</table>')) {
      j++;
    }
    if (j < lines.length && !lines[j+1].includes('Table ' + tableCount + ':')) {
       lines[j] = lines[j] + `\n  <div class="diagram-cap"><strong>Table ${tableCount}:</strong> ${tableCaptions[tableCount-1]}</div>`;
    }
  }
}
html = lines.join('\n');


// 2. Rebuild TOC strings
const newTOC = `
    <div class="toc-sec-hdr toc-row"><span class="toc-num">I.</span><span class="toc-lbl">General Introduction</span><span class="toc-dots"></span><span class="toc-pg">4</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">1. Context &amp; Background</span><span class="toc-dots"></span><span class="toc-pg">4</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">2. Problem Statement</span><span class="toc-dots"></span><span class="toc-pg">4</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">3. Project Objectives</span><span class="toc-dots"></span><span class="toc-pg">4</span></div>

    <div class="toc-sec-hdr toc-row"><span class="toc-num">II.</span><span class="toc-lbl">Requirements Analysis</span><span class="toc-dots"></span><span class="toc-pg">5</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">1. System Actors</span><span class="toc-dots"></span><span class="toc-pg">5</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">2. Functional Requirements</span><span class="toc-dots"></span><span class="toc-pg">5</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">3. Non-Functional Requirements</span><span class="toc-dots"></span><span class="toc-pg">6</span></div>

    <div class="toc-sec-hdr toc-row"><span class="toc-num">III.</span><span class="toc-lbl">System Design &amp; Conception</span><span class="toc-dots"></span><span class="toc-pg">7</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">1. Global Architecture</span><span class="toc-dots"></span><span class="toc-pg">7</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">2. Use Case Diagrams</span><span class="toc-dots"></span><span class="toc-pg">8</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">3. Class Diagram (Domain Model)</span><span class="toc-dots"></span><span class="toc-pg">9</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">4. Database Schema</span><span class="toc-dots"></span><span class="toc-pg">10</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">5. Activity Diagrams</span><span class="toc-dots"></span><span class="toc-pg">11</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">6. Sequence Diagrams</span><span class="toc-dots"></span><span class="toc-pg">13</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">7. Feature Deep-Dive: Schedule Generation Algorithm</span><span class="toc-dots"></span><span class="toc-pg">15</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">8. Feature Deep-Dive: Grading &amp; Exam Management</span><span class="toc-dots"></span><span class="toc-pg">17</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">9. Feature Deep-Dive: Class &amp; Teacher Assignments</span><span class="toc-dots"></span><span class="toc-pg">18</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">10. Feature Deep-Dive: Dashboard Analytics</span><span class="toc-dots"></span><span class="toc-pg">19</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">11. Feature Deep-Dive: Localized Financial Management</span><span class="toc-dots"></span><span class="toc-pg">20</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">12. Feature Deep-Dive: Attendance Tracking</span><span class="toc-dots"></span><span class="toc-pg">21</span></div>

    <div class="toc-sec-hdr toc-row"><span class="toc-num">IV.</span><span class="toc-lbl">Implementation – Backend API (Laravel 12)</span><span class="toc-dots"></span><span class="toc-pg">22</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">1. Project Structure</span><span class="toc-dots"></span><span class="toc-pg">22</span></div>
    <div class="toc-row"><span class="toc-num"></span><span class="toc-lbl sub">2. Authentication &amp; RBAC</span><span class="toc-dots"></span><span class="toc-pg">22</span></div>

    <div class="toc-sec-hdr toc-row"><span class="toc-num">V.</span><span class="toc-lbl">Implementation – Web Dashboard (Vue.js 3)</span><span class="toc-dots"></span><span class="toc-pg">23</span></div>

    <div class="toc-sec-hdr toc-row"><span class="toc-num">VI.</span><span class="toc-lbl">Implementation – Mobile App (Flutter)</span><span class="toc-dots"></span><span class="toc-pg">24</span></div>

    <div class="toc-sec-hdr toc-row"><span class="toc-num">VII.</span><span class="toc-lbl">Testing &amp; Validation</span><span class="toc-dots"></span><span class="toc-pg">25</span></div>

    <div class="toc-sec-hdr toc-row"><span class="toc-num">VIII.</span><span class="toc-lbl">Conclusion &amp; Future Work</span><span class="toc-dots"></span><span class="toc-pg">26</span></div>

    <div class="toc-sec-hdr toc-row"><span class="toc-num"></span><span class="toc-lbl">Table of Figures &amp; Tables</span><span class="toc-dots"></span><span class="toc-pg">3</span></div>
`;

const newTableOfFigures = `
    <div class="toc-row"><span class="toc-num">Fig. 1</span><span class="toc-lbl sub">Basic Use Case Diagram</span><span class="toc-dots"></span><span class="toc-pg">8</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 2</span><span class="toc-lbl sub">Detailed Use Case Diagram</span><span class="toc-dots"></span><span class="toc-pg">8</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 3</span><span class="toc-lbl sub">Conceptual Data Model (MCD)</span><span class="toc-dots"></span><span class="toc-pg">9</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 4</span><span class="toc-lbl sub">Logical Data Model (MLD)</span><span class="toc-dots"></span><span class="toc-pg">10</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 5</span><span class="toc-lbl sub">Activity Diagram — Schedule Generation</span><span class="toc-dots"></span><span class="toc-pg">11</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 6</span><span class="toc-lbl sub">Activity Diagram — Payment Processing</span><span class="toc-dots"></span><span class="toc-pg">12</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 7</span><span class="toc-lbl sub">Activity Diagram — Contract Creation</span><span class="toc-dots"></span><span class="toc-pg">13</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 8</span><span class="toc-lbl sub">Sequence Diagram — User Auth</span><span class="toc-dots"></span><span class="toc-pg">14</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 9</span><span class="toc-lbl sub">Sequence Diagram — Bulk Grades</span><span class="toc-dots"></span><span class="toc-pg">14</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 10</span><span class="toc-lbl sub">Sequence Diagram — Report Card</span><span class="toc-dots"></span><span class="toc-pg">15</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 11</span><span class="toc-lbl sub">Sequence Diagram — Payment Process</span><span class="toc-dots"></span><span class="toc-pg">15</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 12</span><span class="toc-lbl sub">Sequence Diagram — Full report card generation flow</span><span class="toc-dots"></span><span class="toc-pg">18</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 13</span><span class="toc-lbl sub">Activity Diagram — Class ranking generation</span><span class="toc-dots"></span><span class="toc-pg">18</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 14</span><span class="toc-lbl sub">Activity Diagram — Automatic cycle-based data scoping</span><span class="toc-dots"></span><span class="toc-pg">19</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 15</span><span class="toc-lbl sub">Entity-Relationship Diagram — ClassSubjectTeacher Pivot</span><span class="toc-dots"></span><span class="toc-pg">20</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 16</span><span class="toc-lbl sub">Sequence Diagram — Admin assigns teacher to class</span><span class="toc-dots"></span><span class="toc-pg">21</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 17</span><span class="toc-lbl sub">Activity Diagram — Student enrollment & class transfer flow</span><span class="toc-dots"></span><span class="toc-pg">21</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 18</span><span class="toc-lbl sub">Entity-Relationship Diagram — Billing & Transactions</span><span class="toc-dots"></span><span class="toc-pg">23</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 19</span><span class="toc-lbl sub">Entity-Relationship Diagram — Multi-dimensional Attendance</span><span class="toc-dots"></span><span class="toc-pg">24</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 20</span><span class="toc-lbl sub">Work Breakdown Structure (WBS)</span><span class="toc-dots"></span><span class="toc-pg">30</span></div>
    <div class="toc-row"><span class="toc-num">Fig. 21</span><span class="toc-lbl sub">Gantt Chart</span><span class="toc-dots"></span><span class="toc-pg">30</span></div>
`;

let newTableOfTables = "";
tableCaptions.forEach((title, idx) => {
   newTableOfTables += `    <div class="toc-row"><span class="toc-num">Tab. ${idx+1}</span><span class="toc-lbl sub">${title}</span><span class="toc-dots"></span><span class="toc-pg">-</span></div>\n`;
});

// We must replace the blocks in the HTML.
let tocStart = html.indexOf('<div class="toc-sec-hdr toc-row"><span class="toc-num">I.</span>');
let tocEnd = html.indexOf('<div class="toc-sec-hdr toc-row"><span class="toc-num">IX.</span>');

if (tocStart !== -1 && tocEnd !== -1) {
  html = html.substring(0, tocStart) + newTOC + "\n" + html.substring(tocEnd);
}

let tofStart = html.indexOf('<div class="toc-row"><span class="toc-num">Fig. 1</span>');
let tofEnd = html.indexOf('<h1 class="ch" style="margin-top: 40px;">Table of Tables</h1>');

if (tofStart !== -1 && tofEnd !== -1) {
  html = html.substring(0, tofStart) + newTableOfFigures + "\n  " + html.substring(tofEnd);
}

let totStart = html.indexOf('<div class="toc-row"><span class="toc-num">Tab. 1</span>');
let totEnd = html.indexOf('</div>\n\n\n<!-- ════════════════════════════════════════════════════\n     PAGE 4 — GENERAL INTRODUCTION');

if (totStart !== -1 && totEnd !== -1) {
  html = html.substring(0, totStart) + newTableOfTables + "\n  " + html.substring(totEnd);
}

// Ensure all figures are numbered sequentially.
let figCounter = 1;
html = html.replace(/<div class="diagram-cap"><strong>Figure \d+:/g, () => {
    return `<div class="diagram-cap"><strong>Figure ${figCounter++}:`;
});

fs.writeFileSync(filePath, html, 'utf8');
console.log("Done successfully!");
