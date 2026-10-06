// Builds the cPanel upload package (docs/C-STAR-Deployment-BN.md):
//
//   release/cstar-<version>.zip
//   ├── cstar-app/     Laravel — goes NEXT TO public_html (never inside it)
//   └── public_html/   web root: index.php, .htaccess, spa/ (staff & parent apps), build/ (website styles)
//
// Uses the last commit (git archive), so commit first. Needs Node, git and PHP 8.3 + Composer locally;
// the server needs neither Node nor Composer.
//
//   node scripts/build-release.mjs [--php F:/web/php83/php.exe]

import { execFileSync, execSync } from 'node:child_process'
import { cpSync, existsSync, mkdirSync, readFileSync, renameSync, rmSync, writeFileSync } from 'node:fs'
import { join, resolve } from 'node:path'

const root = resolve(import.meta.dirname, '..')
const arg = (name, fallback) => {
  const i = process.argv.indexOf(name)
  return i > -1 ? process.argv[i + 1] : fallback
}
const php = arg('--php', process.env.PHP_BINARY ?? 'F:/web/php83/php.exe')
const run = (cmd, cwd = root) => {
  console.log(`\n> ${cmd}`)
  execSync(cmd, { cwd, stdio: 'inherit' })
}

const dirty = execSync('git status --porcelain', { cwd: root }).toString().trim()
if (dirty) console.warn('⚠ Uncommitted changes are NOT in the package (it is built from the last commit).')
const commit = execSync('git rev-parse --short HEAD', { cwd: root }).toString().trim()
const version = `${new Date().toISOString().slice(0, 10)}-${commit}`

const out = join(root, 'release')
const stage = join(out, `cstar-${version}`)
const app = join(stage, 'cstar-app')
const web = join(stage, 'public_html')
rmSync(stage, { recursive: true, force: true })
mkdirSync(app, { recursive: true })

// 1. Backend source from the last commit.
const tar = join(out, 'backend.tar')
execFileSync('git', ['archive', '--format=tar', `--output=${tar}`, 'HEAD:backend'], { cwd: root })
execFileSync('tar', ['-xf', tar, '-C', app])
rmSync(tar)
for (const p of ['tests', 'phpunit.xml', '.env.example', 'node_modules', 'public/spa', 'public/build']) rmSync(join(app, p), { recursive: true, force: true })

// 2. PHP dependencies without dev tools.
run(`"${php}" "${composerPhar()}" install --no-dev --optimize-autoloader --no-interaction --no-progress`, app)

// 3. Staff & parent apps (React) for the domain root, and the website styles.
run('npx tsc -b', join(root, 'frontend'))
run(`npx vite build --mode production --outDir "${join(app, 'public', 'spa')}" --emptyOutDir`, join(root, 'frontend'))
run('npm run build', join(root, 'backend'))
cpSync(join(root, 'backend', 'public', 'build'), join(app, 'public', 'build'), { recursive: true })

// 4. public/ becomes public_html; index.php looks for the app one folder up in cstar-app/.
renameSync(join(app, 'public'), web)
const index = join(web, 'index.php')
writeFileSync(index, readFileSync(index, 'utf8').replaceAll("__DIR__.'/../", "__DIR__.'/../cstar-app/"))
writeFileSync(join(app, '.public-path'), '../public_html\n')
const htaccess = join(web, '.htaccess')
writeFileSync(
  htaccess,
  readFileSync(htaccess, 'utf8').replace(
    '    RewriteEngine On\n',
    '    RewriteEngine On\n\n    # Never serve hidden files (.env copies, .git …)\n    RewriteRule (^|/)\\.(?!well-known/) - [F,L]\n',
  ) + '\n# No directory listings anywhere\nOptions -Indexes\n',
)
// Safety net: if cstar-app is ever placed inside a web folder by mistake, Apache still refuses it.
writeFileSync(join(app, '.htaccess'), 'Require all denied\n')

writeFileSync(join(stage, 'VERSION.txt'), `C-STAR ${version}\nBuilt ${new Date().toString()}\n`)

// 5. Zip.
const zip = join(out, `cstar-${version}.zip`)
rmSync(zip, { force: true })
execFileSync('tar', ['-a', '-c', '-f', zip, '-C', stage, 'cstar-app', 'public_html', 'VERSION.txt'])
rmSync(stage, { recursive: true, force: true })
console.log(`\n✔ ${zip}\nUpload and follow docs/C-STAR-Deployment-BN.md (then run: php artisan cstar:deploy).`)

function composerPhar() {
  const candidates = [process.env.COMPOSER_PHAR, 'C:/ProgramData/ComposerSetup/bin/composer.phar']
  try {
    candidates.push(join(execSync('where composer', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().split(/\r?\n/)[0], '..', 'composer.phar'))
  } catch {
    // composer not on PATH
  }
  const found = candidates.filter(Boolean).find((p) => existsSync(p))
  if (!found) throw new Error('composer.phar not found — set COMPOSER_PHAR')
  return found
}
