import * as esbuild from 'esbuild';

const watch = process.argv.includes('--watch');

/** @type {esbuild.BuildOptions[]} */
const configs = [
  // Frontend JS
  {
    entryPoints: ['src/resources/frontend/js/social-proof.js'],
    outfile: 'src/resources/frontend/js/social-proof.min.js',
    bundle: false,
    minify: true,
    sourcemap: true,
  },
  // Frontend CSS
  {
    entryPoints: ['src/resources/frontend/css/social-proof.css'],
    outfile: 'src/resources/frontend/css/social-proof.min.css',
    bundle: false,
    minify: true,
    sourcemap: true,
  },
  // CP CSS
  {
    entryPoints: ['src/resources/cp/css/social-proof-cp.css'],
    outfile: 'src/resources/cp/css/social-proof-cp.min.css',
    bundle: false,
    minify: true,
    sourcemap: true,
  },

  // ── Popup subsystem (Phase 2) ─────────────────────────────────────────
  // Each file minified in-place; the popup asset bundles load them in order.
  ...[
    'src/resources/popups/js/renderer.js',
    'src/resources/popups/js/queue.js',
    'src/resources/popups/js/event-sender.js',
    'src/resources/popups/js/popups.js',
    'src/resources/popups/js/popups-async.js',
    'src/resources/popups/js/triggers/time-on-page.js',
    'src/resources/popups/js/triggers/exit-intent.js',
    'src/resources/popups/js/triggers/scroll-depth.js',
    'src/resources/popups/js/triggers/element-click.js',
    'src/resources/popups/js/triggers/page-match.js',
    'src/resources/popups/js/triggers/user-state.js',
    'src/resources/popups/js/layouts/newsletter.js',
    'src/resources/popups/js/layouts/discount.js',
  ].map((entry) => ({
    entryPoints: [entry],
    outfile: entry.replace(/\.js$/, '.min.js'),
    bundle: false,
    minify: true,
    sourcemap: true,
  })),

  // Popup CSS
  {
    entryPoints: ['src/resources/popups/css/social-proof-popups.css'],
    outfile: 'src/resources/popups/css/social-proof-popups.min.css',
    bundle: false,
    minify: true,
    sourcemap: true,
  },

  // Popup CP JS
  {
    entryPoints: ['src/resources/cp/js/popup-edit.js'],
    outfile: 'src/resources/cp/js/popup-edit.min.js',
    bundle: false,
    minify: true,
    sourcemap: true,
  },
];

async function run() {
  if (watch) {
    const contexts = await Promise.all(configs.map(c => esbuild.context(c)));
    await Promise.all(contexts.map(ctx => ctx.watch()));
    console.log('Watching for changes...');
  } else {
    await Promise.all(configs.map(c => esbuild.build(c)));
    console.log('Build complete.');
  }
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
