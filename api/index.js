const path = require('path');
const scale0 = require('scale0');

exports.handler = async function (event, context, callback) {
    const pathToWP = path.join(process.cwd(), 'wp');

    return await scale0({docRoot: pathToWP, event: event});
}