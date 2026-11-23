// Fails when a script asks for a translation key locale/en.yml doesn't have,
// which would show the raw key to readers.
const fs = require('fs');
const path = require('path');

const yml = fs.readFileSync(path.join(__dirname, '../locale/en.yml'), 'utf8');
const keys = new Set();
const stack = [];
for (const line of yml.split('\n')) {
  const m = line.match(/^( *)([\w-]+):/);
  if (!m) continue;
  const depth = m[1].length / 2;
  stack.length = depth;
  stack[depth] = m[2];
  keys.add(stack.join('.'));
}

const missing = [];
const scan = (dir, prefix) => {
  for (const file of fs.readdirSync(dir, { recursive: true })) {
    if (!/\.(js|tsx?)$/.test(file)) continue;
    const src = fs.readFileSync(path.join(dir, file), 'utf8');
    for (const [, key] of src.matchAll(/\bt\('([\w.]+)'/g)) {
      if (!keys.has(prefix + key)) missing.push(`${file}: ${prefix + key}`);
    }
  }
};
scan(path.join(__dirname, 'src/forum'), 'ernestdefoe-dossier.forum.');
scan(path.join(__dirname, 'src/admin'), 'ernestdefoe-dossier.admin.');

// Keys built from a value at run time.
const sections = ['about', 'timeline', 'skills', 'languages', 'projects', 'certificates', 'links', 'open_to'];
const dynamic = [
  ...sections.map((s) => `forum.sections.${s}`),
  ...['everyone', 'members', 'private'].map((v) => `forum.visibility.${v}`),
  ...['work', 'education'].map((k) => `forum.timeline.kinds.${k}`),
  ...['a1', 'a2', 'b1', 'b2', 'c1', 'c2', 'native'].map((l) => `forum.languages.levels.${l}`),
  ...['website', 'linkedin', 'github', 'gitlab', 'x', 'mastodon', 'custom'].map((k) => `forum.links.kinds.${k}`),
  ...['timeline', 'languages', 'certificates', 'links', 'skills'].flatMap((s) => [`forum.modals.${s}.add`, `forum.modals.${s}.edit`]),
  ...['collaboration', 'mentoring', 'freelance', 'jobs'].map((id) => `lib.open_to.${id}`),
];
for (const key of dynamic) if (!keys.has('ernestdefoe-dossier.' + key)) missing.push('dynamic: ' + key);

// The API's validation messages (src/Input.php and friends).
const php = ['Input.php', 'Api/SaveEntry.php', 'Api/Admin/SaveSkill.php']
  .map((f) => fs.readFileSync(path.join(__dirname, '../src', f), 'utf8'))
  .join('\n');
for (const [, key] of php.matchAll(/fail\('[\w]+', '([\w]+)'/g)) {
  if (!keys.has('ernestdefoe-dossier.api.' + key)) missing.push('api: ' + key);
}

if (missing.length) {
  console.error('Missing locale keys:\n' + missing.join('\n'));
  process.exit(1);
}
console.log(`All locale keys present (${keys.size} in en.yml).`);
