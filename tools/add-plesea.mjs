import { readFileSync, writeFileSync } from 'node:fs';

const p = 'C:/Users/Admin/Desktop/catharsis-site/index.html';
let f = readFileSync(p, 'utf8');

const anchor = "{ img: ((window.__resources||{}).stan||'assets/team/stan.png')";

const ro = `{ img: 'assets/asset-030.png', name: 'Dr. Cătălin Plesea Condratovici', first: 'dr. Plesea Condratovici', role: 'Medic psihiatru', bio: 'Abordare atentă și riguroasă a tulburărilor psihice, centrată pe nevoile fiecărui pacient. Îmbină tratamentul medicamentos cu recomandări practice pentru viața de zi cu zi.', tags: ['Psihiatrie generală', 'Tratament personalizat', 'Abordare empatică'], long: ['Dr. Cătălin Plesea Condratovici este medic psihiatru, dedicat diagnosticării și tratamentului tulburărilor psihice la adulți. Construiește împreună cu pacientul un plan de tratament adaptat nevoilor și ritmului fiecăruia.', 'Pune accent pe comunicarea deschisă și pe implicarea activă a pacientului în procesul de recuperare.'], formare: ['Medic psihiatru'], expertiza: ['Psihiatrie generală adulți', 'Tulburări anxioase și depresive', 'Tratament farmacologic personalizat'] },
        `;

const en = `{ img: 'assets/asset-030.png', name: 'Dr. Cătălin Plesea Condratovici', first: 'Dr. Plesea Condratovici', role: 'Psychiatrist', bio: "A careful, rigorous approach to mental health conditions, centred on each patient's needs. Combines medication with practical guidance for everyday life.", tags: ['General psychiatry', 'Personalised treatment', 'Empathetic approach'], long: ["Dr. Cătălin Plesea Condratovici is a psychiatrist dedicated to diagnosing and treating mental health conditions in adults. Together with the patient, he builds a treatment plan adapted to each person's needs and pace.", "He emphasises open communication and the patient's active involvement in the recovery process."], formare: ['Psychiatrist'], expertiza: ['General adult psychiatry', 'Anxiety and depressive disorders', 'Personalised pharmacological treatment'] },
        `;

const parts = f.split(anchor);
if (parts.length !== 3) { console.error(`EXPECTED anchor exactly 2x, found ${parts.length - 1} — aborting`); process.exit(1); }

f = parts[0] + ro + anchor + parts[1] + en + anchor + parts[2];
writeFileSync(p, f);
console.log('OK — profil adaugat in RO si EN');
