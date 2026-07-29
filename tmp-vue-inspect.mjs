import { readFileSync } from "fs";
import { parse } from "@vue/compiler-sfc";
const content = readFileSync("resources/js/Pages/Login.vue", "utf8");
const descriptor = parse(content).descriptor;
console.log('templateLoc', descriptor.template && JSON.stringify(descriptor.template.loc));
console.log('templateContentHead', descriptor.template && JSON.stringify(descriptor.template.content.slice(0,60)));
console.log('templateContentFullFirst200', descriptor.template && JSON.stringify(descriptor.template.content.slice(0,200)));
console.log('templateContentHash', descriptor.template && descriptor.template.content.length);
console.log('templateDelimiters', descriptor.template && [content.slice(descriptor.template.loc.start.offset, descriptor.template.loc.start.offset+20), content.slice(descriptor.template.loc.end.offset-20, descriptor.template.loc.end.offset)]);