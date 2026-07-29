const fs = require('fs');
const { parse } = require('@vue/compiler-dom');
const content = fs.readFileSync('resources/js/Pages/Login.vue','utf8');
const template = content.split('<template>')[1].split('</template>')[0];
const withoutHead = template.replace('    <Head title="Log in"></Head>\r\n\r\n', '');
let err = null;
parse(withoutHead, {
  onError(e) {
    err = e;
    console.log('ERR', e.message, e.loc.start.line, e.loc.start.column);
  },
});
console.log('RESULT', !err);
