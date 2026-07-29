import { parse } from '@vue/compiler-dom';
const tpl = `
    <Head title="Log in"></Head>

    <div class="relative"></div>
`;
const res = parse(tpl, {
  onError(e) {
    console.log('ERR', e.message, 'line', e.loc.start.line, 'col', e.loc.start.column);
  },
});
console.log('CHILDREN', res.children.length);