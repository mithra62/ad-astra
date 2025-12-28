## About Checkoff Pro API

This program includes 3 components

- Website to manage the system
- Command script to import data remotely and consistently
- A Level 4 REST API with token based authentication

## Requirements

- PHP >= 8.2
- MySQL >= 8.x
- Curl

## Installation

Within the project directory, execute the below Commands (in order)

1. `composer install`
2. `npm install`

- Rename `.env.example` to `.env`
- Fill out the newly created `.env` file to match your system

### Install Data

Execute the below in the order provided

1. `php artisan migrate`
2. `php artisan db:seed --class=DatabaseSeeder`

### Helpful Commands

`php artisan config:clear`
`php artisan optimize:clear`
`php artisan cache:clear`
`php artisan route:clear`
`php artisan view:clear`
`php artisan storage:link`
`php artisan key:generate`
`php artisan jwt:secret`
`php artisan jwt:generate`
`php artisan jwt:refresh`
`php artisan jwt:check`
`php artisan jwt:blacklist`


### Log In

3 users will be created with the data seed. Check the `users` table and use the password `password` to gain initial access. 

## Data Import

You'll have to edit your `.env` file to include a copy of the API key for the master/parent site. You'll get this from "someone". Update the `CRAFT_API_TOKEN` config value with the API key before proceeding. 

Once that's done, execute the below command from the project woor. 

`php artisan app:sync-craft`

The above will sync the local system data from the remote system. 

## API Documentation

Execute the below command to generate the API documentation. 

`php artisan l5-swagger:generate`

