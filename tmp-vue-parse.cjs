const fs = require('fs');
const { parse } = require('@vue/compiler-dom');
const content = fs.readFileSync('resources/js/Pages/Login.vue','utf8');
console.log('TEMPLATE COUNT', (content.match(/<template>/g)||[]).length);
console.log('close TEMPLATE COUNT', (content.match(/<\/template>/g)||[]).length);
const parts = content.split('<template>');
console.log('PARTS', parts.length);
const template = parts[1].split('</template>')[0];
console.log('TEMPLATE START', template.slice(0,40).replace(/\n/g,'\\n'));
const res = parse(template, {
  onError(e) {
    console.log('ERROR', e.message, 'line', e.loc && e.loc.start.line, 'col', e.loc && e.loc.start.column);
  }
});
console.log('parsed nodes', res.children.length);