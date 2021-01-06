import path from 'path';

export default {
  mode: 'production',
  entry: path.join(__dirname, 'src/lama.js'),
  output: {
    path: path.join(__dirname, 'dist'),
    filename: 'lama.js'
  },
  module: {
    rules: [{
      test: /\.js/,
      exclude: /(node_modules|bower_components)/,
      use: [{
        loader: 'babel-loader'
      }]
    }]
  },
  stats: {
    colors: true
  },
  devtool: 'source-map'
};
