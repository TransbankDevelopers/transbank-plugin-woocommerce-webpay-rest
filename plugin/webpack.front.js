const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const WooCommerceDependencyExtractionWebpackPlugin = require("@woocommerce/dependency-extraction-webpack-plugin");
const path = require("node:path");

const FRONT_SOURCE_DIR = "assets/src/front/block";
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

// Export configuration.
module.exports = {
    ...defaultConfig,
    entry: {
        webpay_blocks: `./${FRONT_SOURCE_DIR}/webpay_checkout.js`,
        oneclick_blocks: `./${FRONT_SOURCE_DIR}/oneclick_checkout.js`,
        notice_handler: `./${FRONT_SOURCE_DIR}/notice_handler.js`
    },
    output: {
        path: path.resolve(__dirname, FRONT_BUILD_DIR),
        filename: "[name].js"
    },
    plugins: [
        ...defaultConfig.plugins.filter(
            (plugin) =>
                plugin.constructor.name !== "DependencyExtractionWebpackPlugin"
        ),
        new WooCommerceDependencyExtractionWebpackPlugin({
            requestToExternal,
            requestToHandle
        })
    ]
};
