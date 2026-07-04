import { readFileSync, writeFileSync } from 'node:fs';

const p = 'C:/Users/Admin/Desktop/catharsis-site/index.html';
let f = readFileSync(p, 'utf8');

const edits = [
  ["aboutP2a: 'Reunim medici psihiatri, psihologi clinicieni și psihoterapeuți într-o echipă coordonată de '",
   "aboutP2a: 'Reunim medici psihiatri, psihologi clinicieni și psihoterapeuți cu experiență, printre care '"],
  ["aboutP2a: 'We bring together psychiatrists, clinical psychologists and psychotherapists in a team led by '",
   "aboutP2a: 'We bring together experienced psychiatrists, clinical psychologists and psychotherapists, among them '"],
];

for (const [oldS, newS] of edits) {
  const n = f.split(oldS).length - 1;
  console.log(`${n} occurrence(s): ${oldS.slice(0, 55)}...`);
  if (n !== 1) { console.error('EXPECTED EXACTLY 1 — aborting'); process.exit(1); }
  f = f.split(oldS).join(newS);
}

writeFileSync(p, f);
console.log('OK');
