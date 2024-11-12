Coworking App

# Purpose
This app is designed to help small coworking spaces manage their members, bookings, and payments.
I have written it for my own coworking space. 

# Technologies
I have chosen to use the following technologies:
- Php 8
- MySQL 8
- Symfony 7

Originally I wanted to write an API, but as I am not very familiar with front end technologies, I have chosen to write a full stack app.
However, I kept in mind that it might be transformed into an API in the future.

# Setup
This project works with the local PHP server and local MySQL server.
It has no docker configuration. However, the Symfony documentation is very good and you can easily add a docker configuration.

Install the symfony CLI tool - it will provide out of the box the php server
https://symfony.com/download
Clone the project
`composer install `
`symfony server:start` or `symfony serve -d`

`bin/console doctrine:database:create`
`bin/console doctrine:migrations:migrate`
`bin/console doctrine:fixtures:load`

`bin/console app:business-days`
to generate the business days in the future, as fixtures might be outdated. 

You now have a running application with some test data.

# Technical Choices Codebase
## UUId
I have chosen to use UUIds in the URLs for sensitive entities.

## Forms
Most of the forms are simple HTML forms, without the Symfony Form component. But always with CSRF protection. 
This choice should allow me to be more flexible with the use of the forms, making ajax calls easier and/or transform the code into an API

## Code Quality Tools
PHPStan & PHPCsFixer
vendor/bin/php-cs-fixer fix src --allow-risky=yes
vendor/bin/phpstan analyse src --memory-limit 256M

## Testing
I have written some tests for the most critical parts of the app.
They are a mix of unit and functional tests.
As it is a small app, testing fixtures a simple Doctrine Fixtures. 
There are not tests for the Admin section as it is mainly a CRUD interface.

### Commands
Run the following command to get a coverage report.
`./vendor/bin/phpunit --coverage-html tests/coverageReport`

## Configuration 
Specific parameters are found in the services.yaml file.
I have chosen this way, as they are not environment dependent and thus should not be found in the .env file. 
I did not want to do a setting table in the database, as I think it is overkill for this small app.
Depending on how this app might be used by others in the future, this might change.

## Translations & Internationalization
There are two languages in the translation files, but only the German translations are used. I planned to use the browser 
locale to switch between languages, but this goes against good practices and for instance it is only supposed to be used in Germany. 
Number and Date formats are hard coded to German standard.
Depending on the future use of the app, this might change.

## Admin
I have chosen EasyAdmin for the Admin interface. It is a simple and easy to use tool.

## PDFs
I have chosen to use Fpdi to generate PDFs. It is a simple and easy to use tool.
The quickest way to generate my PDFs, is using this library to fill in a template PDF.
The locations are hardcoded in the Class. I can provide the templates if needed. It is an Excel file. 

# Documentation
The documentation is in the doc folder.
Some functionalities are very specific to my needs, like the invoices being sent to the document vault.
At some point this feature will need to change its design, so it can be optional. 