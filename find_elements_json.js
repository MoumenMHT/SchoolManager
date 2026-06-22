const fs = require('fs');

const filePath = 'docs/SchoolHub_Internship_Report.html';
const html = fs.readFileSync(filePath, 'utf8');

// Split the HTML document by '<div class="page">'
// Note: split parts index 0 will be the head/style section (before the first page div)
const pages = html.split('<div class="page">');

const docData = {
  totalPages: pages.length - 1,
  pages: []
};

pages.forEach((pageContent, index) => {
  if (index === 0) return;
  const pageNum = index;
  
  const pageData = {
    pageNum: pageNum,
    headings: [],
    captions: [],
    tables: 0
  };
  
  // Find headings
  const hRegex = /<(h1|h2|h3)[^>]*>([\s\S]*?)<\/\1>/gi;
  let match;
  while ((match = hRegex.exec(pageContent)) !== null) {
    pageData.headings.push({
      tag: match[1].toLowerCase(),
      text: match[2].replace(/<[^>]+>/g, '').trim().replace(/\s+/g, ' ')
    });
  }
  
  // Find figures / diagram captions
  const figRegex = /<div class="(fig-cap|diagram-cap)"[^>]*>([\s\S]*?)<\/div>/gi;
  while ((match = figRegex.exec(pageContent)) !== null) {
    pageData.captions.push({
      cls: match[1],
      text: match[2].replace(/<[^>]+>/g, '').trim().replace(/\s+/g, ' ')
    });
  }

  // Count tables
  const tableRegex = /<table[^>]*>/gi;
  let count = 0;
  while ((match = tableRegex.exec(pageContent)) !== null) {
    count++;
  }
  pageData.tables = count;
  
  docData.pages.push(pageData);
});

fs.writeFileSync('C:\\Users\\issam\\.gemini\\antigravity\\brain\\1465a593-f850-4065-aa9c-6fb898b67730\\scratch\\elements.json', JSON.stringify(docData, null, 2), 'utf8');
console.log("Successfully wrote elements.json");
