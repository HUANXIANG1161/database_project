// 生成演示用的最小合法 PDF（不依赖第三方库）。
const fs = require('fs');

const out = 'D:/database_project/public/uploads/demo/xiamen-plan.pdf';
const lines = [
  [24, 'Xiamen 3-Day Itinerary'],
  [16, 'Day 1  Arrive, Zhongshan Road, Shapowei'],
  [16, 'Day 2  Gulangyu Island, Sunlight Rock, Piano Museum'],
  [16, 'Day 3  Huandao Road, Jimei School Village, return'],
  [14, ''],
  [14, 'Budget: about CNY 1200 per person (excluding transport)'],
  [14, 'Stay: Zengcuoan or Zhongshan Road area'],
  [14, 'Tips: book Gulangyu ferry in advance; avoid peak hours'],
  [12, ''],
  [12, 'Generated for the travel sharing course project demo.'],
];

let stream = '';
let y = 780;
stream += 'BT\n';
for (const [size, text] of lines) {
  const safe = text.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
  stream += `/F1 ${size} Tf 1 0 0 1 72 ${y} Tm (${safe}) Tj\n`;
  y -= size + 12;
}
stream += 'ET\n';

const objects = [
  null,
  '<< /Type /Catalog /Pages 2 0 R >>',
  '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
  '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
  '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
  `<< /Length ${Buffer.byteLength(stream, 'ascii')} >>\nstream\n${stream}endstream`,
];

let pdf = '%PDF-1.4\n';
const offsets = [0];
for (let i = 1; i < objects.length; i++) {
  offsets[i] = Buffer.byteLength(pdf, 'ascii');
  pdf += `${i} 0 obj\n${objects[i]}\nendobj\n`;
}
const xref = Buffer.byteLength(pdf, 'ascii');
pdf += `xref\n0 ${objects.length}\n0000000000 65535 f \n`;
for (let i = 1; i < objects.length; i++) {
  pdf += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
}
pdf += `trailer\n<< /Size ${objects.length} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
fs.writeFileSync(out, pdf, 'ascii');
console.log('wrote', out, Buffer.byteLength(pdf, 'ascii'), 'bytes');
