# Scale0 WordPress Starter
Serverless WordPress on Vercel or Netlify

| Netlify | Vercel |
| --- | --- |
| [![Deploy to Netlify](https://www.netlify.com/img/deploy/button.svg)](https://app.netlify.com/start/deploy?repository=https://github.com/mitchmac/scale0-wordpress-starter) |[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Fmitchmac%2Fscale0-wordpress-starter) |

## Setup
1. Deploy this repository to Vercel or Netlify
2. Setup a MySQL database for WordPress to use. [PlanetScale](https://planetscale.com/) is a great option with a free-tier.
3. Update environment variables for your project in Vercel or Netlify with the database credentials. These are used by wp-config.php. The environment variables are:
```
SCALE0_DB_NAME
SCALE0_DB_USER
SCALE0_DB_PASSWORD
SCALE0_DB_HOST
```

## Structure
- WordPress and its files are in the ```/wp``` directory. You can add plugins or themes there in their respective directories in ```wp-content```
- `netlify.toml` or `vercel.json` are what directs all requests to be served by the file in `api/index.js`

## Gotchas
- WordPress + the PHP files included by Scale0 take up about 43MB. This puts the deployment size close to the 50MB limit for serverless functions.