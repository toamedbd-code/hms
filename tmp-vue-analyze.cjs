const fs = require('fs');
const content = fs.readFileSync('resources/js/Pages/Login.vue', 'utf8');
const tpl = content.split('<template>')[1].split('</template>')[0];
const lines = tpl.split(/\r?\n/);
console.log('TOTAL_LINES', lines.length);
for (let i = 0; i < 12; i++) {
  const line = lines[i];
  process.stdout.write(`LINE ${i+1}: ${JSON.stringify(line)}\n`);
  process.stdout.write('CODES: ' + line.split('').map((c,j)=>`${j}:${c.charCodeAt(0)}`).join(' ') + '\n');
}
console.log('BINARY DUMP AROUND LINE 4:');
const offset = tpl.indexOf('<div class="relative min-h-screen');
console.log('OFFSET', offset);
const raw = Buffer.from(tpl, 'utf8');
console.log(raw.slice(offset-10, offset+100).toString('hex').match(/.{1,2}/g).join(' '));
console.log(raw.slice(offset-10, offset+100).toString('utf8'));
