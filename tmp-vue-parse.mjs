import fs from "fs";
import { parse } from "@vue/compiler-dom";
const content = fs.readFileSync("resources/js/Pages/Login.vue","utf8");
const template = content.split("<template>",1)[1].split("</template>",1)[0];
const res = parse(template, {
  onError(e) {
    console.log(JSON.stringify({ msg: e.message, line: e.loc && e.loc.start.line, col: e.loc && e.loc.start.column }));
  }
});
console.log("parsed nodes", res.children.length);