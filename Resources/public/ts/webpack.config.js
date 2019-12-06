const path = require('path');

module.exports = {
    entry: {
        backend: './src/admin-backend.ts',
        frontend: './src/admin-frontend.ts'
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
        path: path.resolve(__dirname, 'dist'),
    },
};
