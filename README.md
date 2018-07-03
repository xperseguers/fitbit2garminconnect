Fitbit to Garmin Connect
========================

This project is useful if, like me, you own a Fitbit Aria Smart Scale (for instance) and want to
synchronize your weight to your Garmin Connect account.

It looks like there are no real solution for that simple goal so I wrote this project.

And since I wanted it to be as easy to use as possible, it doesn't require any complex OAuth2
setup but will basically emulate your browser, connect to your Fitbit account, fetch data,
connect to your Garmin Connect account and push data as if you would use the web interface to
manually enter your weight.


**Usage**

1. Clone this repository
2. Copy `config.php.sample` as `config.php`, then adapt credentials
3. Execute `index.php`

Easy! Straightforward!

Hope you'll enjoy this project, after all this was all about reverse-engineering the web UI of
Fitbit and Garmin Connect :)

Cheers

Xavier
