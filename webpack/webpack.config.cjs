const path = require('node:path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const WebpackAssetsManifestModule = require('webpack-assets-manifest');
const WebpackAssetsManifest =
  WebpackAssetsManifestModule.WebpackAssetsManifest
  ?? WebpackAssetsManifestModule.default
  ?? WebpackAssetsManifestModule;

module.exports = (_env, argv) => {
  const isProduction = argv.mode === 'production';

  return {
    context: __dirname,
    entry: {
      main: './src/main.ts',
    },
    output: {
      path: path.resolve(__dirname, '../assets/dist'),
      filename: isProduction ? '[name].[contenthash:8].js' : '[name].js',
      assetModuleFilename: '[name].[contenthash:8][ext]',
      publicPath: 'auto',
      clean: isProduction,
    },
    devtool: isProduction ? false : 'eval-cheap-module-source-map',
    module: {
      rules: [
        {
          test: /\.tsx?$/,
          use: {
            loader: 'ts-loader',
            options: {
              transpileOnly: true,
            },
          },
          exclude: /node_modules/,
        },
        {
          test: /\.css$/,
          use: [isProduction ? MiniCssExtractPlugin.loader : 'style-loader', 'css-loader'],
        },
        {
          test: /\.(png|jpe?g|gif|svg|webp)$/i,
          type: 'asset/resource',
        },
      ],
    },
    resolve: {
      extensions: ['.tsx', '.ts', '.js'],
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: isProduction ? '[name].[contenthash:8].css' : '[name].css',
      }),
      new WebpackAssetsManifest({
        output: 'manifest.json',
        entrypoints: true,
        publicPath: '',
      }),
    ],
    optimization: {
      splitChunks: false,
      runtimeChunk: false,
    },
    devServer: {
      host: 'localhost',
      port: 8090,
      hot: true,
      allowedHosts: 'auto',
      client: {
        webSocketURL: 'ws://localhost:8090/ws',
      },
      devMiddleware: {
        writeToDisk: false,
      },
    },
  };
};
