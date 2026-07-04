import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { gunzipSync } from 'node:zlib';
import { join } from 'node:path';

const [,, inFile, outDir] = process.argv;
const html = readFileSync(inFile, 'utf8');

function extractBlock(type) {
  const open = `<script type="__bundler/${type}">`;
  const start = html.indexOf(open);
  if (start === -1) return null;
  const end = html.indexOf('</script>', start);
  return html.slice(start + open.length, end);
}

const manifest = JSON.parse(extractBlock('manifest'));
const extResources = JSON.parse(extractBlock('ext_resources') ?? '[]');
let template = JSON.parse(extractBlock('template'));

const extByMime = {
  'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp',
  'image/svg+xml': 'svg', 'image/gif': 'gif', 'image/x-icon': 'ico',
  'image/avif': 'avif', 'font/woff2': 'woff2', 'font/woff': 'woff',
  'font/ttf': 'ttf', 'application/font-woff2': 'woff2',
  'text/css': 'css', 'text/javascript': 'js', 'application/javascript': 'js',
  'application/json': 'json', 'audio/mpeg': 'mp3', 'video/mp4': 'mp4',
  'application/pdf': 'pdf',
};

mkdirSync(join(outDir, 'assets'), { recursive: true });

const pathByUuid = {};
let i = 0;
for (const [uuid, entry] of Object.entries(manifest)) {
  let bytes = Buffer.from(entry.data, 'base64');
  if (entry.compressed) bytes = gunzipSync(bytes);
  const ext = extByMime[entry.mime] ?? 'bin';
  const rel = `assets/asset-${String(++i).padStart(3, '0')}.${ext}`;
  writeFileSync(join(outDir, rel), bytes);
  pathByUuid[uuid] = rel;
  console.log(`${rel}  ${entry.mime}  ${bytes.length} bytes`);
}

for (const [uuid, rel] of Object.entries(pathByUuid)) {
  template = template.split(uuid).join(rel);
}

const resourceMap = {};
for (const e of extResources) {
  if (pathByUuid[e.uuid]) resourceMap[e.id] = pathByUuid[e.uuid];
}
if (Object.keys(resourceMap).length > 0) {
  const resourceScript = '<script>window.__resources = ' +
    JSON.stringify(resourceMap).replaceAll('</script>', '<\\/script>') +
    ';</script>';
  const headOpen = template.match(/<head[^>]*>/i);
  if (headOpen) {
    const at = headOpen.index + headOpen[0].length;
    template = template.slice(0, at) + resourceScript + template.slice(at);
  }
  console.log('ext_resources map injected:', Object.keys(resourceMap).length, 'entries');
}

writeFileSync(join(outDir, 'index.html'), template);
console.log(`index.html  ${Buffer.byteLength(template)} bytes`);
