const path = require('path');

module.exports = {
    mode: 'production',
    entry: {
        'admin-backend': './src/admin-backend.ts',
        'admin-frontend': './src/admin-frontend.ts'
    },
    module: {
        rules: [
            {
                test: /\.tsx?$/,
                use: 'ts-loader',
                exclude: /node_modules/,
            },
        ],
    },
    resolve: {
        extensions: ['.ts', '.js' ],
    },
    output: {
        path: path.resolve(__dirname, '../js'),
    },
};
