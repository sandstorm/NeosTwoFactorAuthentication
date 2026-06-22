const esbuild = require("esbuild");
const extensibilityMap = require("@neos-project/neos-ui-extensibility/extensibilityMap.json");
const isWatch = process.argv.includes("--watch");

// `alias: extensibilityMap` resolves redux-saga, @neos-project/* etc. to the
// host's shared runtime instead of bundling private copies. This is essential
// for redux-saga: a separately bundled copy uses different effect Symbols, so
// the host saga middleware would not recognise our `take()` and the saga would
// silently never fire.
/** @type {import("esbuild").BuildOptions} */
const options = {
  logLevel: "info",
  bundle: true,
  target: "es2020",
  entryPoints: { Plugin: "src/index.ts" },
  outdir: "../../Public/Neos.Ui/",
  alias: extensibilityMap,
};

if (isWatch) {
  esbuild.context(options).then((ctx) => ctx.watch());
} else {
  esbuild.build(options);
}
