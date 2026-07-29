const fs = require('fs');
const { parse } = require('@vue/compiler-dom');
const { parse: parseSFC, compileTemplate } = require('@vue/compiler-sfc');
const content = fs.readFileSync('resources/js/Pages/Login.vue','utf8');
const template = content.split('<template>')[1].split('</template>')[0];
console.log('TEMPLATE_HEAD', JSON.stringify(template.slice(0,180)));
console.log('TEMPLATE_FIRST_LINES');
const lines = template.split(/\r?\n/);
lines.slice(0,12).forEach((line, idx) => console.log(idx+1, line));
console.log('--- PARSE DOM ---');
const res = parse(template, {
  onError(e) {
    console.log('PARSE_ERR', e.message, 'line', e.loc.start.line, 'col', e.loc.start.column);
  }
});
console.log('DOM_CHILDREN', res.children.length);
console.log('--- COMPILE TEMPLATE ---');
const compiled = compileTemplate({ source: template, filename: 'Login.vue', id: 'test' });
console.log('COMPILE_ERR_COUNT', compiled.errors.length);
compiled.errors.forEach((err) => console.log('COMPILE_ERR', err.message, JSON.stringify(err.loc)));
