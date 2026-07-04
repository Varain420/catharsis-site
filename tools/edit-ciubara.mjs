import { readFileSync, writeFileSync } from 'node:fs';

const p = 'C:/Users/Admin/Desktop/catharsis-site/index.html';
let f = readFileSync(p, 'utf8');

const edits = [
  // RO bio scurt
  ["bio: 'Fondatoare și coordonator medical al clinicii Catharsis. Doctor în științe medicale",
   "bio: 'Doctor în științe medicale"],
  // RO profil lung
  ["cercetării academice. Coordonează activitatea clinicii Catharsis și formează noile generații de medici",
   "cercetării academice. Formează noile generații de medici"],
  // EN short bio
  ["bio: 'Founder and medical coordinator of the Catharsis clinic. Doctor of medical sciences",
   "bio: 'Doctor of medical sciences"],
  // EN long profile
  ["She coordinates the Catharsis clinic and trains new generations of doctors",
   "She trains new generations of doctors"],
];

for (const [oldS, newS] of edits) {
  const n = f.split(oldS).length - 1;
  console.log(`${n} occurrence(s): ${oldS.slice(0, 60)}...`);
  if (n !== 1) { console.error('EXPECTED EXACTLY 1 — aborting'); process.exit(1); }
  f = f.split(oldS).join(newS);
}

writeFileSync(p, f);
console.log('OK — all 4 edits applied');
