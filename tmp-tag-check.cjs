const fs = require('fs');
const content = fs.readFileSync('resources/js/Pages/Login.vue', 'utf8');
const template = content.split('<template>')[1].split('</template>')[0];
const tagRe = /<\/?\s*([a-zA-Z0-9_:-]+)([^>]*)>/g;
const selfClosingRe = /\/\s*>\s*$/;
const stack = [];
let match;
while ((match = tagRe.exec(template)) !== null) {
  const full = match[0];
  const tag = match[1];
  const attrs = match[2];
  const isClosing = /^<\s*\//.test(full);
  const isSelfClosing = selfClosingRe.test(full) || /\b(?:area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)\b/i.test(tag);
  const line = template.slice(0, match.index).split(/\r?\n/).length;
  const col = match.index - template.lastIndexOf('\n', match.index) - 1;
  console.log('TAG', line, col, isClosing ? '</' + tag + '>' : '<' + tag + '>', full);
  if (isClosing) {
    if (stack.length === 0) {
      console.log('UNEXPECTED CLOSE', tag, line, col);
      break;
    }
    const top = stack[stack.length - 1];
    if (top.tag === tag) {
      stack.pop();
    } else {
      console.log('MISMATCH', top.tag, 'vs', tag, 'at', line, col);
      break;
    }
  } else if (!isSelfClosing) {
    stack.push({ tag, line, col });
  }
}
console.log('STACK REMAINING', stack);
