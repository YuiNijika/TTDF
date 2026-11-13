import { copyFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const files = [
    ['node_modules/vue/dist/vue.global.prod.js', 'static/vue/vue.global.prod.js'],
    ['node_modules/element-plus/dist/index.css', 'static/element-plus/index.css'],
    ['node_modules/element-plus/dist/index.full.min.js', 'static/element-plus/index.full.min.js'],
];

for (const [source, target] of files) {
    const destination = resolve(root, target);
    await mkdir(dirname(destination), { recursive: true });
    await copyFile(resolve(root, source), destination);
}

console.log(`Synced ${files.length} framework static files.`);
