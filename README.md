# Converter API
A powerful PHP API for media download & conversion (Work in progress)

[![GitHub stars](https://img.shields.io/github/stars/Stormiix/converter-api.svg)](https://github.com/Stormiix/converter-api/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/Stormiix/converter-api.svg?style=flat)](https://github.com/Stormiix/converter-api/network)
![stability-wip](https://img.shields.io/badge/stability-work_in_progress-lightgrey.svg)
[![HitCount](http://hits.dwyl.com/stormiix/converter-api.svg)](http://hits.dwyl.com/stormiix/converter-api)
[![contributions welcome](https://img.shields.io/badge/contributions-welcome-brightgreen.svg?style=flat)](https://github.com/stormiix/converter-api/issues)
[![Twitter](https://img.shields.io/twitter/url/https/github.com/Stormiix/converter-api.svg?style=flat)](https://twitter.com/intent/tweet?text=Check%20this%20out%20%40Stormix4:&url=https%3A%2F%2Fgithub.com%2FStormiix%2Fconverter-api)

## Getting Started

Converter-API is a PHP api that serves as a backend to my website: [PlaylistConverter](https://playlist-converter.me/?github).
It can be used to create streaming sites "Downloaders/Converters". It uses Youtube-DL to fetch json info about each download such as (Title,description,available formats..etc).

### Prerequisites

In order to use converter-api you must have the following :

- Youtube-DL [Download Here](https://github.com/rg3/youtube-dl/)
- A Deezer developer API Key
- A Google API Key
- That's it :D

### Installing

1. Clone the repo to your folder.
2. create a ".env" file to hold your api keys and general configuration:

``` .env
APP_URL = "https://playlist-converter.me/"
DOMAIN = "playlist-converter.me"
ENCRYPT_KEY ="somerandomstringhere"
ENV = "DEV" or "PROD"
VERSION = "v1"
STACK = "LEMP" or "LAMP"
DOWNLOAD_FOLDER = "tmp" #This is the folder where most downloads/conversions happen

DATABASE_NAME=""
DATABASE_USER=""
DATABASE_PASS=""
DATABASE_HOST="localhost"

DEEZER_APP_KEY = "xxxxxx"
DEEZER_APP_SECRET = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
GOOGLE_API_KEY = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx--xxxxxxx"
LAST_FM_API_KEY=  "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"

```
3. Add URL-Rewriting: Since I'm using Klein PHP router, a few changes must be made to your  server configuraion.
    * Apache

      Make sure [AllowOverride](http://httpd.apache.org/docs/2.0/mod/core.html#allowoverride) is on for your directory, or put in `httpd.conf`

          # Apache (.htaccess or httpd.conf)
          RewriteEngine On
          RewriteCond %{REQUEST_FILENAME} !-f
          RewriteRule . /index.php [L] 

	* nginx

    		# basics
    		try_files $uri $uri/ /index.php?$args;
4. Import the database "database.sql"
5. To be continued ...

## Built With

* [PHP](http://php.net/)

## Used libraries

* [filp/whoops](https://github.com/filp/whoops)
* [fkooman/json](https://github.com/fkooman/json)
* [klein/klein ♥️](https://github.com/klein/klein)
* [google/apiclient](https://github.com/google/apiclient)
* [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)
* [sentry/sentry](https://github.com/sentry/sentry)
* [monolog/monolog](https://github.com/monolog/monolog)

## Authors

* **Anas Mazouni** - *Initial work* - [Converter-API](https://github.com/stormiix)

See also the list of [contributors](https://github.com/stormiix/converter-api/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details

## TODO

- [x] /fetch
- [x] /download
- [x] /formats
- what's next ?
