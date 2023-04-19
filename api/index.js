const path = require('path');
const scale0 = require('scale0');

const { validate } = require('../util/install.js');

exports.handler = async function (event, context, callback) {
    const pathToWP = path.join(process.cwd(), 'wp');

    let response = await scale0({docRoot: pathToWP, event: event});
    let checkInstall = validate(response);

    if (checkInstall) {
        return checkInstall;
    }
    else {
        return response;
    }
}