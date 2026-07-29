const fs = require('fs');
const { parse } = require('@vue/compiler-dom');
const content = fs.readFileSync('resources/js/Pages/Login.vue', 'utf8');
const template = content.split('<template>')[1].split('</template>')[0];
const lines = template.split(/\r?\n/);
for (let i = 1; i <= lines.length; i++) {
  const part = lines.slice(0, i).join('\n');
  let err = null;
  parse(part, {
    onError(e) {
      err = e;
    },
  });
  if (err) {
    console.log('FAIL_LINE', i, 'ERR', err.message, 'line', err.loc.start.line, 'col', err.loc.start.column);
    console.log('PART', JSON.stringify(part.slice(-200)));
    process.exit(0);
  }
}
console.log('NO_ERROR_UPTO', lines.length);
