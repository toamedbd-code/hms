#!/usr/bin/env node
// fixes `text-white` used on light background classes inside .vue files
const fs = require('fs');
const path = require('path');

const base = path.join(process.cwd(), 'resources/js/Pages');
const lightBgList = [
  'bg-white', 'bg-slate-50','bg-slate-100','bg-slate-200','bg-slate-300','bg-slate-400',
  'bg-gray-50','bg-gray-100','bg-gray-200','bg-gray-300','bg-gray-400',
  'bg-amber-50','bg-yellow-50','bg-rose-50','bg-emerald-50','bg-indigo-50','bg-blue-50','bg-amber-100','bg-yellow-100'
];

function walk(dir, files) {
  const items = fs.readdirSync(dir, { withFileTypes: true });
  for (const it of items) {
    const p = path.join(dir, it.name);
    if (it.isDirectory()) walk(p, files);
    else if (it.isFile() && it.name.endsWith('.vue')) files.push(p);
  }
}

if (!fs.existsSync(base)) {
  console.error('Base folder not found:', base);
  process.exit(1);
}

const files = [];
walk(base, files);

const tagClassRe = /<([a-zA-Z0-9:-]+)([^>]*?)\sclass\s*=\s*(['"])([\s\S]*?)\3([^>]*)>/g;
let changedFiles = [];

for (const file of files) {
  let content = fs.readFileSync(file, 'utf8');
  let out = content;
  let fileChanged = false;
  out = out.replace(tagClassRe, (full, tag, before, quote, cls, after) => {
    if (!/\btext-white\b/.test(cls)) return full;
    // skip obvious button classes or button tag
    if (/\bbtn(-|$|\b)/.test(cls) || /\bbtn-primary\b/.test(cls) || /\bbtn-danger\b/.test(cls) || tag.toLowerCase() === 'button') {
      return full;
    }
    // skip dynamic templates or bound classes
    if (cls.includes('{') || cls.includes(':')) return full;
    const hasLightBg = lightBgList.some(bg => new RegExp('\\b' + bg + '\\b').test(cls));
    if (!hasLightBg) return full;

    let newCls = cls.replace(/\btext-white\b/g, '').replace(/\s+/g, ' ').trim();
    if (!/\btext-[^\s]+\b/.test(newCls)) {
      newCls = (newCls ? (newCls + ' ') : '') + 'text-slate-700';
    }
    fileChanged = true;
    return `<${tag}${before} class=${quote}${newCls}${quote}${after}>`;
  });

  if (fileChanged && out !== content) {
    fs.writeFileSync(file, out, 'utf8');
    changedFiles.push(file);
  }
}

console.log('Processed', files.length, 'files. Changed:', changedFiles.length);
for (const f of changedFiles) console.log(f);
