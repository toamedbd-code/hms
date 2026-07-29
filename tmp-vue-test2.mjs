import { parse } from '@vue/compiler-dom';
const tpl = `
    <Head title="Log in"></Head>

    <div class="relative min-h-screen overflow-hidden bg-[#f6f3ee]">
    </div>
`;
const res = parse(tpl, {
  onError(e) {
    console.log('ERR', e.message, 'line', e.loc.start.line, 'col', e.loc.start.column);
  },
});
console.log('CHILDREN', res.children.length);
res.children.forEach((child, idx) => {
  console.log('NODE', idx, child.type, child.tag, child.loc.start.line, child.loc.start.column, child.loc.end.line, child.loc.end.column);
});
