const fs = require('fs');
const { parse: parseSFC, compileTemplate } = require('@vue/compiler-sfc');
const { parse: parseDOM } = require('@vue/compiler-dom');
const content = fs.readFileSync('resources/js/Pages/Login.vue', 'utf8');
const descriptor = parseSFC(content).descriptor;
console.log('templateExists', !!descriptor.template);
if (descriptor.template) {
  const tpl = descriptor.template.content;
  console.log('templateHead', JSON.stringify(tpl.slice(0,160)));
  console.log('templateLength', tpl.length);
  try {
    const ast = parseDOM(tpl, { onError: (err) => { throw err; } });
    console.log('DOM parsed nodes', ast.children.length);
  } catch (err) {
    console.error('DOM parse error', err.message, err.loc);
  }
  try {
    const res = compileTemplate({ source: tpl, filename: 'Login.vue', id: 'test' });
    console.log('compile errors', res.errors.length);
    res.errors.forEach((e) => console.error('ERR', e.message, JSON.stringify(e.loc)));
  } catch (err) {
    console.error('compileTemplate crashed', err.message);
  }
}
