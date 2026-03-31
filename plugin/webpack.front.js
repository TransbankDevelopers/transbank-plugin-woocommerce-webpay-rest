const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const WooCommerceDependencyExtractionWebpackPlugin = require("@woocommerce/dependency-extraction-webpack-plugin");
const RemoveEmptyScriptsPlugin = require("webpack-remove-empty-scripts");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const path = require("node:path");
const glob = require("glob");

const isProd = process.env.NODE_ENV === "production";

const PAGES_BASE = path.resolve(__dirname, "assets/src/front/pages");
const FRONT_BUILD_DIR = "assets/build/front";

const wcDepMap = {
    "@woocommerce/blocks-registry": ["wc", "wcBlocksRegistry"],
    "@woocommerce/settings": ["wc", "wcSettings"]
};

const wcHandleMap = {
    "@woocommerce/blocks-registry": "wc-blocks-registry",
    "@woocommerce/settings": "wc-settings"
};

const requestToExternal = (request) => {
    if (wcDepMap[request]) {
        return wcDepMap[request];
    }
};

const requestToHandle = (request) => {
    if (wcHandleMap[request]) {
        return wcHandleMap[request];
    }
};

const slugFromFile = (absFile) => {
    const rel = path.relative(PAGES_BASE, absFile);
    const relDir = path.dirname(rel);

    return relDir.split(path.sep).join("-").replaceAll(/[^a-zA-Z0-9-_]/g, "-");
};

const entryNameFromFile = (absFile, isStyle = false) => {
    const slug = slugFromFile(absFile);
    const baseName = `front-${slug}`;

    return isStyle ? `${baseName}-style` : baseName;
};

const buildEntries = () => {
    const entries = {};

    glob.sync(path.join(PAGES_BASE, "**/*.js")).forEach((file) => {
        entries[entryNameFromFile(file)] = file;
    });

    glob.sync(path.join(PAGES_BASE, "**/!(_)*.scss")).forEach((file) => {
        entries[entryNameFromFile(file, true)] = file;
    });

    return entries;
};

// Export configuration.
module.exports = {
    ...defaultConfig,
    entry: buildEntries(),
    devtool: isProd ? false : "source-map",
    output: {
        path: path.resolve(__dirname, FRONT_BUILD_DIR),
        filename: "[name].js"
    },
    cache: isProd
        ? false
        : {
            type: "filesystem",
            cacheDirectory: path.resolve(__dirname, ".webpack-cache-front"),
            buildDependencies: { config: [__filename] }
        },
    plugins: [
        new RemoveEmptyScriptsPlugin(),
        ...defaultConfig.plugins.filter((plugin) => {
            return ![
                "DependencyExtractionWebpackPlugin",
                "MiniCssExtractPlugin"
            ].includes(plugin.constructor.name);
        }),
        new MiniCssExtractPlugin({
            filename: "[name].css"
        }),
        new WooCommerceDependencyExtractionWebpackPlugin({
            requestToExternal,
            requestToHandle
        })
    ]
};
