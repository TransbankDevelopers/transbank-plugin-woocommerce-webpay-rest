const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("node:path");
const glob = require("glob");
const RemoveEmptyScriptsPlugin = require("webpack-remove-empty-scripts");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

const isProd = process.env.NODE_ENV === "production";

const PAGES_BASE = path.resolve(__dirname, "assets/src/admin/pages");

const pageSlugFromFile = (absFile) => {
    const rel = path.relative(PAGES_BASE, absFile);
    const dir = path.dirname(rel);
    return dir.split(path.sep).join("-").replaceAll(/[^a-zA-Z0-9-_]/g, "-");
};

const buildEntries = () => {
    const entries = {};

    glob.sync(path.join(PAGES_BASE, "**/*.js")).forEach((file) => {
        const slug = pageSlugFromFile(file);
        entries[`admin-${slug}`] = file;
    });

    glob.sync(path.join(PAGES_BASE, "**/!(_)*.scss")).forEach((file) => {
        const slug = pageSlugFromFile(file);
        entries[`admin-${slug}-style`] = file;
    });

    return entries;
};

const replacePlugin = (plugins, pluginName, nextPlugin) => {
    const idx = plugins.findIndex((p) => p?.constructor?.name === pluginName);
    if (idx === -1) {
        return plugins;
    }

    const copy = [...plugins];
    copy[idx] = nextPlugin;
    return copy;
};

module.exports = {
    ...defaultConfig,
    name: "tbk-admin",

    entry: buildEntries(),

    devtool: isProd ? false : "source-map",

    output: {
        path: path.resolve(__dirname, "assets/build/admin"),
        filename: "js/[name].js",
        chunkFilename: "js/chunks/[name].js",
        publicPath: "",
        clean: true
    },

    optimization: {
        ...defaultConfig.optimization,
        splitChunks: false,
        runtimeChunk: false
    },

    cache: isProd
        ? false
        : {
            type: "filesystem",
            cacheDirectory: path.resolve(__dirname, ".webpack-cache-admin"),
            buildDependencies: { config: [__filename] }
        },

    plugins: (() => {
        let plugins = defaultConfig.plugins.filter(
            (plugin) => plugin.constructor.name !== "RtlCssPlugin"
        );

        plugins = replacePlugin(
            plugins,
            "MiniCssExtractPlugin",
            new MiniCssExtractPlugin({
                filename: "css/[name].css"
            })
        );

        plugins.unshift(new RemoveEmptyScriptsPlugin());

        return plugins;
    })()
};
