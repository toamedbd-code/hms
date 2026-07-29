const fs = require('fs');
const { parse } = require('@vue/compiler-dom');
const content = fs.readFileSync('resources/js/Pages/Login.vue','utf8');
const template = content.split('<template>')[1].split('</template>')[0];
const res = parse(template, {
  onError(e) {
    console.log('PARSE_ERR', e.message, 'line', e.loc.start.line, 'col', e.loc.start.column);
  }
});
console.log('CHILDREN', res.children.length);
res.children.forEach((child, index) => {
  console.log('NODE', index, child.type, child.tag, child.loc && child.loc.start, child.loc && child.loc.end);
  if (child.type === 1) {
    console.log('  props', child.props.map(p => ({ type: p.type, name: p.name, value: p.value && p.value.content })));
  }
});
