const fs = require('fs');
const { parse } = require('@vue/compiler-dom');
const content = fs.readFileSync('resources/js/Pages/Login.vue', 'utf8');
const template = content.split('<template>')[1].split('</template>')[0];
console.log('ORIGINAL_HEAD_LINE', template.split(/\r?\n/)[1]);
let err = null;
parse(template, {
  onError(e) {
    err = e;
  },
});
console.log('ORIGINAL_ERR', err && err.message, err && err.loc && err.loc.start);
const fixed = template.replace('<Head title="Log in"></Head>', '<Head title="Log in" />');
err = null;
parse(fixed, {
  onError(e) {
    err = e;
  },
});
console.log('FIXED_ERR', err && err.message, err && err.loc && err.loc.start);
console.log('FIXED_WORKS', !err);
