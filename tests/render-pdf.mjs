import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {createCanvas} = require('../.tools/browser/node_modules/@napi-rs/canvas');
const {getDocument} = await import('../.tools/browser/node_modules/pdfjs-dist/legacy/build/pdf.mjs');
const folder = path.resolve('.tools/artifacts');
for (const name of fs.readdirSync(folder).filter(file => file.endsWith('.pdf'))) {
  const pdf = await getDocument({data:new Uint8Array(fs.readFileSync(path.join(folder,name))),useSystemFonts:true}).promise;
  console.log(`${name}: ${pdf.numPages} pages`);
  for (let i = 1; i <= pdf.numPages; i++) {
    const page = await pdf.getPage(i), viewport = page.getViewport({scale:1.5});
    const canvas = createCanvas(Math.ceil(viewport.width), Math.ceil(viewport.height));
    await page.render({canvasContext:canvas.getContext('2d'),viewport}).promise;
    fs.writeFileSync(path.join(folder,`${name.slice(0,-4)}-${i}.png`),canvas.toBuffer('image/png'));
  }
}
