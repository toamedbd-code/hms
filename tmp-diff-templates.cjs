const fs = require('fs');
const a = fs.readFileSync('resources/js/Pages/Login.vue','utf8');
const b = fs.readFileSync('resources/js/Pages/Auth/Login.vue','utf8');
const ta = a.split('<template>')[1].split('</template>')[0];
const tb = b.split('<template>')[1].split('</template>')[0];
const la = ta.split(/\r?\n/);
const lb = tb.split(/\r?\n/);
const max = Math.max(la.length, lb.length);
for (let i = 0; i < max; i++) {
  const A = la[i] || '';
  const B = lb[i] || '';
  if (A !== B) {
    console.log('Line', i+1);
    console.log('- A:', A);
    console.log('+ B:', B);
    console.log('');
  }
}
console.log('done');
