const path = require('path');
const scale0 = require('scale0');

const { validate } = require('../util/install.js');
const { setup } = require('../util/directory.js');

exports.handler = async function (event, context, callback) {
    setup();

    let response = await scale0({docRoot: '/tmp/wp', event: event});
    let checkInstall = validate(response);

    if (checkInstall) {
        return checkInstall;
    }
    else {
        return response;
    }
}