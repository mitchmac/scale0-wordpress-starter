exports.validate = function(response) {
    if (
        !process.env['SCALE0_DB_NAME'] || 
        !process.env['SCALE0_DB_USER'] ||
        !process.env['SCALE0_DB_PASSWORD'] ||
        !process.env['SCALE0_DB_HOST']
    ) {

        if (process.env['SITE_NAME']) {
            dashboardLink = `https://app.netlify.com/sites/${process.env['SITE_NAME']}/settings/env`;
        }
        else {
            dashboardLink = 'https://vercel.com/dashboard';
        }

        let message = "<p>It appears that the required environment variables for the WordPress database aren't setup.</p>"
        + "<p>If you don't have a database created yet, head over to <a href='https://planetscale.com'>PlanetScale</a> to create one.</p>"
        + `<p>Then you'll need to populate the environment variables for your site at Vercel or Netlify (<a href="${dashboardLink}">dashboard</a>)`
        + '<p>The required variables are:</p>'
        + `<pre><code>
            SCALE0_DB_NAME
            SCALE0_DB_USER
            SCALE0_DB_PASSWORD
            SCALE0_DB_HOST
           </code></pre>`
        + '<p>Then <strong>remember to redeploy your site at Vercel or Netlify</strong> for the environment variables to be updated for the site.</p>';

        return {
            statusCode: 500,
            headers: response.headers,
            body: loadTemplate(message)
        }
    }
}

function loadTemplate(message){
    return `
    <!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
        <title>Scale0 WordPress Starter</title>
      </head>
      <body>
        <main class="container" style="width: 800px; margin: 0 auto">
          <h1>You're almost there!</h1>
          ${message}
        </main>
      </body>
    </html>
    `;
}